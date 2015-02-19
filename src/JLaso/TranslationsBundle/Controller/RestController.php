<?php

namespace JLaso\TranslationsBundle\Controller;

use Doctrine\ORM\EntityManager;
use JLaso\TranslationsBundle\Entity\Key;
use JLaso\TranslationsBundle\Entity\Message;
use JLaso\TranslationsBundle\Entity\Project;
use JLaso\TranslationsBundle\Entity\Repository\KeyRepository;
use JLaso\TranslationsBundle\Entity\Repository\MessageRepository;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Class RestController
 * @package JLaso\TranslationsBundle\Controller
 * @Route("/api")
 */
class RestController extends Controller
{
    /** @var EntityManager */
    protected $em;

    const DEFAULT_CATALOG = "messages";
    const MIN_PORT = 10000;
    const MAX_PORT = 10050;

    /**
     *
     */
    protected function init()
    {
        $this->em = $this->container->get('doctrine.orm.default_entity_manager');
    }

    protected function validateRequest(Request $request, Project $project)
    {
        $content = $request->getContent();
        $params  = json_decode($content, true);

        if( !isset($params['key'])  || !isset($params['secret']) ){
            return false;
        }

        return ($params['key'] == $project->getApiKey()) && ($params['secret'] == $project->getApiSecret());

    }

    /**
     * @Route("/")
     */
    public function indexAction()
    {
        return $this->render('TranslationsBundle:Rest:index.html.twig');
    }

    /**
     * @param string $message
     *
     * @return mixed
     */
    protected function exception($message)
    {
        $result = array(
            'result' => false,
            'reason' => $message,
        );

        return $this->printResult($result);
    }

    /**
     * @param $data
     *
     * @return mixed
     */
    protected function printResult($data)
    {
        header('Content-type: application/json');
        print json_encode($data);
        exit;
    }

    /**
     * @param array  $data
     *
     * @return mixed
     */
    protected function resultOk($data = array())
    {
        return $this->printResult(
            array_merge(
                array(
                    'result' => true,
                ),
                $data
            )
        );
    }

    /**
     * @Route("/ping")
     * @Method("GET")
     */
    public function pingAction()
    {
        die('pong');
    }

    /**
     * Devuelve el indice de las keys de ese bundle
     *
     * @Route("/key/index/{projectId}/{bundle}")
     * @Method("POST")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function getKeyIndex(Project $project, $bundle = false)
    {
        $this->init();
        $keyRepository = $this->getKeyRepository();
        //$bundles       = $keyRepository->findAllBundlesForProject($project);
        $keys          = $keyRepository->findAllKeysForProjectAndBundle($project, $bundle);
        $keysResult    = array();
        foreach($keys as $key){
            $keysResult[$key->getKey()] = $key->getKey();
        }

        return $this->resultOk(array('keys' => $keysResult));
    }

    /**
     * Crea un socket de comunicacion
     *
     * @Route("/create-socket/{projectId}")
     * @Method("POST")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function createSocketAction(Request $request, Project $project)
    {
        //@TODO: Ver si es una brecha el ir probando con diferentes projects y saturar al servidor, sería mejor no utilizar ParamConverter y
        // no permitir más de x peticiones por segundo de la misma IP
        $this->init();
        //ob_implicit_flush();

        if($this->validateRequest($request, $project)){
            $host = php_uname('n'); //
            if(strpos($host, '.local') !== false){
                $host = '127.0.0.1';
            }else{
                $host = gethostbyname($host);
            }
            $found = false;
            $errno = null;
            $errtxt = '';
            for ($port = self::MIN_PORT; $port < self::MAX_PORT; $port++)
            {
                $connection = @fsockopen($host, $port, $errno, $errtxt, 500);
                if (is_resource($connection))
                {
                    fclose($connection);
                }else{
                    $found = true;
                    break;
                }
            }
            if($found){

                /*
                $app = new Application($this->get('kernel'));
                $app->run(null, new NullOutput());
                */

                $srcDir = dirname($this->get('kernel')->getRootDir());
                $cmd = array(
                    $srcDir . '/app/console',
                    'jlaso:translations:server-mongo-start',
                    $host,
                    $port,
                );

