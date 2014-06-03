<?php

namespace JLaso\TranslationsBundle\Command;

/**
 * @author Joseluis Laso <jlaso@joseluislaso.es>
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
use JLaso\TranslationsBundle\Document\Repository\TranslatableDocumentRepository;
use JLaso\TranslationsBundle\Document\Repository\TranslationRepository;
use JLaso\TranslationsBundle\Document\TranslatableDocument;
use JLaso\TranslationsBundle\Document\Translation;
use JLaso\TranslationsBundle\Controller\RestController;
use JLaso\TranslationsBundle\Entity\Project;
use JLaso\TranslationsBundle\Entity\Repository\ProjectRepository;
use JLaso\TranslationsBundle\Entity\Repository\TranslationsLogRepository;
use JLaso\TranslationsBundle\Model\SocketClient;
use JLaso\TranslationsBundle\Service\Manager\TranslationsManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ServerWatchCommand extends ContainerAwareCommand
{
    const VERSION = "1.0";

    const CMD_REGISTER            = 'register';

    const ACK = 'ACK';
    const NO_ACK = 'NO-ACK';

    const BLOCK_SIZE = 1024;
    const DEFAULT_PORT = 10888;

    const TICKS = 1000;

    /** @var  EntityManager */
    protected static $em;
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
    protected static $showCommand = true;
    protected static $showExceptions = false;
    protected $sended = 0;
    protected $received = 0;
    protected $timeStart;

    /** @var  SocketClient[] */
    protected static $clients;

    protected static $connections;
    protected static $buffers;

    protected static $output;
    protected static $input;

    /**
     * configure the command that starts the server
     */
    protected function configure()
    {
        $this
            ->setName('jlaso:translations:server-watch-start')
            ->setDescription('Start the server to attend watch clients')
            ->addArgument('address', InputArgument::REQUIRED, 'server address')
            ->addArgument('port', InputArgument::OPTIONAL, 'port number where start server');
    }

    /**
     * Atomic send of a string trough the socket
     *
     * @param $msg
     *
     * @return int
     */
    protected static function send($msg)
    {
        $msg .= PHP_EOL;

        //return stream_socket_sendto($this->msgsock, $msg, strlen($msg));
    }

    /**
     * Reads the socket
     *
     * @param bool $compress
     *
     * @return int|string
     */
    protected function readSocket($compress = true)
    {
        $buffer = '';
        do{
            if((time() - $this->timeStart) > 100){
                //exit -1;
                die('??');
            }
            $buf = stream_socket_recvfrom($this->msgsock, 15 + 1024, PHP_BINARY_READ);
            if($buf === false){
                echo "socket_read() falló: razón: " . socket_strerror(socket_last_error($this->msgsock)) . "\n";
                return -2;
            }

            if(!trim($buf)){
                return '';
            }

            if(substr_count($buf, ":") < 3){
                var_dump($buf);
                die('error in format');
            }
            list($size, $block, $blocks)  = explode(":", $buf);
            $aux = substr($buf, 15);

            if($this->debug){
                echo sprintf("%d/%d blocks (start of block %s)\n", $block, $blocks, substr($aux, 0, 10));
            }

            if($size == strlen($aux)){
                $this->send(self::ACK);
            }else{
                $this->send(self::NO_ACK);
                die(sprintf('error in size (block %d of %d): informed %d vs %d read', $block, $blocks, $size, strlen($aux)));
            }

            $buffer .= $aux;

        }while($block < $blocks);

        $this->received += strlen($buffer);
        $size = $this->prettySize($this->received);
        echo "v " , $size, "  ";

        $result = $compress ? lzf_decompress($buffer) : $buffer;

        if($this->debug){
            $aux = json_decode($result, true);
            if(isset($aux['data'])){
                //var_dump($aux);
                echo sprintf("received %d keys\n", count($aux['data']));
            }
        }

        return $result;
    }

    /**
     * Body of the command
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        self::$connections = array();
        self::$buffers = array();
        self::$clients = array();

        set_time_limit(0);
        ob_implicit_flush();
        $this->timeStart = time();

        $container                 = $this->getContainer();
        self::$em                  = $container->get('doctrine.orm.default_entity_manager');
        $this->dm                  = $container->get('doctrine.odm.mongodb.document_manager');
        $this->translationsManager = $container->get('jlaso.translations_manager');

        self::$output = $output;
        self::$input = $input;

        $address = $input->getArgument('address');
        $port    = $input->getArgument('port') ?: self::DEFAULT_PORT;

        $output->writeln("Welcome to TranslationsApiBundle server for watch clientes v" . self::VERSION . ", listening on <info>$address:$port</info>");

//        try{
//            $connection = fsockopen($address, $port, $errno, $errtxt, 500);
//        }catch(\Exception $e){
//            die($e->getMessage());
//        }
//        if (!is_resource($connection))
//        {
//            //fclose($connection);
//            die(sprintf('some error [%d:%s] happens opening socket', $errno, $errtxt));
//        }

        $socket = stream_socket_server("tcp://$address:$port", $errno, $errstr);
        if (false === $socket) {
            $message = "Could not bind to tcp://$address:$port: $errstr";
            throw new \Exception(sprintf("[%d]: %s",$errno, $message));
        }
        stream_set_blocking($socket, 0);

        $tick = self::TICKS;
        $cycles = 0;

        $base = event_base_new();
        $event = event_new();
        event_set($event, $socket, EV_READ | EV_PERSIST, array("JLaso\\TranslationsBundle\\Command\\ServerWatchCommand","ev_accept") /* function($s,$f,$b){ ServerWatchCommand::ev_accept($s,$f,$b); }*/, $base);
        event_base_set($event, $base);
        event_add($event);
        $loop = 0;
        while(true){

            /** This is the main loop */
            event_base_loop($base, EVLOOP_ONCE | EVLOOP_NONBLOCK);

            /** wait a millisecond */
            usleep(1000);
            /** and for show that the process is alive prints a dot */
            if(!$tick--){
                //print "".($cycles++ % 9);
                $tick = self::TICKS;
            }
        }


        die();

        /**
         * ****************
         */

        $client_socks = array();
        do{

            $write = $except = NULL;
            $read_socks = $client_socks;
            $read_socks[] = $server;
            $num_changed_sockets = stream_select($read_socks, $write, $except, 0);

            if($num_changed_sockets){

                foreach($read_socks as $serv){

                    $socket = stream_socket_accept($serv);
                    $this->msgsock = $socket;
                    $buf = $this->readSocket(false);
                    //$buf = stream_socket_recvfrom($socket, self::BLOCK_SIZE); //stream_socket_recvfrom($sock, self::BLOCK_SIZE, STREAM_OOB);
                    print($buf);
                    //stream_socket_sendto($socket, $data);

                    if($buf){
                        $read = json_decode($buf, true);
                        var_dump($read);
                        /**
                         * fixed or not data that comes with the data received
                         */
                        $command   = isset($read['command']) ? $read['command'] : '';
                        $projectId = intval(isset($read['project_id']) ? $read['project_id'] : '');
                        $apiKey    = isset($read['auth.key']) ? $read['auth.key'] : '';
                        $apiSecret = isset($read['auth.secret']) ? $read['auth.secret'] : '';

                        if($this->showCommand){
                            $output->writeln($command);
                        }

                        switch($command){

                            case self::CMD_REGISTER:
                                if($this->showCommand){
                                    $output->writeln(sprintf('project %d',$projectId));
                                }
                                /** @var Project $project */
                                $project = $this->getProjectRepository()->find(intval($projectId));
                                if(!$project){
                                    $this->exception('wrong project');
                                    break;
                                }
                                if($this->showCommand){
                                    $output->writeln(sprintf('project api-key= %s, api-secret = %s', $project->getApiKey(),$project->getApiSecret()));
                                    $output->writeln(sprintf('received api-key= %s, api-secret = %s', $apiKey,$apiSecret));
                                }
                                if($project->getApiKey()!=$apiKey || $project->getApiSecret()!=$apiSecret){
                                    $this->exception('wrong credentials');
                                    break;
                                }
                                $uniqid = $this->getLongUniqid();
                                $this->clients[] = new SocketClient($uniqid, $socket);
                                $this->resultOk(array('uniqid'=>$uniqid));
                                break;

                        }
                    }

                }

            }else{

                usleep(1000);

                if(!$tick--){

                    $output->write('.');
                    $tick = self::TICKS;

//                    $lastVersion = $this->getTranslationLogRepository()->getLast();
//
//                    if(count($this->clients)){
//                        foreach($this->clients as $client)
//                        {
//
//                        }
//                    }


                    if(count($this->clients)){
                        foreach($this->clients as $client){

                            //$socket = stream_socket_accept($serv);
                            $socket = $client->getSocket();
                            $data = sprintf("Hi %s, now is %s", base64_encode(print_r($this->clients,true)), date('d-m-Y H:i:s'));
                            stream_socket_sendto($socket, $data);

                        }
                    }

                }
            }


        }while(true);

