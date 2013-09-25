<?php

namespace JLaso\TranslationsBundle\Controller;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use JLaso\TranslationsBundle\Entity\Key;
use JLaso\TranslationsBundle\Entity\Message;
use JLaso\TranslationsBundle\Entity\Permission;
use JLaso\TranslationsBundle\Entity\Project;
use JLaso\TranslationsBundle\Entity\Repository\KeyRepository;
use JLaso\TranslationsBundle\Entity\Repository\LanguageRepository;
use JLaso\TranslationsBundle\Entity\Repository\MessageRepository;
use JLaso\TranslationsBundle\Entity\Repository\ProjectRepository;
use JLaso\TranslationsBundle\Entity\User;
use JLaso\TranslationsBundle\Exception\AclException;
use JLaso\TranslationsBundle\Form\Type\NewProjectType;
use JLaso\TranslationsBundle\Service\MailerService;
use JLaso\TranslationsBundle\Service\Manager\TranslationsManager;
use JLaso\TranslationsBundle\Service\RestService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class DefaultController
 * @package JLaso\TranslationsBundle\Controller
 * @Route("/")
 */
class DefaultController extends Controller
{

    /** @var  EntityManager */
    protected $em;
    protected $config;
    /** @var  TranslationsManager */
    protected $translationsManager;
    /** @var User */
    protected $user;
    /** @var  Translator */
    protected $translator;
    /** @var  RestService */
    protected $restService;

    protected function init()
    {
        $this->em                  = $this->container->get('doctrine.orm.default_entity_manager');
        $this->config              = $this->container->getParameter('jlaso_translations');
        $this->translationsManager = $this->container->get('jlaso.translations_manager');
        $this->user                = $this->get('security.context')->getToken()->getUser();
        $this->translator          = $this->container->get('translator');
        $this->restService         = $this->container->get('jlaso.rest_service');
    }

    /**
     * @Route("/", name="home")
     */
    public function indexAction()
    {
        return $this->redirect($this->generateUrl('user_login'));
    }

    /**
     * @Route("/translations", name="user_index")
     * @Template()
     */
    public function userIndexAction()
    {
        $this->init();
        $projects = $this->translationsManager->getProjectsForUser($this->user);

        return array(
            'projects' => $projects,
        );
    }

    /**
     * Create new Project
     *
     * @Route("/new-project", name="new_project")
     * @Template()
     */
    public function newProjectAction(Request $request)
    {
        $this->init();

        $project  = new Project();
        $form = $this->createForm(new NewProjectType(), $project);

        if($request->isMethod('POST')){
            $form->bind($request);

            if ($form->isValid()) {

                $permission = new Permission();
                $permission->setUser($this->user);
                $permission->setProject($project);
                $permission->addPermission(Permission::OWNER);
                $this->em->persist($permission);
                $this->em->persist($project);
                $this->em->flush();

                /** @var MailerService $mailer */
                $mailer = $this->get('jlaso.mailer_service');
                try{
                    $send   = $mailer->sendNewProjectMessage($project, $this->user);
                }catch(\Exception $e){
                    if($this->get('kernel')->getEnvironment()=='prod'){
                         // ?
                    }
                }

                return $this->redirect($this->generateUrl('user_index'));
            }

        }

        return array(
            'error'         => null,
            'form'          => $form->createView(),
            'project'       => $project,
        );
    }

    /**
     * @Route("/translations/{projectId}/{bundle}/{catalog}/{currentKey}", name="translations")
     * @Template()
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function translationsAction(Project $project, $bundle = '', $catalog ='', $currentKey = '')
    {
        $this->init();
        $permission = $this->translationsManager->userHasProject($this->user, $project);

        if(false === $permission){
            throw new AclException($this->translator->trans('error.acl.not_enough_permissions_to_manage_this_project'));
        }

        /** @var ArrayCollection $localKeys */
        $localKeys = $project->getKeys();
        if((!$bundle || !$catalog) && count($localKeys)){
            $bundle  = $localKeys->first()->getBundle();
            $catalog = $localKeys->first()->getCatalog();
            //ldd($bundle, $catalog);
            return $this->redirect($this->generateUrl('translations', array(
                        'projectId'  => $project->getId(),
                        'bundle'     => $bundle,
                        'catalog'    => $catalog,
                        'currentKey' => $currentKey,
                    )
                )
            );
        }
        $keyRepository = $this->getKeyRepository();
        $bundles       = $keyRepository->findAllBundlesForProject($project);
        $keys          = $keyRepository->findAllKeysForProjectBundleAndCatalog($project, $bundle, $catalog);
        $keysAssoc     = array();
        foreach($keys as $key){
            $keysAssoc = $this->translationsManager->iniToAssoc($key->getKey(), $keysAssoc);
        }