                if(function_exists('pcntl_exec')){
                    pcntl_exec('php', $cmd);
                }else{
                    $cmd = "php " . implode(" ",$cmd). " >/dev/null 2>/dev/null &";
                    exec($cmd);
                }

                return $this->resultOk(array(
                        'host' => $host,
                        'port' => $port,
                        'cmd'  => $cmd,
                    )
                );
            }

            return $this->exception(sprintf('unable to start socket server on %s, port %d, %d:%s', $host, $port, $errno, $errtxt));

        }

        return $this->exception('unable to start socket: bad credentials');
    }

    /**
     * Devuelve el indice de los bundles de ese proyecto
     *
     * @Route("/bundle/index/{projectId}")
     * @Method("POST")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function getBundleIndex(Request $request, Project $project)
    {
        try{
            $this->init();
            if(!$this->validateRequest($request, $project)){
                return $this->exception('error_messages.invalid_credentials');
            };
            $keyRepository = $this->getKeyRepository();
            $bundles       = $keyRepository->findAllBundlesForProject($project);
        }catch(\Exception $e){
            return $this->exception($e->getMessage());
        }

        return $this->resultOk(array('bundles' => $bundles));
    }

    /**
     * Devuelve los detalles de un mensaje en concreto
     *
     * @Route("/translation/details/{projectId}/{bundle}/{key}/{language}/{catalog}")
     * @Method("POST")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function getTranslationDetails(Project $project, $bundle, $key, $language, $catalog = self::DEFAULT_CATALOG)
    {
        $this->init();
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
            )
        );
    }

    /**
     * Devuelve los mensajes de una key
     *
     * @Route("/translations/{projectId}/{bundle}/{key}/{catalog}")
     * @Method("POST")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function getTranslations(Project $project, $bundle, $key, $catalog = self::DEFAULT_CATALOG)
    {
        $this->init();
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
     * @Route("/get-messages/{projectId}/{bundle}/{locale}/{catalog}")
     * @Method("POST")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function getMessagesAction(Project $project, $bundle, $locale, $catalog = self::DEFAULT_CATALOG)
    {
        $this->init();

        $data = $this->getMessageRepository()
                     ->findAllMessagesOfProjectBundleCatalogAndLocale($project, $bundle, $catalog, $locale);

        $result = array();
        foreach($data as $item){
            /*$result[$item->getKey()->getKey()] = array(
                'message'    => $item->getMessage(),
                'updated_at' => $item->getUpdatedAt()->format('c'),
            );*/
            $result[$item->getKey()->getKey()] = $item->getMessage();
        }

        return $this->resultOk(
            array(
                'project'   => $project->getId(),
                'bundle'    => $bundle,
                'catalog'   => $catalog,
                'messages'  => $result,
            )
        );
    }

    /**
     * @Route("/get-comments/{projectId}/{bundle}/{catalog}")
     * @Method("POST")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function getCommentsAction(Project $project, $bundle, $catalog = self::DEFAULT_CATALOG)
    {
        $this->init();

        $data = $this->getKeyRepository()
                     ->findAllKeysForProjectBundleAndCatalog($project, $bundle, $catalog);

        $result = array();
        foreach($data as $item){
//            $result[$item->getKey()] = array(
//                'comment'    => $item->getComment(),
//                'updated_at' => $item->getUpdatedAt()->format('c'),
//            );
            $result[$item->getKey()] = $item->getComment();
        }

        return $this->resultOk(
            array(
                'project'   => $project->getId(),
                'bundle'    => $bundle,
                'catalog'   => $catalog,
                'comments'  => $result,
            )
        );
    }

    /**
     * @Route("/put-messages/{projectId}/{bundle}/{locale}/{catalog}")
     * @Method("POST")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function putMessagesAction(Request $request, Project $project, $bundle, $locale, $catalog = self::DEFAULT_CATALOG)
    {
        $this->init();
        $requestContent = json_decode($request->getContent(), true);
        $messages       = $requestContent['messages'];
        foreach($messages as $key=>$message)
        {
            $this->insertOrUpdateMessage($project, $bundle, $catalog, $key, $locale, $message);
        }

        return $this->resultOk();
    }

    /**
     * @Route("/put-comments/{projectId}/{bundle}/{catalog}")
     * @Method("POST")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function putCommentsAction(Request $request, Project $project, $bundle, $catalog = self::DEFAULT_CATALOG)
    {
        $this->init();
        $requestContent = json_decode($request->getContent(), true);
        $comments       = $requestContent['comments'];
        foreach($comments as $key=>$comment)
        {
            $this->insertOrUpdateComment($project, $bundle, $catalog, $key, $comment);
        }

        return $this->resultOk();
    }

    /**
     * Devuelve el mensaje de una key
     *
     * @Route("/translation/{projectId}/{bundle}/{key}/{locale}/{catalog}")
     * @Method("POST")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function getTranslation(Project $project, $bundle, $key, $locale, $catalog = self::DEFAULT_CATALOG)
    {
        $this->init();
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
        /** @var Message $messageRecord */
        $messageRecord = $this->getMessageRepository()->findOneBy(array(
                'key'      => $keyRecord,
                'language' => $locale,
            )
        );
        if(!$messageRecord){
            return $this->exception('No message found');
        }

        return $this->resultOk(
            array(
                'message'   => $messageRecord->getMessage(),
                'updatedAt' => $keyRecord->getUpdatedAt()->format('c'),
            )
        );
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
        $key = urldecode($key);
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

    /**
     * @param Project $project
     * @param string  $bundleName
     * @param string  $catalog
     * @param string  $key
     * @param string  $comment
     *
     * @return Message
     */
    protected function insertOrUpdateComment(Project $project, $bundleName, $catalog, $key, $comment)
    {
        $key = urldecode($key);
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
        }
        $keyRecord->setComment($comment);
        $this->em->persist($keyRecord);
        $this->em->flush();

        return $keyRecord;
    }

    /**
     * Devuelve el comentario de una key
     *
     * @Route("/get/comment/{projectId}/{bundle}/{key}/{catalog}")
     * @Method("POST")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function getCommentAction(Project $project, $bundle, $key, $catalog = self::DEFAULT_CATALOG)
    {
        $this->init();
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

        return $this->resultOk(
            array(
                'comment'   => $keyRecord->getComment(),
                'updatedAt' => $keyRecord->getUpdatedAt()->format('c'),
            )
        );
    }

    /**
     * @Route("/put/message/{projectId}/{bundle}/{key}/{language}/{catalog}")
     * @Method("POST")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function putMessage(Request $request, $project, $bundle, $key, $language, $catalog = self::DEFAULT_CATALOG)
    {
        $this->init();
        $key = urldecode($key);
        $param   = json_decode($request->getContent(), true);
        $message = $param['message'];
        $this->insertOrUpdateMessage($project, $bundle, $catalog, $key, $language, $message);

        return $this->resultOk();
    }


    /**
     * @Route("/update/message/if-newest/{projectId}/{bundle}/{key}/{language}/{catalog}")
     * @Method("POST")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function updateMessageIfNewest( Request $request, $project, $bundle, $key, $language, $catalog = self::DEFAULT_CATALOG)
    {
        $this->init();
        $key = urldecode($key);
        $param = json_decode($request->getContent(), true);
        $lastModification = new \DateTime($param['last_modification']);
        $message = $param['message'];
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
        $this->em->flush();

        return $this->resultOk(array('updated' => true));
    }

    /**
     * @Route("/update/comment/if-newest/{projectId}/{bundle}/{key}/{catalog}")
     * @Method("POST")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function updateCommentIfNewest(Request $request, $project, $bundle, $key, $catalog = self::DEFAULT_CATALOG)
    {
        $this->init();
        $key = urldecode($key);

        $param = json_decode($request->getContent(), true);
        $lastModification = new \DateTime($param['last_modification']);
        $comment = $param['comment'];
        if(!$bundle || !$lastModification || !$comment || !$key){
            return $this->exception('Validation exceptions, missing parameters');
        }
        $keyRecord = $this->getKeyRepository()->findOneBy(array(
                'project'  => $project,
                'bundle'   => $bundle,
                'key'      => $request->get('key'),
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
        $this->em->flush();

        return $this->resultOk(array(
                'updated'   => true,
                'message'   => $keyRecord->getComment(),
                'updatedAt' => $keyRecord->getUpdatedAt()->format('c'),
            )
        );
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
}
