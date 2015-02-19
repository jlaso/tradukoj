<?php

namespace JLaso\TranslationsBundle\Command;

use Doctrine\ORM\EntityManager;
use JLaso\TranslationsBundle\Controller\RestController;
use JLaso\TranslationsBundle\Entity\Key;
use JLaso\TranslationsBundle\Entity\Message;
use JLaso\TranslationsBundle\Entity\Project;
use JLaso\TranslationsBundle\Entity\Repository\KeyRepository;
use JLaso\TranslationsBundle\Entity\Repository\MessageRepository;
use JLaso\TranslationsBundle\Entity\Repository\ProjectRepository;
use JLaso\TranslationsBundle\Service\Manager\TranslationsManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ServerCommand extends ContainerAwareCommand
{
    const CMD_SHUTDOWN            = 'shutdown';
    const CMD_PROJECTS            = 'project-index';
    const CMD_KEY_INDEX           = 'key-index';
    const CMD_BUNDLE_INDEX        = 'bundle-index';
    const CMD_TRANSLATION_DETAILS = 'translation-details';
    const CMD_TRANSLATIONS        = 'translations';
    const CMD_GET_COMMENT         = 'get-comment';
    const CMD_PUT_MESSAGE         = 'put-message';
    const CMD_UPDATE_MESSAGE      = 'update-message-if-newest';
    CONST CMD_UPDATE_COMMENT      = 'update-comment-if-newest';
    const CMD_BLOCK_SYNC          = 'block-sync';

    /** @var  EntityManager */
    protected $em;
    protected $socket;
    protected $msgsock;
    /** @var  Project */
    protected $project;
    /** @var  TranslationsManager */
    protected $translationsManager;

    protected $debug = false;
    protected $showCommand = false;
    protected $showExceptions = false;
    protected $sended = 0;
    protected $received = 0;

    protected function configure()
    {
        $this
            ->setName('jlaso:translations:server-start')
            ->setDescription('Start the server')
            ->addArgument('port', InputArgument::REQUIRED, 'port number where start server');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        set_time_limit(0);

        /* Activar el volcado de salida implícito, así veremos lo que estamo obteniendo
         * mientras llega. */
        ob_implicit_flush();

        $container                 = $this->getContainer();
        $this->em                  = $container->get('doctrine.orm.default_entity_manager');
        $this->translationsManager = $container->get('jlaso.translations_manager');

        $address = '127.0.0.1';
        $port = $input->getArgument('port');

        if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
            echo "socket_create() falló: razón: " . socket_strerror(socket_last_error()) . "\n";
        }

        if (socket_bind($sock, $address, $port) === false) {
            echo "socket_bind() falló: razón: " . socket_strerror(socket_last_error($sock)) . "\n";
        }

        if (socket_listen($sock, 5) === false) {
            echo "socket_listen() falló: razón: " . socket_strerror(socket_last_error($sock)) . "\n";
        }

        do {
            if (($this->msgsock = socket_accept($sock)) === false) {
                echo "socket_accept() falló: razón: " . socket_strerror(socket_last_error($sock)) . "\n";
                break;
            }
            /* Send instructions */
            $msg = "Welcome to TranslationsApiBundle v1.0." . PHP_EOL;
            socket_write($this->msgsock, $msg, strlen($msg));

            do {
                if (false === ($buf = socket_read($this->msgsock, 2048, PHP_NORMAL_READ))) {
                    echo "socket_read() falló: razón: " . socket_strerror(socket_last_error($this->msgsock)) . "\n";
                    break 2;
                }
                if (!$buf = trim($buf)) {
                    continue;
                }

                var_dump($buf, json_decode($buf, true));


                $this->received += strlen($buf);
                $size = $this->prettySize($this->received);
                echo "v " , $size, "  ";

                try{
                    $read     = json_decode($buf, true);
                    var_dump($read); die;
                    $command  = isset($read['command']) ? $read['command'] : '';
                    $bundle   = isset($read['bundle']) ? $read['bundle'] : '';
                    $key      = isset($read['key']) ? $read['key'] : '';
                    $language = isset($read['language']) ? $read['language'] : '';
                    $catalog  = isset($read['catalog']) ? $read['catalog'] : RestController::DEFAULT_CATALOG;
                    $message  = isset($read['message']) ? $read['message'] : '';
                    $comment  = isset($read['comment']) ? $read['comment'] : '';
                    $lastModification = isset($read['last_modification']) ? new \DateTime($read['last_modification']) : null;

                    if($this->showCommand){
                        $output->writeln($command);
                    }

                    switch($command){

                        case self::CMD_PROJECTS:
                            $projects = $this->getProjectIndex();
                            $this->resultOk($projects);
                            break;

                        case self::CMD_KEY_INDEX:
                            if($this->validateRequest($buf)){
                                $keyRepository = $this->getKeyRepository();
                                $keys          = $keyRepository->findAllKeysForProjectAndBundle($this->project, $bundle);
                                foreach($keys as $key){
                                    $keysResult[] = $key->getKey();
                                }
                                $this->resultOk(array('keys' => $keysResult));
                            };
                            break;

                        case self::CMD_BUNDLE_INDEX:
                            if($this->validateRequest($buf)){
                                $keyRepository = $this->getKeyRepository();
                                $bundles       = $keyRepository->findAllBundlesForProject($this->project);
                                $this->resultOk(array('bundles' => $bundles));
                            };
                            break;

                        case self::CMD_TRANSLATION_DETAILS:
                            if($this->validateRequest($buf)){
                                $this->getTranslationDetails($this->project, $bundle, $key, $language, $catalog);
                            };
                            break;

                        case self::CMD_TRANSLATIONS:
                            if($this->validateRequest($buf)){
                                $this->getTranslations($this->project, $bundle, $key, $catalog);
                            };
                            break;

                        case self::CMD_GET_COMMENT:
                            if($this->validateRequest($buf)){
                                $this->getComment($this->project, $bundle, $key, $catalog);
                            }
                            break;

                        case self::CMD_PUT_MESSAGE:
                            if($this->validateRequest($buf)){
                                $this->putMessage($this->project, $bundle, $key, $language, $catalog, $message);
                            }
                            break;

                        case self::CMD_UPDATE_COMMENT:
                            if($this->validateRequest($buf)){
                                $this->updateCommentIfNewest($this->project, $bundle, $key, $catalog, $lastModification, $comment);
                            }
                            break;

                        case self::CMD_UPDATE_MESSAGE:
                            if($this->validateRequest($buf)){
                                $this->updateMessageIfNewest($this->project, $bundle, $key, $language, $catalog, $lastModification, $message);
                            }
                            break;

                        case self::CMD_BLOCK_SYNC:
                            if($this->validateRequest($buf)){
                                $data = isset($read['data']) ? $read['data'] : null;
                                $this->blockSync($this->project, $catalog, $language, $bundle, $data);
                            }
                            break;

                        case self::CMD_SHUTDOWN:
                            socket_close($this->msgsock);
                            socket_close($sock);
                            exit;
                            break 2;

                        default:
                            $this->exception('command unknow');
                            break;
                    }
                }catch(\Exception $e){
                    $this->exception($e->getCode() . ': ' . $e->getMessage() . ' in line ' . $e->getLine() . ' of file ' . $e->getFile());
                    if($e->getCode() == 0){
                        die('error grave');
                    }
                }

            } while (true);

        } while (true);

    }

    protected function getProjectIndex()
    {
        /** @var Project[] $projects */
        $projects = $this->getProjectRepository()->findAll();
        $result = array();
        foreach($projects as $project){
            $result[] = array(
                'id'         => $project->getId(),
                'name'       => $project->getName(),
                'created_at' => $project->getCreatedAt()->format('c'),
                'locales'    => $project->getManagedLocales(),
            );
        }

        return $result;
    }

    protected function send($buffer)
    {
        $debug = substr($buffer,0,60);
        $buffer = lzf_compress($buffer);

        $this->sended += strlen($buffer);
        $size = $this->prettySize($this->sended);
        echo '^ ' ,$size, "    ";

        $size = $this->prettySize($this->sended + $this->received);
        echo ', Total:', $size, ' ', $debug;

        echo str_repeat(chr(8), 80);

        return socket_write($this->msgsock, $buffer, strlen($buffer));
    }

    protected function resultOk($data = array())
    {
        $data['result'] = true;
        $result = json_encode($data) . PHP_EOL;
        if($this->debug){
            print $result;
        }

        return $this->send($result);
    }

    protected function exception($reason)
    {
        $result = json_encode(array(
                'result' => false,
                'reason' => $reason,
            )
        ) . PHP_EOL;
        if($this->showExceptions){
            print $result;
        }

        return $this->send($result);
    }

    protected function prettySize($size)
    {
        $kb = 1024;
        $mb = $kb * 1024;
        $gb = $mb * 1024;

        $result = "";
        if($size > $gb){
            $result .= intval($size/$gb) . 'Gb ';
            $size -= $gb * intval($size/$gb);
        }

        if($size > $mb){
            $result .= intval($size/$mb, 3) . 'Mb ';
            $size -= $mb * intval($size/$mb);
        }

        if($size > $kb){
            $result .= intval($size/$kb) . 'Kb ';
            $size -= $kb * intval($size/$kb);
        }

        //$result .= $size . 'b ';

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
        if(!$projectId){
            $this->exception('invalid credentials');

            return false;
        }
        $key           = $data['auth.key'];
        $secret        = $data['auth.secret'];
        $this->project = $this->getProjectRepository()->find($projectId);
        if(!$this->project){
            $this->exception('invalid project');

            return false;
        }

        return ($key == $this->project->getApiKey()) && ($secret == $this->project->getApiSecret());

    }

    /**
     * Devuelve los detalles de un mensaje en concreto
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

        if(!$keyRecord){
            return $this->exception('No key found in this bundle');
        }
        /** @var Message $message */
        $message = $this->getMessageRepository()->findOneBy(array(
                'key'      => $keyRecord,
                'language' => $language,
            )
        );
        if(!$message){
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
     * Devuelve los mensajes de una key
     *
     */
    public function getTranslations(Project $project, $bundle, $key, $catalog)
    {
        if(!$project || !$bundle || !$key || !$catalog){
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

        if(!$keyRecord){
            return $this->exception('No key found in this bundle');
        }
        $messages = array();
        foreach($keyRecord->getMessages() as $message){
            $messages[$message->getLanguage()] = array(
                'message'           => $message->getMessage(),
                'last_modification' => $message->getUpdatedAt()->format('U'),
            );
        }

        return $this->resultOk(array('messages' => $messages));
    }

    /**
     * Devuelve el comentario de una key
     */
    protected function getComment(Project $project, $bundle, $key, $catalog)
    {
        if(!$project || !$bundle || !$key || !$catalog){
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

        if(!$keyRecord){
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
     */
    public function putMessage(Project $project, $bundle, $key, $language, $catalog, $message)
    {
        // message puede estar en blanco
        if(!$project || !$bundle || !$key || !$language || !$catalog){
            return $this->exception('missing parameters');
        }
        $this->insertOrUpdateMessage($project, $bundle, $catalog, $key, $language, $message);

        return $this->resultOk();
    }

    /**
     */
    public function updateMessageIfNewest(Project $project, $bundle, $key, $language, $catalog, $lastModification, $message)
    {
        if(!$bundle || !$language || !$key || !$lastModification || !$message){
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

        if(!$keyRecord){
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
        if(!$messageRecord){
            $messageRecord = new Message();
            $messageRecord->setKey($keyRecord);
            $messageRecord->setLanguage($language);
        }else{
            if($messageRecord->getUpdatedAt() >= $lastModification){
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
     */
    public function updateCommentIfNewest(Project $project, $bundle, $key, $catalog, $lastModification, $comment)
    {
        if(!$bundle || !$lastModification || !$comment || !$key){
            return $this->exception('Validation exceptions, missing parameters');
        }
        $keyRecord = $this->getKeyRepository()->findOneBy(array(
                'project'  => $project,
                'bundle'   => $bundle,
                'key'      => $key,
                'catalog'  => $catalog,
            )
        );
        if(!$keyRecord instanceof Key){
            $keyRecord = new Key();
            $keyRecord->setBundle($bundle);
            $keyRecord->setKey($key);
            $keyRecord->setCatalog($catalog);
        }else{
            if($keyRecord->getUpdatedAt() >= $lastModification){
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
        if(!$bundle || !$data || !$language || !$bundle || !$catalog){
            return $this->exception('Validation exceptions, missing parameters');
        }
        $result = array();

        /** @var Message[] $localMessages */
        $localMessages = $this->translationsManager->getMessagesForBundleCatalogAndLocale($project, $bundle, $catalog, $language);
        foreach($localMessages as $message){

            $key = $message->getKey();
            $remoteMessage = isset($data[$key]) ? $data[$key] : null;

            if($remoteMessage){
                $remoteDate = new \DateTime($remoteMessage['updatedAt']);
                if($message->getUpdatedAt() < $remoteDate){
                    $message->setMessage($remoteMessage['message']);
                    $message->setUpdatedAt($remoteDate);
                    $this->em->persist($message);
                }
            }
            if($message->getApproved()){
                $result[] = $message->asArray();
            }
        }
        $this->em->flush();

        return $this->resultOk($result);
    }

    /**
     * @return ProjectRepository
     */
    protected function getProjectRepository()
    {
        return $this->em->getRepository('TranslationsBundle:Project');
    }

    /**
     * @return MessageRepository
     */
    private function getMessageRepository()
    {
        return $this->em->getRepository('TranslationsBundle:Message');
    }

    /**
     * @return KeyRepository
     */
    private function getKeyRepository()
    {
        return $this->em->getRepository('TranslationsBundle:Key');
    }

    /**
     * @param Project $project
     * @param string  $bundleName
     * @param string  $catalog
     * @param string  $key
     * @param string  $language
     * @param string  $msg
     *
     * @return Message
     */
    protected function insertOrUpdateMessage(Project $project, $bundleName, $catalog, $key, $language, $msg)
    {
        /** @var Key $keyRecord */
        $keyRecord = $this->getKeyRepository()->findOneBy(array(
                'project'  => $project,
                'bundle'   => $bundleName,
                'catalog'  => $catalog,
                'key'      => $key,
            )
        );

        if(!$keyRecord){
            $keyRecord = new Key();
            $keyRecord->setProject($project);
            $keyRecord->setBundle($bundleName);
            $keyRecord->setCatalog($catalog);
            $keyRecord->setKey($key);
            $this->em->persist($keyRecord);
            $this->em->flush();
        }
        /** @var Message $message */
        $message = $this->getMessageRepository()->findOneBy(array(
                'key'      => $keyRecord,
                'language' => $language,
            )
        );
        if(!$message){
            $message = new Message();
            $message->setKey($keyRecord);
            $message->setLanguage($language);
        }
        $message->setMessage($msg);
        $message->setUpdatedAt();
        $this->em->persist($message);
        $this->em->flush();

        return $message;
    }

}

/**
 *
 *
 * {"command":"key index", "key":"1234", "secret":1234, "project_id":1}
 *
 * {"command":"bundle index", "key":"1234", "secret":1234, "project_id":1}
 *
 *
 *
 */