die;

        do {
            if(!$tick--){

                $output->write('.');
                $tick = self::TICKS;

                $lastVersion = $this->getTranslationLogRepository()->findLast();

                foreach($this->clients as $client)
                {

                }

            }
            //$output->write('X');
            $readSockets = array($sock);
            $writeSockets  = NULL;
            $exceptSockets = NULL;

            $num_changed_sockets = socket_select($readSockets, $writeSockets, $exceptSockets, 0);
//var_dump($num_changed_sockets);
            if (!$num_changed_sockets) {
                usleep(100);
                continue;
            }
            if (($this->msgsock = socket_accept($sock)) === false) {
                echo "socket_accept() error: " . socket_strerror(socket_last_error($sock)) . "\n";
                break;
            }
            /* Enviar instrucciones. */
            $this->send("Welcome to TranslationsApiBundle server for watch clientes v" . self::VERSION);

            do {

                if(!$tick--){

                    $output->write('.');
                    $tick = self::TICKS;

                }
                $output->write('X');

//                $waiting = socket_recvfrom($this->socket, $buf, 1, MSG_DONTWAIT);
//                var_dump($waiting);
//                if(!$waiting){
//                    continue;
//                }
                $buf = $this->readSocket();

                if($buf){
                    try{
                        $read     = json_decode($buf, true);
                        /**
                         * fixed or not data that comes with the data received
                         */
                        $command   = isset($read['command']) ? $read['command'] : '';
                        $projectId = intval(isset($read['project_id']) ? $read['project_id'] : '');
                        $apiKey    = isset($read['auth.key']) ? $read['auth.key'] : '';
                        $apiSecret = isset($read['auth.secret']) ? $read['auth.secret'] : '';

                        if($this->showCommand){
                            $output->writeln($command);
                        }

                        switch($command){

                            case self::CMD_REGISTER:
                                if($this->showCommand){
                                    $output->writeln(sprintf('project %d',$projectId));
                                }
                                /** @var Project $project */
                                $project = $this->getProjectRepository()->find(intval($projectId));
                                if(!$project){
                                    $this->exception('wrong project');
                                    break;
                                }
                                if($this->showCommand){
                                    $output->writeln(sprintf('project api-key= %s, api-secret = %s', $project->getApiKey(),$project->getApiSecret()));
                                    $output->writeln(sprintf('received api-key= %s, api-secret = %s', $apiKey,$apiSecret));
                                }
                                if($project->getApiKey()!=$apiKey || $project->getApiSecret()!=$apiSecret){
                                    $this->exception('wrong credentials');
                                    break;
                                }
                                $uniqid = $this->getLongUniqid();
                                $this->clients[] = $uniqid;
                                $this->resultOk(array('uniqid'=>$uniqid));
                                break;

//                            case self::CMD_CATALOG_INDEX:
//                                if($this->validateRequest($buf)){
//                                    $this->getCatalogIndex($this->project->getId());
//                                };
//                                break;
//
//                            case self::CMD_TRANSDOC_INDEX:
//                                if($this->validateRequest($buf)){
//                                    $this->getCatalogIndex($this->project->getId());
//                                };
//                                break;
//
//                            case self::CMD_TRANSDOC_SYNC:
//                                if($this->validateRequest($buf)){
//                                    $this->transDocSync($this->project->getId(), $bundle, $key, $locale, $fileName, $message, $lastModification);
//                                };
//                                break;
//
//                            case self::CMD_TRANSDOC_GET:
//                                if($this->validateRequest($buf)){
//                                    $this->getCatalogIndex($this->project->getId());
//                                };
//                                break;
//
//                            case self::CMD_UPLOAD_KEYS:
//                                if($this->validateRequest($buf)){
//                                    $data = isset($read['data']) ? $read['data'] : null;
//                                    $this->receiveKeys($this->project, $catalog, $data);
//                                }
//                                break;
//
//                            case self::CMD_DOWNLOAD_KEYS:
//                                if($this->validateRequest($buf)){
//                                    $data = isset($read['data']) ? $read['data'] : null;
//                                    $this->sendKeys($this->project, $catalog);
//                                }
//                                break;
//
//                            case self::CMD_SHUTDOWN:
//                                //socket_close($sock);
//                                $this->resultOk();
//                                sleep(1);
//                                socket_close($this->msgsock);
//                                exit;

                            default:
                                $this->exception(sprintf('command \'%s\' unknow', $command));
                                break;
                        }
                    }catch(\Exception $e){
                        $msg = $e->getCode() . ': ' . $e->getMessage() . ' in line ' . $e->getLine() . ' of file ' . $e->getFile();
                        $this->exception($msg);
                        if($e->getCode() == 0){
                            die('error grave: ' . $msg);
                        }
                    }
                }

            } while (true);

        } while (true);

    }


    static function ev_accept($socket, $flag, $base)
    {
        static $id = 0;

        $connection = stream_socket_accept($socket);
        stream_set_blocking($connection, 0);

        $id += 1;

        $buffer = event_buffer_new(
            $connection,
            array("JLaso\\TranslationsBundle\\Command\\ServerWatchCommand","ev_read") /*function($b,$id){ ServerWatchCommand::ev_read($b,$id); }*/,
            NULL,
            array("JLaso\\TranslationsBundle\\Command\\ServerWatchCommand","ev_error") /*function($b,$e,$id){ ServerWatchCommand::ev_error($b,$e,$id); }*/,
            $id
        );
        event_buffer_base_set($buffer, $base);
        event_buffer_timeout_set($buffer, 30, 30);
        event_buffer_watermark_set($buffer, EV_READ, 0, 0xffffff);
        event_buffer_priority_set($buffer, 10);
        event_buffer_enable($buffer, EV_READ | EV_PERSIST);

        // we need to save both buffer and connection outside
        self::$connections[$id] = $connection;
        self::$buffers[$id] = $buffer;
    }

    static function ev_error($buffer, $error, $id)
    {
        event_buffer_disable(self::$buffers[$id], EV_READ | EV_WRITE);
        event_buffer_free(self::$buffers[$id]);
        fclose(self::$connections[$id]);
        unset(self::$buffers[$id], self::$connections[$id]);
    }

    static function ev_read($buffer, $id)
    {
        $data = '';
        while ($read = event_buffer_read($buffer, 256)) {
            $data .= $read;
        }

        $info = json_decode($data, true);
        //var_dump($info); die;

        $command   = isset($info['command']) ? $info['command'] : '';
        $projectId = intval(isset($info['project_id']) ? $info['project_id'] : '');
        $apiKey    = isset($info['auth.key']) ? $info['auth.key'] : '';
        $apiSecret = isset($info['auth.secret']) ? $info['auth.secret'] : '';

        if(self::$showCommand){
            self::$output->writeln($command);
        }

        switch($command){

            case self::CMD_REGISTER:
                if(self::$showCommand){
                    self::$output->writeln(sprintf('project %d',$projectId));
                }
                /** @var Project $project */
                $project = self::getProjectRepository()->find(intval($projectId));
                if(!$project){
                    self::exception('wrong project');
                    break;
                }
                if(self::$showCommand){
                    self::$output->writeln(sprintf('project api-key= %s, api-secret = %s', $project->getApiKey(),$project->getApiSecret()));
                    self::$output->writeln(sprintf('received api-key= %s, api-secret = %s', $apiKey,$apiSecret));
                }
                if($project->getApiKey()!=$apiKey || $project->getApiSecret()!=$apiSecret){
                    self::exception('wrong credentials');
                    break;
                }
                $uniqid = self::getLongUniqid();
                self::$clients[] = $uniqid;
                self::resultOk(array('uniqid'=>$uniqid));
                break;

        }
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

    protected function getCatalogIndex($projectId)
    {
        $catalogs = $this->getTranslationRepository()->getCatalogs($projectId);

        return $this->resultOk(array('catalogs' => $catalogs));
    }

    protected function getTransDocIndex($projectId)
    {
        /** @var TranslatableDocument[] $documents */
        $documents = $this->getTranslatableDocumentRepository()->findBy(array('projectId' => intval($projectId)));

        $index = array();

        foreach($documents as $document){

            $files = $document->getFiles();

            foreach($files as $file){
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

        if(!$document){
            $document = new TranslatableDocument();
            $document->setProjectId($projectId);
            $document->setBundle($bundle);
            $document->setKey($key);
        }

        $files = $document->getFiles();

        $found = false;

        if($files){
            foreach($files as $file){

                if($file->getLocale() == $locale){
                    $found = true;
                    break;
                }

            }
        }else{
            $files = new ArrayCollection();
        }

        $updatedAt = $lastModification->getTimestamp();
        if($found){
            if($file->getUpdatedAt() < $updatedAt){
                $file->setMessage($message);
                $file->setUpdatedAt($updatedAt);
            }else{
                return $this->resultOk(
                    array(
                        'updated'   => false,
                        'message'   => $file->getMessage(),
                        'updatedAt' => $file->getUpdatedAt()->format('c'),
                    )
                );
            }
        }else{
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

    protected static function sendMessage($msg, $compress = false)
    {
        if($compress){
            $msg = lzf_compress($msg);
        }else{
            $msg .= PHP_EOL;
        }
// ME HE QUEDADO AQUI
        $len = strlen($msg);
        if(self::$debug){
            print "sending {$len} chars" . PHP_EOL;
        }

        $blocks = ceil($len / self::BLOCK_SIZE);
        for($i=0; $i<$blocks; $i++){

            $block = substr($msg, $i * self::BLOCK_SIZE,
                ($i == $blocks-1) ? $len - ($i-1) * self::BLOCK_SIZE : self::BLOCK_SIZE);
            $prefix = sprintf("%06d:%03d:%03d:", strlen($block), $i+1, $blocks);
            $aux =  $prefix . $block;
            if($this->debug){
                print sprintf("sending block %d from %d, prefix = %s\n", $i+1, $blocks, $prefix);
            }

            if(false === stream_socket_sendto($this->msgsock, $aux, strlen($aux))){
                die('error');
            };

//            do{
//                $read = stream_socket_recvfrom($this->msgsock, 10, PHP_NORMAL_READ);
//                //print $read;
//            }while(strpos($read, self::ACK) !== 0);
        }
        $this->sended += strlen($msg);
        $size = $this->prettySize($this->sended);
        echo '^ ' ,$size, "    ";

        $size = $this->prettySize($this->sended + $this->received);
        echo ', Total:', $size;

        echo str_repeat(chr(8), 80);

        return true;
    }

    protected static function resultOk($data = array())
    {
        $data['result'] = true;
        $result = json_encode($data) . PHP_EOL;
        if(self::$debug){
            print $result;
        }

        return self::sendMessage($result);
    }

    protected static function exception($reason)
    {
        $result = json_encode(array(
                'result' => false,
                'reason' => $reason,
            )
        ) . PHP_EOL;
        if(self::$showExceptions){
            print $result;
        }

        return self::send($result);
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
     * @return TranslationRepository
     */
    protected function getTranslationRepository()
    {
        return $this->dm->getRepository('TranslationsBundle:Translation');
    }

    /**
     * @return TranslatableDocumentRepository
     */
    protected function getTranslatableDocumentRepository()
    {
        return $this->dm->getRepository('TranslationsBundle:TranslatableDocument');
    }

    /**
     *
     * $data[key][locale]
     * {
     *   message,
     *   updatedAt,
     *   bundle,
     *   fileName,
     * }
     *
     */
    protected function receiveKeys(Project $project, $catalog, $data)
    {
        if(!$project || !$catalog|| !$data){
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

        if($this->debug){
            echo sprintf("found %d in translations\n", count($messages));
        }

        foreach($messages as $message){

            $key = $message->getKey();
            $bundle = '';
            $translations = $message->getTranslations();
            $dirty = false;

            if(count($translations)){

                foreach($translations as $locale=>$translation){

                    if(isset($data[$key][$locale])){

                        $current = $data[$key][$locale];
                        $updatedAt = new \DateTime($current['updatedAt']);

                        if($message->getUpdatedAt() < $updatedAt){

                            $result[$key][$locale]              = $current['updatedAt'];
                            $translations[$locale]['message']   = $current['message'];
                            $translations[$locale]['updatedAt'] = $updatedAt;
                            $dirty = true;

                        }

                        $translations[$locale]['fileName']  = $current['fileName'];

                        if(!$bundle){
                            if($current['bundle']){
                                $bundle = $current['bundle'];
                            }else{
                                preg_match('/\/(?<bundle>\w+Bundle)\//i', $current['fileName'], $matches);
                                if(isset($matches['bundle'])){
                                    $bundle = $matches['bundle'];
                                }
                            }
                        }
                        unset($data[$key][$locale]);

                    }
                }
                if($dirty){
                    $message->setBundle($bundle);
                    $message->setTranslations($translations);

                    $this->dm->persist($message);
                }
            }
        }

        if($this->debug){
            echo sprintf("found %d keys in data\n", count($data));
        }

        foreach($data as $key=>$dataLocale){

            if(count($dataLocale)){

                if($this->debug){
                    echo sprintf("processing key %s\n", $key);
                }

                $bundle = '';

                $translation = new Translation();
                $translation->setCatalog($catalog);
                $translation->setKey($key);
                $translation->setProjectId($project->getId());
                $translation->setBundle();

                $translations = array();

                foreach($dataLocale as $locale=>$message){

                    $translations[$locale] = array(
                        'message'   => $message['message'],
                        'updatedAt' => new \DateTime($message['updatedAt']),
                        'approved'  => true,
                        'fileName'  => $message['fileName'],
                    );

                    if(!$bundle){
                        if($message['bundle']){
                            $bundle = $message['bundle'];
                        }else{
                            preg_match('/\/(?<bundle>\w+Bundle)\//i', $current['fileName'], $matches);
                            if(isset($matches['bundle'])){
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

        // normalize ?

        return $this->resultOk($result);
    }

    /**
     *
     * $data[key][locale]
     * {
     *   message,
     *   updatedAt
     * }
     *
     */
    protected function sendKeys(Project $project, $catalog)
    {
        if(!$project || !$catalog){
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

        if($this->debug){
            echo sprintf("found %d in translations\n", count($messages));
        }

        $data    = array();
        $bundles = array();
        foreach($messages as $message){
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
    protected static function getProjectRepository()
    {
        return self::$em->getRepository('TranslationsBundle:Project');
    }

    /**
     * @return TranslationLogRepository
     */
    protected function getTranslationLogRepository()
    {
        return $this->em->getRepository('TranslationsBundle:TranslationLog');
    }

    protected static function getLongUniqid()
    {
        return uniqid(rand(10000,99999)) . base64_encode(date('U')) . base64_encode(json_encode(array(uniqid()=>uniqid())));
    }

}