        $managedLocales = explode(',',$project->getManagedLocales());
        $transData = array();
        foreach($keys as $key){
            $data = array(
                'id'       => $key->getId(),
                'key'      => $key->getKey(),
                'id_html'  => $this->translationsManager->keyToHtmlId($key->getKey()),
                'comment'  => $key->getComment(),
                'bundle'   => $key->getBundle(),
                'messages' => array(),
            );
            foreach($key->getMessages() as $message){
                $data['messages'][$message->getLanguage()] = $message->getMessage();
            }
            $transData[] = $data;
        }

        $languages = $this->getLanguageRepository()->findAllLanguageIn($managedLocales, true);
        $projects  = $this->translationsManager->getProjectsForUser($this->user);

        return array(
            'projects'          => $projects,
            'project'           => $project,
            'bundles'           => $bundles,
            'keys'              => $keysAssoc,
            //'keys_raw'        => $keys,
            'current_bundle'    => $bundle,
            'managed_languages' => $managedLocales,
            'trans_data'        => $transData,
            'current_key'       => $currentKey,
            'languages'         => $languages,
        );
    }

    /**
     * @Route("/save-comment/{projectId}", name="save_comment")
     * @Method("POST")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function saveCommentAction(Request $request, Project $project)
    {
        $this->init();

        $bundle   = $request->get('bundle');
        //@TODO: comprobar que el usuario que esta logado tiene permiso para hacer esto
        if(!$bundle || !$request->get('key') || !$request->get('comment')){
            die('validation exception, request content = ' . $request->getContent());
        }

        $key = $this->getKeyRepository()->findOneBy(array(
                'project'  => $project,
                'bundle'   => $bundle,
                'key'      => $request->get('key'),
            )
        );
        if(!$key instanceof Key){
            die('key invalid');
        }
        $comment = $request->get('comment');
        $key->setComment($comment);
        $key->setUpdatedAt();
        $this->em->persist($key);
        $this->em->flush();
        $this->restService->resultOk(
            array(
                'comment' => $comment,
                'id_html' => $this->translationsManager->keyToHtmlId($key->getKey()),
            )
        );
    }


    /**
     * @Route("/save-message/{projectId}", name="save_message")
     * @Method("POST")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function saveMessageAction(Request $request, Project $project)
    {
        $this->init();

        //$params   = json_decode($request->getContent(), true);
        $bundle   = $request->get('bundle');
        $language = $request->get('language');
        //@TODO: comprobar que el usuario que esta logado tiene permiso para hacer esto
        if(!$bundle || !$language || !$request->get('key') || !$request->get('message')){
            die('validation exception, request content = ' . $request->getContent());
        }

        $key = $this->getKeyRepository()->findOneBy(array(
                'project'  => $project,
                'bundle'   => $bundle,
                'key'      => $request->get('key'),
            )
        );
        $message = $this->getMessageRepository()->findOneBy(array(
                'key'      => $key,
                'language' => $language,
            )
        );
        if(!$message instanceof Message){
            $message = new Message();
            $message->setKey($key);
            $message->setLanguage($language);
        }
        $message->setMessage($request->get('message'));
        $message->setUpdatedAt();
        $this->em->persist($message);
        $this->em->flush();
        die('OK');
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
    protected function getMessageRepository()
    {
        return $this->em->getRepository('TranslationsBundle:Message');
    }

    /**
     * @return LanguageRepository
     */
    protected function getLanguageRepository()
    {
        return $this->em->getRepository('TranslationsBundle:Language');
    }

    /**
     * @return KeyRepository
     */
    protected function getKeyRepository()
    {
        return $this->em->getRepository('TranslationsBundle:Key');
    }



}
