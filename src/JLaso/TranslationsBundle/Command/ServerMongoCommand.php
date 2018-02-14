<?php

namespace JLaso\TranslationsBundle\Command;

/*
 *
 *
 * {"command":"key index", "key":"1234", "secret":1234, "project_id":1, ...}
 *
 * {"command":"bundle index", "key":"1234", "secret":1234, "project_id":1, ...}
 *
 *
 *
 */

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Doctrine\ODM\MongoDB\DocumentManager;
use JLaso\TranslationsBundle\Document\File;
use JLaso\TranslationsBundle\Document\Repository\ProjectInfoRepository;
use JLaso\TranslationsBundle\Document\Repository\TranslatableDocumentRepository;
use JLaso\TranslationsBundle\Document\Repository\TranslationRepository;
use JLaso\TranslationsBundle\Document\TranslatableDocument;
use JLaso\TranslationsBundle\Document\Translation;
use JLaso\TranslationsBundle\Controller\RestController;
use JLaso\TranslationsBundle\Entity\Project;
use JLaso\TranslationsBundle\Entity\Repository\ProjectRepository;
use JLaso\TranslationsBundle\Service\Manager\TranslationsManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ServerMongoCommand extends ContainerAwareCommand
{
    const VERSION = "1.4";

    /** Shutdowns the server */
    const CMD_SHUTDOWN            = 'shutdown';

    const CMD_PROJECTS            = 'project-index';
    const CMD_CATALOG_INDEX       = 'catalog-index';
    const CMD_TRANSDOC_INDEX      = 'transdoc-index';
    const CMD_TRANSDOC_SYNC       = 'transdoc-sync';
    const CMD_TRANSDOC_GET        = 'transdoc-get';

//    const CMD_KEY_INDEX           = 'key-index';
//    const CMD_BUNDLE_INDEX        = 'bundle-index';
//    const CMD_TRANSLATION_DETAILS = 'translation-details';
//    const CMD_TRANSLATIONS        = 'translations';
//    const CMD_GET_COMMENT         = 'get-comment';
//    const CMD_PUT_MESSAGE         = 'put-message';
//    const CMD_UPDATE_MESSAGE      = 'update-message-if-newest';
//    const CMD_UPDATE_COMMENT      = 'update-comment-if-newest';
//    const CMD_BLOCK_SYNC          = 'block-sync';

    /** Mongo part */
    const CMD_UPLOAD_KEYS   = 'upload-keys';
    const CMD_DOWNLOAD_KEYS = 'download-keys';

    const ACK = 'ACK';
    const NO_ACK = 'NO-ACK';

    const BLOCK_SIZE = 1024;

    const SIGNATURE = '000000:000:000:';

    /** @var  EntityManager */
    protected $em;
    /** @var  DocumentManager */
    protected $dm;
    protected $socket;
    protected $msgsock;
    /** @var  Project */
    protected $project;
    /** @var  TranslationsManager */
    protected $translationsManager;

    /** statistics and debug properties */
    protected $debug = true;
    //protected $showCommand = true;
    //protected $showExceptions = false;
    protected $sended = 0;
    protected $received = 0;
    protected $timeStart;

    /** @var OutputInterface */
    protected $output;

    protected $lzfUse = true;

    /**
     * configure the command that starts the server.
     */
    protected function configure()
    {
        $this
            ->setName('jlaso:translations:server-mongo-start')
            ->setDescription('Start the server')
            ->addArgument('address', InputArgument::REQUIRED, 'server address')
            ->addArgument('port', InputArgument::REQUIRED, 'port number where start server')
            ->addOption('lzf', null, InputOption::VALUE_OPTIONAL, 'lzf option, default yes')
            ->addOption('debug', null, InputOption::VALUE_NONE, 'debug')
        ;
    }

    /**
     * Atomic send of a string trough the socket.
     *
     * @param $msg
     *
     * @return int
     */
    protected function send($msg)
    {
        $this->debug("-> send '%s'", $msg);
        $msg .= PHP_EOL;

        return socket_write($this->msgsock, $msg, strlen($msg));
    }

    /**
     * Reads the socket.
     *
     * @param bool $compress
     *
     * @return int|string
     */
    protected function readSocket($compress = true)
    {
        $buffer = '';
        do {
            if ((time() - $this->timeStart) > 100) {
                exit -1;
            }
            $buf = socket_read($this->msgsock, strlen(self::SIGNATURE) + self::BLOCK_SIZE, PHP_BINARY_READ);
            if ($buf === false) {
                echo "socket_read() failed: reason: ".socket_strerror(socket_last_error($this->msgsock))."\n";

                return -2;
            }

            if (!trim($buf)) {
                return '';
            }

            $signature = substr($buf, 0, strlen(self::SIGNATURE));
            if (substr_count($signature, ":") < 3) {
                $this->send(self::NO_ACK);
                $this->exception('error in signature format');
            }
            list($size, $block, $blocks)  = explode(":", $signature);
            $size = intval($size);
            $block = intval($block);
            $blocks = intval($blocks);
            if(!$size || !$block || !$blocks){
                $this->send(self::NO_ACK);
                $this->exception("error in signature: zero");
            }
            $aux = substr($buf, strlen(self::SIGNATURE));

            $this->debug("received block %d of %d <info>%s</info>", $block, $blocks, substr($buf, 0, strlen(self::SIGNATURE)));
            $this->hexDump($aux);

            if ($size == strlen($aux)) {
                $this->send(self::ACK);
            } else {
                $this->send(self::NO_ACK);
                $this->exception(sprintf('error in size (block %d of %d): informed %d vs %d read', $block, $blocks, $size, strlen($aux)));
            }

            $buffer .= $aux;
        } while ($block < $blocks);

        $this->received += strlen($buffer);
        $size = $this->prettySize($this->received);
        $this->debug("<error>v</error> downloaded %s" , $size);

        $this->debug("size of buffer is %d", strlen($buffer));
        $this->hexDump($buffer);

        if ($compress) {
            if ($this->lzfUse) {
                $this->debug('lzf_decompressing');
                $result = lzf_decompress($buffer);
            } else {
                $this->debug('gzuncompressing');
                $result = gzuncompress($buffer);
            }
        } else {
            $result = $buffer;
        }
        if(false === $result){
            $this->exception('Error descompressing data');
        }

        $this->debug($result);

        if ($this->debug) {
            $aux = json_decode($result, true);
            if (isset($aux['data'])) {
                echo sprintf("received %d keys\n", count($aux['data']));
            }
        }

        return $result;
    }

    protected function hexDump($data)
    {
        $result = array();
        for($i=0; $i<strlen($data); $i++){
            $result[] = sprintf("%02X", ord($data[$i]));
        }
        $result = join(':', $result);

        $this->debug( '[' . $result . ']');
    }

    protected function debug()
    {
        if($this->debug){
            $msg = call_user_func_array('sprintf', func_get_args());
            $this->output->writeln($msg);
        }
    }

    /**
     * Body of the command.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        set_time_limit(0);
        ob_implicit_flush();
        $this->timeStart = time();

        if ($input->getOption('lzf') && (strtolower($input->getOption('lzf')) == 'no')) {
            $this->lzfUse = false;
        };

        $this->output = $output;
        $this->debug = $input->getOption('debug') ? true : false;
        $container = $this->getContainer();
        $this->em = $container->get('doctrine.orm.default_entity_manager');
        $this->dm = $container->get('doctrine.odm.mongodb.document_manager');
        $this->translationsManager = $container->get('jlaso.translations_manager');

        $address = $input->getArgument('address');
        $port    = $input->getArgument('port');

        $this->debug('<info>Started mongo socket server on %s:%d</info>', $address, $port);

        if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
            echo "socket_create() error: ".socket_strerror(socket_last_error())."\n";
        }

        if (socket_bind($sock, $address, $port) === false) {
            echo "socket_bind() error: ".socket_strerror(socket_last_error($sock))."\n";
        }

        if (socket_listen($sock, 5) === false) {
            echo "socket_listen() error: ".socket_strerror(socket_last_error($sock))."\n";
        }

        do {
            if (($this->msgsock = socket_accept($sock)) === false) {
                echo "socket_accept() error: ".socket_strerror(socket_last_error($sock))."\n";
                break;
            }
            $this->send("Welcome to Tradukoj (".self::VERSION.") lzf: ".($this->lzfUse ? "yes" : "no"));

            do {
                $buf = $this->readSocket();

                if ($buf) {
                    try {
                        $read     = json_decode($buf, true);
                        /*
                         * fixed or not data that comes with the data received
                         */
                        $command  = isset($read['command']) ? $read['command'] : '';
                        $bundle   = isset($read['bundle']) ? $read['bundle'] : '';
                        $fileName = isset($read['file_name']) ? $read['file_name'] : '';
                        $key      = isset($read['key']) ? $read['key'] : '';
                        $locale   = isset($read['locale']) ? $read['locale'] : '';
                        $catalog  = isset($read['catalog']) ? $read['catalog'] : RestController::DEFAULT_CATALOG;
                        $message  = isset($read['message']) ? $read['message'] : '';
                        $comment  = isset($read['comment']) ? $read['comment'] : '';
                        $lastModification = isset($read['last_modification']) ? new \DateTime($read['last_modification']) : null;

                        $this->debug("* received command '<error>%s</error>'", $command);

                        switch ($command) {

                            case self::CMD_PROJECTS:
                                $projects = $this->getProjectIndex();
                                $this->resultOk($projects);
                                break;

                            case self::CMD_CATALOG_INDEX:
                                if ($this->validateRequest($buf)) {
                                    $this->getCatalogIndex($this->project->getId());
                                };
                                break;

                            case self::CMD_TRANSDOC_INDEX:
                                if ($this->validateRequest($buf)) {
                                    $this->getCatalogIndex($this->project->getId());
                                };
                                break;

                            case self::CMD_TRANSDOC_SYNC:
                                if ($this->validateRequest($buf)) {
                                    $this->transDocSync($this->project->getId(), $bundle, $key, $locale, $fileName, $message, $lastModification);
                                };
                                break;

                            case self::CMD_TRANSDOC_GET:
                                if ($this->validateRequest($buf)) {
                                    $this->getCatalogIndex($this->project->getId());
                                };
                                break;

//
//                            case self::CMD_KEY_INDEX:
//                                if($this->validateRequest($buf)){
//                                    $keyRepository = $this->getKeyRepository();
//                                    $keys          = $keyRepository->findAllKeysForProjectAndBundle($this->project, $bundle);
//                                    foreach($keys as $key){
//                                        $keysResult[] = $key->getKey();
//                                    }
//                                    $this->resultOk(array('keys' => $keysResult));
//                                };
//                                break;
//
//                            case self::CMD_BUNDLE_INDEX:
//                                if($this->validateRequest($buf)){
//                                    $keyRepository = $this->getKeyRepository();
//                                    $bundles       = $keyRepository->findAllBundlesForProject($this->project);
//                                    $this->resultOk(array('bundles' => $bundles));
//                                };
//                                break;
//
//                            case self::CMD_TRANSLATION_DETAILS:
//                                if($this->validateRequest($buf)){
//                                    $this->getTranslationDetails($this->project, $bundle, $key, $language, $catalog);
//                                };
//                                break;
//
//                            case self::CMD_TRANSLATIONS:
//                                if($this->validateRequest($buf)){
//                                    $this->getTranslations($this->project, $bundle, $key, $catalog);
//                                };
//                                break;
//
//                            case self::CMD_GET_COMMENT:
//                                if($this->validateRequest($buf)){
//                                    $this->getComment($this->project, $bundle, $key, $catalog);
//                                }
//                                break;
//
//                            case self::CMD_PUT_MESSAGE:
//                                if($this->validateRequest($buf)){
//                                    $this->putMessage($this->project, $bundle, $key, $language, $catalog, $message);
//                                }
//                                break;
//
//                            case self::CMD_UPDATE_COMMENT:
//                                if($this->validateRequest($buf)){
//                                    $this->updateCommentIfNewest($this->project, $bundle, $key, $catalog, $lastModification, $comment);
//                                }
//                                break;
//
//                            case self::CMD_UPDATE_MESSAGE:
//                                if($this->validateRequest($buf)){
//                                    $this->updateMessageIfNewest($this->project, $bundle, $key, $language, $catalog, $lastModification, $message);
//                                }
//                                break;
//
//                            case self::CMD_BLOCK_SYNC:
//                                if($this->validateRequest($buf)){
//                                    $data = isset($read['data']) ? $read['data'] : null;
//                                    $this->blockSync($this->project, $catalog, $language, $bundle, $data);
//                                }
//                                break;

                            case self::CMD_UPLOAD_KEYS:
                                if ($this->validateRequest($buf)) {
                                    $data = isset($read['data']) ? $read['data'] : null;
                                    $this->receiveKeys($this->project, $catalog, $data);
                                }
                                break;

                            case self::CMD_DOWNLOAD_KEYS:
                                if ($this->validateRequest($buf)) {
                                    $data = isset($read['data']) ? $read['data'] : null;
                                    $this->sendKeys($this->project, $catalog);
                                }
                                break;

                            case self::CMD_SHUTDOWN:
                                //socket_close($sock);
                                $this->resultOk();
                                sleep(1);
                                socket_close($this->msgsock);
                                exit;

                            default:
                                $this->exception(sprintf('command \'%s\' unknown', $command));
                                break;
                        }

                    } catch (\Exception $e) {
                        $msg = $e->getCode().': '.$e->getMessage().' in line '.$e->getLine().' of file '.$e->getFile();
                        $this->exception($msg);
                        if ($e->getCode() == 0) {
                            die('error grave: '.$msg);
                        }
                    }
                }
            } while (true);
        } while (true);
    }

    /**
     * @return array
     */
    protected function getProjectIndex()
    {
        /** @var Project[] $projects */
        $projects = $this->getProjectRepository()->findAll();
        $result = array();
        foreach ($projects as $project) {
            $result[] = array(
                'id'         => $project->getId(),
                'name'       => $project->getName(),
                'created_at' => $project->getCreatedAt()->format('c'),
                'locales'    => $project->getManagedLocales(),
            );
        }

        return $result;
    }

    /**
     * @param $projectId
     * @return bool
     */
    protected function getCatalogIndex($projectId)
    {
        $catalogs = $this->getProjectInfoRepository()->getCatalogs($projectId);

        return $this->resultOk(array('catalogs' => $catalogs));
    }

    /**
     * @param $projectId
     * @return bool
     */
    protected function getTransDocIndex($projectId)
    {
        /** @var TranslatableDocument[] $documents */
        $documents = $this->getTranslatableDocumentRepository()->findBy(array('projectId' => intval($projectId)));

        $index = array();

        foreach ($documents as $document) {
            $files = $document->getFiles();

            foreach ($files as $file) {
                $index[] = array(
                    'bundle'    => $document->getBundle(),
                    'key'       => $document->getKey(),
                    'file'      => $document->getFilename(),
                    'locale'    => $file->getLocale(),
                    'updatedAt' => $file->getUpdatedAt()->format('c'),
                );
            }
        }

        return $this->resultOk(array('documents' => $index));
    }

    /**
     * @param $projectId
     * @param $bundle
     * @param $key
     * @param $locale
     * @param $fileName
     * @param $message
     * @param \DateTime $lastModification
     * @return bool
     */
    protected function transDocSync($projectId, $bundle, $key, $locale, $fileName, $message, \DateTime $lastModification)
    {
        $projectId = intval($projectId);

        /** @var TranslatableDocument[] $documents */
        $document = $this->getTranslatableDocumentRepository()->findOneBy(
            array(
                'projectId' => $projectId,
                'bundle'    => $bundle,
                'key'       => $key,
            )
        );

        if (!$document) {
            $document = new TranslatableDocument();
            $document->setProjectId($projectId);
            $document->setBundle($bundle);
            $document->setKey($key);
        }

        $files = $document->getFiles();

        $found = false;

        if ($files) {
            foreach ($files as $file) {
                if ($file->getLocale() == $locale) {
                    $found = true;
                    break;
                }
            }
        } else {
            $files = new ArrayCollection();
        }

        $updatedAt = $lastModification->getTimestamp();
        if ($found) {
            if ($file->getUpdatedAt() < $updatedAt) {
                $file->setMessage($message);
                $file->setUpdatedAt($updatedAt);
            } else {
                return $this->resultOk(
                    array(
                        'updated'   => false,
                        'message'   => $file->getMessage(),
                        'updatedAt' => $file->getUpdatedAt()->format('c'),
                    )
                );
            }
        } else {
            $file = new File();
            $file->setMessage($message);
            $file->setUpdatedAt($updatedAt);
            $file->setLocale($locale);
            $file->setFileName($fileName);
            $files->add($file);
        }

        $document->setFiles($files);

        $this->dm->persist($document);
        $this->dm->flush();

        return $this->resultOk(array('updated' => true));
    }

    /**
     * @param $msg
     * @param bool $compress
     * @return bool
     */
    protected function sendMessage($msg, $compress = true)
    {
        $this->debug("sending %s chars <info>%s</info>", strlen($msg), $msg);
        if ($compress) {
            if ($this->lzfUse) {
                $msg = lzf_compress($msg);
            } else {
                $msg = gzcompress($msg, 9);
            }
        } else {
            $msg .= PHP_EOL;
        }
        $len = strlen($msg);

        $blocks = ceil($len / self::BLOCK_SIZE);
        for ($i = 0; $i<$blocks; $i++) {
            $block = substr(
                $msg,
                $i * self::BLOCK_SIZE,
                ($i == $blocks-1) ? $len - ($i-1) * self::BLOCK_SIZE : self::BLOCK_SIZE
            );
            $prefix = sprintf("%06d:%03d:%03d:", strlen($block), $i+1, $blocks);
            $aux =  $prefix.$block;
            $this->debug("sending block %d from %d, prefix <info>%s</info>", $i+1, $blocks, $prefix);

            if (false === socket_write($this->msgsock, $aux, strlen($aux))) {
                die('error');
            };

            do {
                $read = socket_read($this->msgsock, 10, PHP_NORMAL_READ);
            } while (strpos($read, self::ACK) !== 0);
        }
        $this->sended += strlen($msg);
        $size = $this->prettySize($this->sended);
        $this->debug('<error>^</error> uploaded %s, total(d+u) %s', $size, $this->prettySize($this->sended + $this->received));

        return true;
    }

    /**
     * @param array $data
     * @return bool
     */
    protected function resultOk($data = array())
    {
        $data['result'] = true;
        $result = json_encode($data).PHP_EOL;
        $this->debug($result);

        return $this->sendMessage($result);
    }

    /**
     * @param $reason
     * @throws \Exception
     */
    protected function exception($reason)
    {
        $result = array(
            'result' => false,
            'reason' => $reason,
        );
        $result = json_encode($result).PHP_EOL;

        $this->debug($result);
        $this->send($result);

        sleep(1);
        socket_close($this->msgsock);

        die($reason);
    }

    /**
     * @param $size
     * @return string
     */
    protected function prettySize($size)
    {
        $kb = 1024;
        $mb = $kb * 1024;
        $gb = $mb * 1024;

        $result = "";
        if ($size > $gb) {
            $result .= intval($size/$gb).'Gb ';
            $size -= $gb * intval($size/$gb);
        }

        if ($size > $mb) {
            $result .= intval($size/$mb, 3).'Mb ';
            $size -= $mb * intval($size/$mb);
        }

        if ($size > $kb) {
            $result .= intval($size/$kb).'Kb ';
            $size -= $kb * intval($size/$kb);
        }

        $result .= $size . 'b ';

        return $result;
    }

    /**
     * @param $buffer
     *
     * @return bool
     */
    protected function validateRequest($buffer)
    {
        $this->project = null;
        $data          = json_decode($buffer, true);
        $projectId     = isset($data['project_id']) ? $data['project_id'] : 0;
        if (!$projectId) {
            $this->exception('invalid credentials');

            return false;
        }
        $key           = $data['auth.key'];
        $secret        = $data['auth.secret'];
        $this->project = $this->getProjectRepository()->find($projectId);
        if (!$this->project) {
            $this->exception('invalid project');

            return false;
        }
        $authorized = ($key == $this->project->getApiKey()) && ($secret == $this->project->getApiSecret());
        if(!$authorized){
            $this->exception('bad credentials');
        }

        return $authorized;
    }

    /**
     * @param Project $project
     * @param $bundle
     * @param $key
     * @param $language
     * @param string $catalog
     * @return bool|int
     */
    public function getTranslationDetails(Project $project, $bundle, $key, $language, $catalog = RestController::DEFAULT_CATALOG)
    {
        $key = urldecode($key);

        /** @var Key $keyRecord */
        $keyRecord = $this->getKeyRepository()->findOneBy(array(
                'project'  => $project,
                'bundle'   => $bundle,
                'key'      => $key,
                'catalog'  => $catalog,
            )
        );

        if (!$keyRecord) {
            return $this->exception('No key found in this bundle');
        }
        /** @var Message $message */
        $message = $this->getMessageRepository()->findOneBy(array(
                'key'      => $keyRecord,
                'language' => $language,
            )
        );
        if (!$message) {
            return $this->exception('No message found in this key/language');
        }

        return $this->resultOk(array(
                'message'           => $message->getMessage(),
                'last_modification' => $message->getUpdatedAt()->format('U'),
                'updatedAt'         => $message->getUpdatedAt()->format('c'),
            )
        );
    }

    /**
     * @param Project $project
     * @param $bundle
     * @param $key
     * @param $catalog
     * @return bool|int
     */
    public function getTranslations(Project $project, $bundle, $key, $catalog)
    {
        if (!$project || !$bundle || !$key || !$catalog) {
            return $this->exception('missing parameters');
        }
        /** @var Key $keyRecord */
        $keyRecord = $this->getKeyRepository()->findOneBy(array(
                'project'  => $project,
                'bundle'   => $bundle,
                'key'      => $key,
                'catalog'  => $catalog,
            )
        );

        if (!$keyRecord) {
            return $this->exception('No key found in this bundle');
        }
        $messages = array();
        foreach ($keyRecord->getMessages() as $message) {
            $messages[$message->getLanguage()] = array(
                'message'           => $message->getMessage(),
                'last_modification' => $message->getUpdatedAt()->format('U'),
            );
        }

        return $this->resultOk(array('messages' => $messages));
    }

    /**
     * @param Project $project
     * @param $bundle
     * @param $key
     * @param $catalog
     * @return bool|int
     */
    protected function getComment(Project $project, $bundle, $key, $catalog)
    {
        if (!$project || !$bundle || !$key || !$catalog) {
            return $this->exception('missing parameters');
        }
        /** @var Key $keyRecord */
        $keyRecord = $this->getKeyRepository()->findOneBy(array(
                'project'  => $project,
                'bundle'   => $bundle,
                'key'      => $key,
                'catalog'  => $catalog,
            )
        );

        if (!$keyRecord) {
            return $this->exception('No key found in this bundle');
        }

        return $this->resultOk(
            array(
                'comment'   => $keyRecord->getComment(),
                'updatedAt' => $keyRecord->getUpdatedAt()->format('c'),
            )
        );
    }

    /**
     * @param Project $project
     * @param $bundle
     * @param $key
     * @param $language
     * @param $catalog
     * @param $lastModification
     * @param $message
     * @return bool|int
     */
    public function updateMessageIfNewest(Project $project, $bundle, $key, $language, $catalog, $lastModification, $message)
    {
        if (!$bundle || !$language || !$key || !$lastModification || !$message) {
            return $this->exception('Validation exceptions, missing parameters');
        }
        /** @var Key $keyRecord */
        $keyRecord = $this->getKeyRepository()->findOneBy(array(
                'project'  => $project,
                'bundle'   => $bundle,
                'key'      => $key,
                'catalog'  => $catalog,
            )
        );

        if (!$keyRecord) {
            $keyRecord = new Key();
            $keyRecord->setProject($project);
            $keyRecord->setKey($key);
            $keyRecord->setBundle($bundle);
            $keyRecord->setCatalog($catalog);
            $this->em->persist($keyRecord);
            $this->em->flush();
        }
        /** @var Message $messageRecord */
        $messageRecord = $this->getMessageRepository()->findOneBy(array(
                'key'      => $keyRecord,
                'language' => $language,
            )
        );
        if (!$messageRecord) {
            $messageRecord = new Message();
            $messageRecord->setKey($keyRecord);
            $messageRecord->setLanguage($language);
        } else {
            if ($messageRecord->getUpdatedAt() >= $lastModification) {
                return $this->resultOk(array(
                        'updated'   => false,
                        'message'   => $messageRecord->getMessage(),
                        'updatedAt' => $messageRecord->getUpdatedAt()->format('c'),
                    )
                );
            }
        }
        $messageRecord->setMessage($message);
        $messageRecord->setUpdatedAt($lastModification);
        $this->em->persist($messageRecord);
        $this->em->flush($messageRecord);

        return $this->resultOk(array(
                'updated'   => true,
                'message'   => $messageRecord->getMessage(),
                'updatedAt' => $messageRecord->getUpdatedAt()->format('c'),
            )
        );
    }

    /**
     * @param Project $project
     * @param $bundle
     * @param $key
     * @param $catalog
     * @param $lastModification
     * @param $comment
     * @return bool|int
     */
    public function updateCommentIfNewest(Project $project, $bundle, $key, $catalog, $lastModification, $comment)
    {
        if (!$bundle || !$lastModification || !$comment || !$key) {
            return $this->exception('Validation exceptions, missing parameters');
        }
        $keyRecord = $this->getKeyRepository()->findOneBy(array(
                'project'  => $project,
                'bundle'   => $bundle,
                'key'      => $key,
                'catalog'  => $catalog,
            )
        );
        if (!$keyRecord instanceof Key) {
            $keyRecord = new Key();
            $keyRecord->setBundle($bundle);
            $keyRecord->setKey($key);
            $keyRecord->setCatalog($catalog);
        } else {
            if ($keyRecord->getUpdatedAt() >= $lastModification) {
                return $this->resultOk(array(
                        'updated'   => false,
                        'message'   => $keyRecord->getComment(),
                        'updatedAt' => $keyRecord->getUpdatedAt()->format('c'),

                    )
                );
            }
        }
        $keyRecord->setComment($comment);
        $keyRecord->setUpdatedAt($lastModification);
        $this->em->persist($keyRecord);
        $this->em->flush($keyRecord);

        return $this->resultOk(array(
                'updated'   => true,
                'message'   => $keyRecord->getComment(),
                'updatedAt' => $keyRecord->getUpdatedAt()->format('c'),
            )
        );
    }

    protected function blockSync(Project $project, $catalog, $language, $bundle, $data)
    {
        if (!$bundle || !$data || !$language || !$bundle || !$catalog) {
            return $this->exception('Validation exceptions, missing parameters');
        }
        $result = array();

        /** @var Message[] $localMessages */
        $localMessages = $this->translationsManager->getMessagesForBundleCatalogAndLocale($project, $bundle, $catalog, $language);
        foreach ($localMessages as $message) {
            $key = $message->getKey();
            $remoteMessage = isset($data[$key]) ? $data[$key] : null;

            if ($remoteMessage) {
                $remoteDate = new \DateTime($remoteMessage['updatedAt']);
                if ($message->getUpdatedAt() < $remoteDate) {
                    $message->setMessage($remoteMessage['message']);
                    $message->setUpdatedAt($remoteDate);
                    $this->em->persist($message);
                }
            }
            if ($message->getApproved()) {
                $result[] = $message->asArray();
            }
        }
        $this->em->flush();

        return $this->resultOk($result);
    }

    /**
     * @return TranslationRepository
     */
    protected function getTranslationRepository()
    {
        return $this->dm->getRepository('TranslationsBundle:Translation');
    }

    /**
     * @return ProjectInfoRepository
     */
    protected function getProjectInfoRepository()
    {
        return $this->dm->getRepository('TranslationsBundle:ProjectInfo');
    }

    /**
     * @return TranslatableDocumentRepository
     */
    protected function getTranslatableDocumentRepository()
    {
        return $this->dm->getRepository('TranslationsBundle:TranslatableDocument');
    }

    /**
     * $data[key][locale]
     * {
     *   message,
     *   updatedAt,
     *   bundle,
     *   fileName,
     * }.
     */
    protected function receiveKeys(Project $project, $catalog, $data)
    {
        if (!$project || !$catalog || !$data) {
            return $this->exception('Validation exceptions, missing parameters');
        }
        $result = array();

        /** @var Translation[] $messages */
        $messages = $this->getTranslationRepository()->findBy(
            array(
                'projectId' => $project->getId(),
                'catalog'   => $catalog,
            )
        );

        $this->debug("\tfound <info>%d</info> messages in translations", count($messages));

        foreach ($messages as $message) {
            $key = $message->getKey();
            $bundle = '';
            $translations = $message->getTranslations();
            $dirty = false;

            if (count($translations)) {
                foreach ($translations as $locale => $translation) {
                    if (isset($data[$key][$locale])) {
                        $current = $data[$key][$locale];
                        $updatedAt = new \DateTime($current['updatedAt']);

                        if ($message->getUpdatedAt()->sec < intval($updatedAt->format("U"))) {
                            $result[$key][$locale]              = $current['updatedAt'];
                            $translations[$locale]['message']   = $current['message'];
                            $translations[$locale]['updatedAt'] = $updatedAt;
                            if (isset($current['approved'])) {
                                $translations[$locale]['approved'] = $current['approved'];
                            }
                            $dirty = true;
                        }

                        $translations[$locale]['fileName']  = $current['fileName'];

                        if (!$bundle) {
                            if ($current['bundle']) {
                                $bundle = $current['bundle'];
                            } else {
                                preg_match('/\/(?<bundle>\w+Bundle)\//i', $current['fileName'], $matches);
                                if (isset($matches['bundle'])) {
                                    $bundle = $matches['bundle'];
                                }
                            }
                        }
                        unset($data[$key][$locale]);
                    }
                }
                if ($dirty) {
                    $message->setBundle($bundle);
                    $message->setTranslations($translations);

                    $this->dm->persist($message);
                }
            }
        }

        $this->debug("found <info>%d</info> keys in data", count($data));

        foreach ($data as $key => $dataLocale) {
            if (count($dataLocale)) {
                $this->debug("processing key <info>%s</info>", $key);

                $bundle = '';

                $translation = new Translation();
                $translation->setCatalog($catalog);
                $translation->setKey($key);
                $translation->setProjectId($project->getId());
                $translation->setBundle($bundle);

                $translations = array();

                foreach ($dataLocale as $locale => $message) {
                    $translations[$locale] = array(
                        'message'   => $message['message'],
                        'updatedAt' => new \DateTime($message['updatedAt']),
                        'approved'  => true,
                        'fileName'  => $message['fileName'],
                    );

                    if (!$bundle) {
                        if ($message['bundle']) {
                            $bundle = $message['bundle'];
                        } else {
                            preg_match('/\/(?<bundle>\w+Bundle)\//i', $current['fileName'], $matches);
                            if (isset($matches['bundle'])) {
                                $bundle = $matches['bundle'];
                            }
                        }
                    }
                }

                $translation->setBundle($bundle);
                $translation->setTranslations($translations);
                $this->dm->persist($translation);
            }
        }

        $this->dm->flush();

        // normalize
        $this->translationsManager->regenerateProjectInfo($project->getId());

        return $this->resultOk($result);
    }

    /**
     * $data[key][locale]
     * {
     *   message,
     *   updatedAt
     * }.
     */
    protected function sendKeys(Project $project, $catalog)
    {
        if (!$project || !$catalog) {
            return $this->exception("Validation exceptions, missing parameters project={$project->getId()}, catalog=$catalog");
        }

        /** @var Translation[] $messages */
        $messages = $this->getTranslationRepository()->findBy(
            array(
                'projectId' => $project->getId(),
                'catalog'   => $catalog,
                'deleted'   => false,
            ),
            array('key' => 'ASC')
        );

        $this->debug("found <info>%d</info> in translations", count($messages));

        $data    = array();
        $bundles = array();
        foreach ($messages as $message) {
            $key           = $message->getKey();
            $data[$key]    = $message->getTranslations();
            $bundles[$key] = $message->getBundle();
        }

        return $this->resultOk(
            array(
                'data'    => $data,
                'bundles' => $bundles,
            )
        );
    }

    /**
     * @return ProjectRepository
     */
    protected function getProjectRepository()
    {
        return $this->em->getRepository('TranslationsBundle:Project');
    }
}
