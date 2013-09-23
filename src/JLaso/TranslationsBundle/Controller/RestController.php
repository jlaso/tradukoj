<?php

namespace JLaso\TranslationsBundle\Controller;

use Doctrine\ORM\EntityManager;
use JLaso\TranslationsBundle\Entity\Key;
use JLaso\TranslationsBundle\Entity\Message;
use JLaso\TranslationsBundle\Entity\Project;
use JLaso\TranslationsBundle\Entity\Repository\KeyRepository;
use JLaso\TranslationsBundle\Entity\Repository\MessageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;

/**
 * Class RestController
 * @package JLaso\TranslationsBundle\Controller
 * @Route("/api")
 */
class RestController extends Controller
{
    /** @var EntityManager */
    protected $em;

    /**
     *
     */
    protected function init()
    {
        $this->em = $this->container->get('doctrine.orm.default_entity_manager');
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
        $bundles       = $keyRepository->findAllBundlesForProject($project);
        $keys          = $keyRepository->findAllKeysForProjectAndBundle($project, $bundle);
        $keysResult    = array();
        foreach($keys as $key){
            $keysResult[$key->getKey()] = $key->getKey();
        }

        return $this->resultOk(array('keys' => $keysResult));
    }

    /**
     * Devuelve el indice de los bundles de ese proyecto
     *
     * @Route("/bundle/index/{projectId}")
     * @Method("POST")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function getBundleIndex(Project $project)
    {
        try{
            $this->init();
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
     * @Route("/translation/details/{projectId}/{bundle}/{key}/{language}")
     * @Method("POST")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function getTranslationDetails(Project $project, $bundle, $key, $language)
    {
        $this->init();

        /** @var Key $keyRecord */
        $keyRecord = $this->getKeyRepository()->findOneBy(array(
                'project'  => $project,
                'bundle'   => $bundle,
                'key'      => $key,
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
     * @Route("/translations/{projectId}/{bundle}/{key}")
     * @Method("POST")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function getTranslations(Project $project, $bundle, $key)
    {
        $this->init();

        /** @var Key $keyRecord */
        $keyRecord = $this->getKeyRepository()->findOneBy(array(
                'project'  => $project,
                'bundle'   => $bundle,
                'key'      => $key,
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
     * @Route("/get-messages/{projectId}/{bundle}/{catalog}/{locale}")
     * @Method("POST")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function getMessagesAction(Project $project, $bundle, $catalog, $locale)
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
    public function getCommentsAction(Project $project, $bundle, $catalog)
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
     * @Route("/put-messages/{projectId}/{bundle}/{catalog}/{locale}")
     * @Method("POST")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function putMessagesAction(Project $project, $bundle, $catalog, $locale, Request $request)
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
    public function putCommentsAction(Project $project, $bundle, $catalog, Request $request)
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
     * @Route("/translation/{projectId}/{bundle}/{key}/{locale}")
     * @Method("POST")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function getTranslation(Project $project, $bundle, $key, $locale)
    {
        $this->init();

        /** @var Key $keyRecord */
        $keyRecord = $this->getKeyRepository()->findOneBy(array(
                'project'  => $project,
                'bundle'   => $bundle,
                'key'      => $key,
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
     * @Route("/get/comment/{projectId}/{bundle}/{key}")
     * @Method("POST")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function getCommentAction(Project $project, $bundle, $key)
    {
        $this->init();

        /** @var Key $keyRecord */
        $keyRecord = $this->getKeyRepository()->findOneBy(array(
                'project'  => $project,
                'bundle'   => $bundle,
                'key'      => $key,
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
     * @Route("/put/message/{projectId}/{bundle}/{catalog}/{key}/{language}")
     * @Method("POST")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function putMessage($project, $bundle, $catalog, $key, $language, Request $request)
    {
        $this->init();
        $param   = json_decode($request->getContent(), true);
        $message = $param['message'];
        $this->insertOrUpdateMessage($project, $bundle, $catalog, $key, $language, $message);

        return $this->resultOk();
    }


    /**
     * @Route("/update/message/if-newest/{projectId}/{bundle}/{key}/{language}")
     * @Method("POST")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function updateMessageIfNewest($project, $bundle, $key, $language, Request $request)
    {
        $this->init();
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
            )
        );

        if(!$keyRecord){
            $keyRecord = new Key();
            $keyRecord->setProject($project);
            $keyRecord->setKey($key);
            $keyRecord->setBundle($bundle);
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
     * @Route("/update/comment/if-newest/{projectId}/{bundle}/{key}")
     * @Method("POST")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function updateCommentIfNewest($project, $bundle, $key, Request $request)
    {
        $this->init();

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
            )
        );
        if(!$keyRecord instanceof Key){
            $keyRecord = new Key();
            $keyRecord->setBundle($bundle);
            $keyRecord->setKey($key);
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
        $keyRecord->setUpdatedAt();
        $this->em->persist($keyRecord);
        $this->em->flush();

        return $this->resultOk(array('updated' => true));
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
