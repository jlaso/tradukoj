<?php

namespace JLaso\TranslationsBundle\Controller;

use Doctrine\Common\Collections\ArrayCollection;

use JLaso\TranslationsBundle\Entity\Permission;
use JLaso\TranslationsBundle\Entity\Project;
use JLaso\TranslationsBundle\Entity\Repository\ProjectRepository;
use JLaso\TranslationsBundle\Entity\TranslationLog;
use JLaso\TranslationsBundle\Entity\Repository\TranslationLogRepository;
use JLaso\TranslationsBundle\Entity\Repository\LanguageRepository;
use JLaso\TranslationsBundle\Entity\User;

use JLaso\TranslationsBundle\Exception\AclException;
use JLaso\TranslationsBundle\Form\Type\NewProjectType;
use JLaso\TranslationsBundle\Service\MailerService;
use JLaso\TranslationsBundle\Service\Manager\TranslationsManager;
use JLaso\TranslationsBundle\Service\RestService;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Doctrine\ORM\EntityManager;
use Doctrine\ODM\MongoDB\DocumentManager;

use JLaso\TranslationsBundle\Document\File;
use JLaso\TranslationsBundle\Document\TranslatableDocument;
use JLaso\TranslationsBundle\Document\Repository\TranslatableDocumentRepository;
use JLaso\TranslationsBundle\Document\Translation;
use JLaso\TranslationsBundle\Document\Repository\TranslationRepository;

/**
 * Class DefaultController
 * @package JLaso\TranslationsBundle\Controller
 * @Route("/")
 */
class DefaultController extends BaseController
{

    const APPROVE    = TranslationLog::APPROVE;
    const DISAPPROVE = TranslationLog::DISAPPROVE;

    /** @var  EntityManager */
    protected $em;
    /** @var  DocumentManager */
    protected $dm;
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
        /** @var DocumentManager $dm */
        $this->dm                  = $this->container->get('doctrine.odm.mongodb.document_manager');
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
    public function userIndexAction($projectId = 0)
    {
        $this->init();

        $projects  = $this->translationsManager->getProjectsForUser($this->user);
        $project   = null;
        $langInfo  = array();
        $keysInfo  = array();
        $stats     = array();

        foreach($projects as $prj){
            $prjId = $prj->getId();
            if(($projectId) && ($projectId == $prjId)){
                $project = $prj;
            }
            $managedLocales = explode(',',$prj->getManagedLocales());
            $languages = $this->getLanguageRepository()->findAllLanguageIn($managedLocales, true);
            foreach($managedLocales as $locale){
                $langInfo[$prjId][$locale] = array(
                    'keys' => 10,
                    'info' => $languages[$locale],
                );
            }
            $stats[] = array(
                'project' => $prj->getId(),
                'locales' => $managedLocales,
                'data'    => $this->translationsManager->getStatistics($prj),
            );
        }

        /** @var User $user */
        $user = $this->getUser();
        $permissions = $user->getPermission();

        return array(
            'action'      => 'user-index',
            'projects'    => $projects,
            'project'     => $project,
            'languages'   => $langInfo,
            'permissions' => $permissions,
            'stats'       => $stats,
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
                // Give permission to write in all languages
                $permission->addPermission(Permission::WRITE_PERM, '*');
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
     * @Route("/translations/{projectId}/{catalog}", name="translations")
     * @Template()
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function translationsAction(Project $project, $catalog ='')
    {
        $this->init();
        //$permission = $this->translationsManager->userHasProject($this->user, $project);
        $permission = $this->translationsManager->getPermissionForUserAndProject($this->user, $project);

        if(!$permission instanceof Permission){
            throw new AclException($this->translator->trans('error.acl.not_enough_permissions_to_manage_this_project'));
        }

        /** @var TranslationRepository $translationRepository */
        $translationRepository = $this->dm->getRepository('TranslationsBundle:Translation');

//        /** @var ArrayCollection $localKeys */
//        $localKeys = $project->getKeys();
//        if((!$bundle || !$catalog) && count($localKeys)){
//            $bundle  = $localKeys->first()->getBundle();
//            $catalog = $localKeys->first()->getCatalog();
//            //ldd($bundle, $catalog);
//            return $this->redirect($this->generateUrl('translations', array(
//                        'projectId'  => $project->getId(),
//                        'bundle'     => $bundle,
//                        'catalog'    => $catalog,
//                        'currentKey' => $currentKey,
//                    )
//                )
//            );
//        }
        //$keyRepository = $this->getKeyRepository();
        $bundles         = $this->translationsManager->getAllBundlesForProject($project);
        //$keys          = $keyRepository->findAllKeysForProjectBundleAndCatalog($project, $bundle, $catalog);
        $keys = $translationRepository->getKeys($project->getId(), $catalog);
        $keysAssoc = array();
        foreach($keys as $key){
            $keysAssoc = $this->translationsManager->iniToAssoc($key['key'], $keysAssoc);
        }


        $managedLocales = explode(',',$project->getManagedLocales());
        $transData = array();
        /*
        foreach($keys as $key){
            $data = array(
                'id'       => $key['id'],
                'key'      => $key['key'],
                'id_html'  => $this->translationsManager->keyToHtmlId($key['key']),
                'comment'  => $key->getComment(),
                'bundle'   => $key->getBundle(),
                'messages' => array(),
                'info'     => array(),
            );
            foreach($key->getMessages() as $message){
                $data['messages'][$message->getLanguage()] = $message->getMessage();
                $data['info'][$message->getLanguage()] = array(
                    'approved' => $message->getApproved(),
                    'id'       => $message->getId(),
                );
            }
            $transData[] = $data;
        }
        */

        $languages = $this->getLanguageRepository()->findAllLanguageIn($managedLocales, true);
        $projects  = $this->translationsManager->getProjectsForUser($this->user);
        $catalogs  = $translationRepository->getCatalogs($project->getId());

        return array(
            'action'            => 'catalogs',
            'projects'          => $projects,
            'project'           => $project,
            'catalogs'          => $catalogs,
            'keys'              => $keysAssoc,
            ////'keys_raw'        => $keys,
            'current'           => $catalog,
            'managed_languages' => $managedLocales,
            'trans_data'        => $transData,
            //'current_key'       => $currentKey,
            'languages'         => $languages,
            'permissions'       => $permission->getPermissions(),
            'bundles'           => $bundles,
        );
    }


    /**
     * @Route("/translations/{projectId}/{catalog}/new-key", name="translations_new_key")
     * @Template()
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function newKeyAction(Request $request, Project $project, $catalog)
    {
        $this->init();
        $permission = $this->translationsManager->getPermissionForUserAndProject($this->user, $project);
        if(!$permission instanceof Permission){
            return $this->printResult(array(
                    'result' => false,
                    'reason' => $this->translator->trans('error.acl.not_enough_permissions_to_manage_this_project'),
                )
            );
        }
        $catalog = trim($catalog);
        $bundle  = trim($request->get('bundle'));
        $keyName = trim($request->get('key'));
        $current = trim($request->get('current'));
        if(!$bundle || !$keyName){
            return $this->printResult(array(
                    'result' => false,
                    'reason' => $this->translator->trans('translations.new_key_dialog.error.not_enough_parameters'),
                )
            );
        }
        $translationRepository = $this->getTranslationRepository();

        $key = $translationRepository->findOneBy(array(
                'projectId' => $project->getId(),
                'catalog'   => $catalog,
                'key'       => $keyName,
            )
        );
        if($key){
            return $this->printResult(array(
                    'result' => false,
                    'reason' => $this->translator->trans('translations.new_key_dialog.error.key_already_exists', array('%key%' => $keyName)),
                )
            );
        }
        $managedLocales = explode(',',$project->getManagedLocales());

        $translation = new Translation();
        $translation->setProjectId($project->getId());
        $translation->setCatalog($catalog);
        $translation->setBundle($bundle);
        $translation->setKey($keyName);
        $translation = $this->translationsManager->normalizeTranslation($translation, $managedLocales);
        $this->dm->persist($translation);
        $this->dm->flush($translation);

        $this->translationsManager->saveLog($translation->getId(), '', $keyName, TranslationLog::NEW_KEY, $this->user, TranslationLog::TRANSLATIONS_GROUP);

        /** @var TranslationRepository $translationRepository */
        $translationRepository = $this->dm->getRepository('TranslationsBundle:Translation');
        if(strpos($current, "Bundle") !== false){
            $keys = $translationRepository->getKeysByBundle($project->getId(), $current);
        }else{
            $keys = $translationRepository->getKeys($project->getId(), $current);
        }
        $tree = $this->keysToPlainArray($keys);

        $languages = $this->getLanguageRepository()->findAllLanguageIn($managedLocales, true);

        $html = $this->renderView("TranslationsBundle:Default:messages.html.twig",array(
                'translation'     => $translation,
                'managed_locales' => $managedLocales,
                'permissions'     => $permission->getPermissions(),
                'languages'       => $languages,
            )
        );

        return $this->printResult(array(
                'result' => true,
                'tree'   => $tree,
                'key'    => $keyName,
                'html'   => $html,
            )
        );
    }



    /**
     * @Route("/translations/{projectId}/{catalog}/remove-key/{key}", name="translations_remove_key")
     * @Template()
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function removeKeyAction(Request $request, Project $project, $catalog, $key)
    {
        $this->init();
        $permission = $this->translationsManager->getPermissionForUserAndProject($this->user, $project);
        if(!$permission instanceof Permission){
            return $this->printResult(array(
                    'result' => false,
                    'reason' => $this->translator->trans('error.acl.not_enough_permissions_to_manage_this_project'),
                )
            );
        }
        $catalog = trim($catalog);
        //$current = trim($request->get('current'));
        $translationRepository = $this->getTranslationRepository();

        $translation = $translationRepository->findOneBy(array(
                'projectId' => $project->getId(),
                'catalog'   => $catalog,
                'key'       => $key,
            )
        );
        if($translation){
            $this->translationsManager->saveLog($translation->getId(), '', $key, TranslationLog::REMOVE_KEY, $this->user, TranslationLog::TRANSLATIONS_GROUP);
            $this->dm->remove($translation);
            $this->dm->flush($translation);
        }else{
            $this->addNoticeFlash('translations.remove_key.error.key_dont_exists', array('%key%' => $keyName));
        }

        return $this->redirect($this->generateUrl('translations', array('projectId' => $project->getId(), 'catalog' => $catalog)));
    }


    protected function isABundle($string)
    {
        return ((false != strpos($string, "Bundle")) || $string == "*app");
    }

    /**
     * @Route("/tools/change-key/project-{projectId}", name="translations_change_key")
     * @Template()
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function changeKeyAction(Request $request, Project $project)
    {
        $this->init();
        $permission = $this->translationsManager->getPermissionForUserAndProject($this->user, $project);
        if(!$permission instanceof Permission){
            return $this->printResult(array(
                    'result' => false,
                    'reason' => $this->translator->trans('error.acl.not_enough_permissions_to_manage_this_project'),
                )
            );
        }
        $catalog = trim($request->get('catalog'));
        $keyNew  = trim($request->get('keyNew'));
        $keyOld  = trim($request->get('keyOld'));
        $current = trim($request->get('current'));
        if(!$catalog || !$current || !$keyNew || !$keyOld){
            return $this->printResult(array(
                    'result' => false,
                    'reason' => $this->translator->trans('translations.change_key_dialog.error.not_enough_parameters'),
                )
            );
        }
        $translationRepository = $this->getTranslationRepository();
        $key = $translationRepository->findOneBy(array(
                'projectId' => $project->getId(),
                'catalog'   => $catalog,
                'key'       => $keyNew,
            )
        );
        if($key){
            return $this->printResult(array(
                    'result' => false,
                    'reason' => $this->translator->trans('translations.change_key_dialog.error.key_already_exists', array('%key%' => $keyNew)),
                )
            );
        }
        $managedLocales = explode(',',$project->getManagedLocales());

        $translation = $translationRepository->findOneBy(array(
                'projectId' => $project->getId(),
                'catalog'   => $catalog,
                'key'       => $keyOld,
            )
        );
        if(!$translation){
            return $this->printResult(array(
                    'result' => false,
                    'reason' => $this->translator->trans('translations.change_key_dialog.error.key_dont_exists', array('%key%' => $keyOld)),
                )
            );
        }
        $translation->setKey($keyNew);
        $translation = $this->translationsManager->normalizeTranslation($translation, $managedLocales);
        $this->dm->persist($translation);
        $this->dm->flush($translation);

        $this->translationsManager->saveLog($translation->getId(), '', $keyOld . " => " . $keyNew, TranslationLog::CHANGE_KEY, $this->user, TranslationLog::TRANSLATIONS_GROUP);

        /** @var TranslationRepository $translationRepository */
        $translationRepository = $this->dm->getRepository('TranslationsBundle:Translation');
        if($this->isABundle($current)){
            $keys = $translationRepository->getKeysByBundle($project->getId(), $current);
        }else{
            $keys = $translationRepository->getKeys($project->getId(), $current);
        }
        $tree = $this->keysToPlainArray($keys);

        $languages = $this->getLanguageRepository()->findAllLanguageIn($managedLocales, true);

        $html = $this->renderView("TranslationsBundle:Default:messages.html.twig",array(
                'translation'     => $translation,
                'managed_locales' => $managedLocales,
                'permissions'     => $permission->getPermissions(),
                'languages'       => $languages,
            )
        );

        return $this->printResult(array(
                'result' => true,
                'tree'   => $tree,
                'key'    => $keyNew,
                'html'   => $html,
            )
        );
    }

    /**
     * @Route("/documents/{projectId}/{bundle}/{key}", name="documents")
     * @Template()
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function documentsAction(Project $project, $bundle ='', $key = '')
    {
        $this->init();
        $permission = $this->translationsManager->getPermissionForUserAndProject($this->user, $project);

        if(!$permission instanceof Permission){
            throw new AclException($this->translator->trans('error.acl.not_enough_permissions_to_manage_this_project'));
        }

        /** @var TranslatableDocumentRepository $translationRepository */
        $transDocRepository = $this->dm->getRepository('TranslationsBundle:TranslatableDocument');

        $keysAssoc = array();

        /** @var TranslatableDocument[] $documents */
        if($bundle){
            $documents = $transDocRepository->findAll(
                array(
                    'projectId' => $project->getId(),
                    'bundle'    => $bundle
                )
            );
            foreach($documents as $document){
                $keysAssoc = $this->translationsManager->iniToAssoc($document->getKey(), $keysAssoc);
            }
        }else{
            $documents = null;
        }


        $managedLocales = explode(',',$project->getManagedLocales());

        $languages = $this->getLanguageRepository()->findAllLanguageIn($managedLocales, true);
        $projects  = $this->translationsManager->getProjectsForUser($this->user);
        $bundles   = $transDocRepository->getBundles($project->getId());

//        if($key){
//            /** @var TranslatableDocumentRepository $translationRepository */
//            $transDocRepository = $this->dm->getRepository('TranslationsBundle:TranslatableDocument');
//            /** @var TranslatableDocument $document */
//            $translation = $transDocRepository->findOneBy(
//                array(
//                    'projectId' => $project->getId(),
//                    'bundle'    => $bundle,
//                    'key'       => $key,
//                )
//            );
//            $permission = $this->translationsManager->getPermissionForUserAndProject($this->user, $project);
//            $languages = $this->getLanguageRepository()->findAllLanguageIn($managedLocales, true);
//
//            $html = $this->renderView("TranslationsBundle:Default:document-messages.html.twig",array(
//                    'translation'     => $translation,
//                    'managed_locales' => $managedLocales,
//                    'permissions'     => $permission->getPermissions(),
//                    'languages'       => $languages,
//                )
//            );
//        }else{
//            $html = '';
//        }

        return array(
            'action'            => 'documents',
            'projects'          => $projects,
            'project'           => $project,
            'bundles'           => $bundles,
            'keys'              => $keysAssoc,
            'current'           => $bundle,
            'managed_languages' => $managedLocales,
            'languages'         => $languages,
            'permissions'       => $permission->getPermissions(),
//            'html_translations' => $html,
        );
    }

    /**
     * @Route("/bundles/{projectId}/{bundle}", name="bundles")
     * @Template()
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function bundlesAction(Project $project, $bundle ='')
    {
        $this->init();
        $permission = $this->translationsManager->getPermissionForUserAndProject($this->user, $project);

        if(!$permission instanceof Permission){
            throw new AclException($this->translator->trans('error.acl.not_enough_permissions_to_manage_this_project'));
        }

        /** @var TranslationRepository $translationRepository */
        $translationRepository = $this->dm->getRepository('TranslationsBundle:Translation');
        $bundles         = $this->translationsManager->getAllBundlesForProject($project);
        $keys = $translationRepository->getKeysByBundle($project->getId(), $bundle);
        $keysAssoc = array();
        foreach($keys as $key){
            $keysAssoc = $this->translationsManager->iniToAssoc($key['key'], $keysAssoc);
        }

        $managedLocales = explode(',',$project->getManagedLocales());
        $transData = array();
        $languages = $this->getLanguageRepository()->findAllLanguageIn($managedLocales, true);
        $projects  = $this->translationsManager->getProjectsForUser($this->user);
        $catalogs  = $translationRepository->getCatalogs($project->getId());

        return array(
            'action'            => 'bundles',
            'projects'          => $projects,
            'project'           => $project,
            'bundles'           => $bundles,
            'keys'              => $keysAssoc,
            'current'           => $bundle,
            'managed_languages' => $managedLocales,
            'trans_data'        => $transData,
            'languages'         => $languages,
            'permissions'       => $permission->getPermissions(),
        );
    }

    /**
     * @Route("/tree-documents-{projectId}-{bundle}.json", name="tree-docs.json")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function treeDocJsonAction(Request $request, Project $project, $bundle)
    {
        /**
         * [
        { "id" : "ajson1", "parent" : "#", "text" : "Simple root node" },
        { "id" : "ajson2", "parent" : "#", "text" : "Root node 2" },
        { "id" : "ajson3", "parent" : "ajson2", "text" : "Child 1" },
        { "id" : "ajson4", "parent" : "ajson2", "text" : "Child 2" },
        ]
         */
        $this->init();
        $permission = $this->translationsManager->getPermissionForUserAndProject($this->user, $project);

        if(!$permission instanceof Permission){
            throw new AclException($this->translator->trans('error.acl.not_enough_permissions_to_manage_this_project'));
        }

        /** @var TranslatableDocumentRepository $repository */
        $repository = $this->dm->getRepository('TranslationsBundle:TranslatableDocument');
        $documents = $repository->findBy(array(
                'projectId' => $project->getId(),
                'bundle'    => $bundle,
            )
        );
        $keys = array();
        foreach($documents as $item){
            $keys[] = array(
                'key' => $item->getKey(),
                'id'  => $item->getId(),
            );
        }
        $keysAssoc = $this->keysToPlainArray($keys);

        return $this->printResult($keysAssoc);
    }


    /**
     * @Route("/logs-{projectId}-{catalog}.json", name="translation_logs.json")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function logsJsonAction(Request $request, Project $project, $catalog)
    {
        $this->init();
        $permission = $this->translationsManager->getPermissionForUserAndProject($this->user, $project);

        if(!$permission instanceof Permission){
            throw new AclException($this->translator->trans('error.acl.not_enough_permissions_to_manage_this_project'));
        }

        $key = $request->get('key');
        $result = array();
        $users = array();

        if($key){
            /** @var TranslationRepository $repository */
            $repository = $this->dm->getRepository('TranslationsBundle:Translation');
            $translation = $repository->findOneBy(array(
                    'projectId' => $project->getId(),
                    'catalog'   => $catalog,
                    'key'       => $key,
                )
            );
            if($translation){
                /** @var TranslationLogRepository $logRepository */
                $logRepository = $this->em->getRepository('TranslationsBundle:TranslationLog');
                /** @var TranslationLog[] $logs */
                $logs = $logRepository->findBy(array('translationId'=>$translation->getId()), array('createdAt'=>'DESC'));
                foreach($logs as $log){
                    $user = $log->getUser();
                    if($user && !isset($users[$user->getId()])){
                        $users[$user->getId()] = $user->getName() ?: $user->getEmail();
                    }
                    $result[] = array(
                        'id' => $log->getId(),
                        'date' => $log->getCreatedAt()->format('d/M/Y H:i:s'),
                        'user_id' => $user->getId(),
                        'locale' => $log->getLocale(),
                        'action' => $log->getActionType(),
                        'message' => $log->getMessage(),
                    );
                }
            }
        }

        return $this->printResult(array('logs' => $result, 'users' => $users));
    }

    /**
     * @Route("/tree-{projectId}-{criteria}.json", name="tree.json")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function treeJsonAction(Request $request, Project $project, $criteria)
    {
        /**
          * [
                { "id" : "ajson1", "parent" : "#", "text" : "Simple root node" },
                { "id" : "ajson2", "parent" : "#", "text" : "Root node 2" },
                { "id" : "ajson3", "parent" : "ajson2", "text" : "Child 1" },
                { "id" : "ajson4", "parent" : "ajson2", "text" : "Child 2" },
            ]
         */
        $this->init();
        // only show keys with blank message (pending) in this language, if any
        $onlyLanguage = trim($request->get('language'));
        $approvedFilter = trim($request->get('status'));
        $permission = $this->translationsManager->getPermissionForUserAndProject($this->user, $project);

        if(!$permission instanceof Permission){
            throw new AclException($this->translator->trans('error.acl.not_enough_permissions_to_manage_this_project'));
        }

        if(strpos($criteria, "Bundle") !== false){
            $keys = $this->getTranslationRepository()->getKeysByBundle($project->getId(), $criteria, $onlyLanguage, $approvedFilter);
        }else{
            $keys = $this->getTranslationRepository()->getKeys($project->getId(), $criteria, $onlyLanguage, $approvedFilter);
        }
        $keysAssoc = $this->keysToPlainArray($keys);

        return $this->printResult($keysAssoc);
    }

    /**
     * @param array  $keys
     *
     * @return array
     */
    protected function keysToPlainArray($keys)
    {
        $trans = array();

        $keysAssoc = array();
        foreach($keys as $key){
            $this->keyed2Plain($key['key'], $keysAssoc, $trans);
        }

        return array_values($keysAssoc);
    }

    protected function keyed2Plain($keyedArray, &$arrayAssoc)
    {
        $keys = explode('.', $keyedArray);
        $id = $idAnt = '';
        $i = count($keys) - 1;
        foreach($keys as $k){
            $id   .= $k;
            $arrayAssoc[$id] = array(
                'id'     => $id,
                'parent' => $idAnt ?: '#',
                'text'   => $k,

            );
            $idAnt = $id;
            $id .= '.';
            $i--;
        }
    }

    /**
     * @Route("/translations-messages", name="translations_messages")
     * @Method("POST")
     */
    public function getMessages(Request $request)
    {
        $this->init();
        $projectId = $request->get('projectId');
        $catalog   = $request->get('catalog');
        $bundle    = $request->get('bundle');
        $key       = $request->get('key');
        /** @var Project $project */
        $project   = $this->getProjectRepository()->find($projectId);

        if(!$project){
            $this->printResult(array(
                    'result' => false,
                    'reason' => 'project '.$projectId.' not exists',
                )
            );
        };

        $managedLocales = explode(',', $project->getManagedLocales());
        $translation    = $this->translationsManager->getTranslation($project, $catalog ?: $bundle, $key);
        $permission     = $this->translationsManager->getPermissionForUserAndProject($this->user, $project);
        $languages      = $this->getLanguageRepository()->findAllLanguageIn($managedLocales, true);

        $html = $this->renderView("TranslationsBundle:Default:messages.html.twig",array(
                'translation'     => $translation,
                'managed_locales' => $managedLocales,
                'permissions'     => $permission->getPermissions(),
                'languages'       => $languages,
            )
        );
        $this->printResult(array(
                'result' => true,
                'key'    => $translation->getKey(),
                'html'   => $html,
            )
        );
    }

    /**
     * @Route("/documents-messages", name="documents_messages")
     * @Method("POST")
     */
    public function getDocuments(Request $request)
    {
        $this->init();
        $projectId = intval($request->get('projectId'));
        $bundle    = $request->get('bundle');
        $key       = $request->get('key');
        /** @var Project $project */
        $project   = $this->getProjectRepository()->find($projectId);

        if(!$project){
            $this->printResult(array(
                    'result' => false,
                    'reason' => 'project '.$projectId.' not exists',
                )
            );
        };

        $managedLocales = explode(',',$project->getManagedLocales());

        /** @var TranslatableDocumentRepository $translationRepository */
        $transDocRepository = $this->dm->getRepository('TranslationsBundle:TranslatableDocument');
        /** @var TranslatableDocument $document */
        $translation = $transDocRepository->findOneBy(
            array(
                'projectId' => $projectId,
                'bundle'    => $bundle,
                'key'       => $key,
            )
        );
        $permission = $this->translationsManager->getPermissionForUserAndProject($this->user, $project);
        $languages = $this->getLanguageRepository()->findAllLanguageIn($managedLocales, true);

        $html = $this->renderView("TranslationsBundle:Default:document-messages.html.twig",array(
                'translation'     => $translation,
                'managed_locales' => $managedLocales,
                'permissions'     => $permission->getPermissions(),
                'languages'       => $languages,
            )
        );
        $this->printResult(array(
                'result' => true,
                'key'    => $translation->getKey(),
                'html'   => $html,
            )
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

        $catalog  = $request->get('catalog');
        //$bundle   = $request->get('bundle');
        $key      = $request->get('key');
        $comment  = str_replace("\'","'",$request->get('comment'));
        //@TODO: comprobar que el usuario que esta logado tiene permiso para hacer esto
        if(!$catalog || !$key || !$comment){
            die('validation exception, request content = ' . $request->getContent());
        }

        $translation = $this->translationsManager->putComment($project, $catalog, $key, $comment);
        if(!$translation){
            $this->printResult(array(
                    'result' => false,
                    'reason' => 'translation not found',
                )
            );
        }
        $this->dm->persist($translation);
        $this->dm->flush();

        $this->translationsManager->saveLog($translation->getId(), '*', $comment, TranslationLog::COMMENT, $this->user);

        $this->printResult(array(
                'result'  => true,
                'message' => $comment,
            )
        );
    }

    /**
     * @param Message $msg
     * @param         $action
     *
     * @throws \Exception
     */
    protected function genericActionOnMessage(Message $msg, $action)
    {
        die('not implemented yet!');
//        switch($action){
//            case self::APPROVE:
//                $msg->setApproved(true);
//                break;
//
//            case self::DISAPPROVE:
//                $msg->setApproved(false);
//                break;
//
//            default:
//                throw new \Exception("genericActionOnMessage: unknown action " . $action);
//        }
//
//        $this->em->persist($msg);
//        $this->em->flush($msg);
//        $this->translationsManager->saveLog($msg, $action, $this->user);
    }

    /**
     * @param $locale
     * @param $perm
     *
     * @return bool
     */
    protected function checkPermission($locale, $perm)
    {
        $permissionArray = $this->user->getPermission();
        $permission      = null;
        $permissions     = isset($permissionArray[Permission::LOCALE_KEY]) ? $permissionArray[Permission::LOCALE_KEY] : array();
        if (isset($permissions[$locale])) {
            $permission = $permissions[$locale];
        } else {
            $permission = isset($permissions[Permission::WILD_KEY]) ? $permissions[Permission::WILD_KEY] : '';
        }

        return Permission::checkPermission($permission, $perm);
    }

    /**
     * @Route("/approve-translation/{translationId}/{locale}", name="approve_translation")
     * @Method("POST")
     * @ ParamConverter("translation", class="TranslationsBundle:Translation", options={"id" = "translationId"})
     */
    public function approveMessageAction($translationId, $locale)
    {
        $this->init();

        $translation = $this->getTranslationRepository()->find($translationId);

        if(!$translation){
            return $this->restService->exception(
                $this->translator->trans('message.translation_not_found')
            );
        }

        if($this->checkPermission($locale, Permission::ADMIN_PERM)){
            $translations = $translation->getTranslations();
            if(isset($translations[$locale])){
                $translations[$locale]['approved'] = true;
                $translation->setTranslations($translations);
                $this->dm->persist($translation);
                $this->dm->flush($translation);
                $this->restService->resultOk(
                    array(
                        'message'  => $translations[$locale]['message'],
                        'approved' => true,
                        'id'       => $translationId,
                        'locale'   => $locale,
                    )
                );
            }else{
                $this->restService->exception(
                    $this->translator->trans('message.inexistent_locale_to_approve')
                );
            }
        }else{
            $this->restService->exception(
                $this->translator->trans('message.without_permissions_to_approve')
            );
        }

    }

     /**
     * @Route("/disapprove-translation/{translationId}/{locale}", name="disapprove_translation")
     * @Method("POST")
     * @ ParamConverter("translation", class="TranslationsBundle:Translation", options={"id" = "translationId"})
     */
    public function disapproveMessageAction($translationId, $locale)
    {
        $this->init();

        $translation = $this->getTranslationRepository()->find($translationId);

        if(!$translation){
            return $this->restService->exception(
                $this->translator->trans('message.translation_not_found')
            );
        }

        if($this->checkPermission($locale, Permission::ADMIN_PERM)){
            $translations = $translation->getTranslations();
            if(isset($translations[$locale])){
                $translations[$locale]['approved'] = false;
                $translation->setTranslations($translations);
                $this->dm->persist($translation);
                $this->dm->flush($translation);
                $this->restService->resultOk(
                    array(
                        'message'  => $translations[$locale]['message'],
                        'approved' => false,
                        'id'       => $translationId,
                        'locale'   => $locale,
                    )
                );
            }else{
                $this->restService->exception(
                    $this->translator->trans('message.inexistent_locale_to_approve')
                );
            }
        }else{
            $this->restService->exception(
                $this->translator->trans('message.without_permissions_to_approve')
            );
        }

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
        $catalog  = $request->get('catalog');
        $locale   = $request->get('locale');
        $key      = $request->get('key');
        $message  = str_replace("\'","'",$request->get('message'));
        //@TODO: comprobar que el usuario que esta logado tiene permiso para hacer esto
        if(!$catalog || !$locale || !$key || !$message){
            die('validation exception, request content = ' . $request->getContent());
        }

        $translation = $this->translationsManager->putTranslation($project, $catalog, $key, $locale, $message);
        if(!$translation){
            $this->printResult(array(
                    'result' => false,
                    'reason' => 'translation not found',
                )
            );
        }
        $this->dm->persist($translation);
        $this->dm->flush();

        $this->translationsManager->saveLog($translation->getId(), $locale, $message, TranslationLog::TRANSLATE, $this->user);

        $this->printResult(array(
                'result'  => true,
                'message' => $message,
            )
        );
    }



    /**
     * @Route("/save-document/{projectId}", name="save_document")
     * @Method("POST")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function saveDocumentAction(Request $request, Project $project)
    {
        $this->init();

        $bundle   = $request->get('bundle');
        $locale   = $request->get('locale');
        $key      = $request->get('key');
        $message  = str_replace("\'","'",$request->get('message'));

        //@TODO: comprobar que el usuario que esta logado tiene permiso para hacer esto
        if(!$bundle || !$locale || !$key || !$message){
            die('validation exception, request content = ' . $request->getContent());
        }

        $transDocRepository = $this->getTranslatableDocumentRepository();

        /** @var TranslatableDocument $translation */
        $translation = $transDocRepository->findOneBy(array(
                'projectId' => $project->getId(),
                'bundle'    => $bundle,
                'key'       => $key,
            )
        );
        if(!$translation){
            return $this->printResult(array(
                    'result' => false,
                    'reason' => 'document not found',
                )
            );
        }
        /** @var File[] $translations */
        $files = $translation->getFiles();
        $found = false;
        foreach($files as $file){
            if($file->getLocale() == $locale){
                $found = true;
                break;
            }
        }
        if(!$found){
            return $this->printResult(array(
                    'result' => false,
                    'reason' => 'locale not found',
                )
            );
        }

        $file->setMessage($message);
        $this->dm->persist($translation);
        $this->dm->flush();

        $this->translationsManager->saveLog($translation->getId(), $locale, $message, TranslationLog::TRANSLATE, $this->user, TranslationLog::DOCUMENTS_GROUP);

        $this->printResult(array(
                'result'  => true,
                'message' => $message,
            )
        );
    }


    /**
     * @Route("/normalize/{projectId}/{erase}", name="normalize")
     * @ Method("POST")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function normalizeAction(Request $request, Project $project, $erase = '')
    {
        $this->init();

        // completar los documentos  a los que le falten subdocumentos de traducciones

        //$this->translationsManager->userHasProject($this->user, $project);
        $permissions = $this->translationsManager->getPermissionForUserAndProject($this->user, $project);
        $permissions = $permissions->getPermissions();

        if($permissions['general'] != Permission::OWNER){
            return $this->printResult(array(
                    'result' => false,
                    'reason' => 'not enough permissions to do this',
                )
            );
        }

        $managedLocales = explode(',',$project->getManagedLocales());

        /** @var Translation[] $translations */
        $translations = $this->getTranslationRepository()->findBy(array('projectId' => $project->getId() ));

        foreach($translations as $translation){

            $transArray = $translation->getTranslations();
            foreach($managedLocales as $locale){
                if(!isset($transArray[$locale])){
                    $transArray[$locale] = Translation::genTranslationItem('');
                }
            }
            $translation->setTranslations($transArray);
            $this->dm->persist($translation);

        }

        $this->dm->flush();

        if($erase === 'erase-duplicates')
        {
            // eliminar los documentos que no tengan translation en ingles (para borrar duplicados)

            foreach($translations as $translation){
                $transArray = $translation->getTranslations();
                if(!$transArray['en']['message']){
                    print 'erasing ... ' . $translation->getId() . '<br/>';
                    $this->dm->remove($translation);
                }
            }

            $this->dm->flush();
        }

        return $this->printResult(array(
                'result' => true,
            )
        );
    }

    /**
     * @Route("/search/{projectId}", name="search")
     * @Method("POST")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function searchAction(Request $request, Project $project)
    {
        $this->init();
        $search = $request->get('search');

        $permissions = $this->translationsManager->getPermissionForUserAndProject($this->user, $project);

        if(!$permissions){
            return $this->printResult(array(
                    'result' => false,
                    'reason' => 'not enough permissions to do this',
                )
            );
        }

        $managedLocales = explode(',',$project->getManagedLocales());

        /** @var Translation[] $translations */
        $translations = $this->getTranslationRepository()->searchKeys($project->getId(), $search);

        die(count($translations));
//        foreach($translations as $translation){
//
//            $transArray = $translation->getTranslations();
//            foreach($managedLocales as $locale){
//                if(!isset($transArray[$locale])){
//                    $transArray[$locale] = Translation::genTranslationItem('');
//                }
//            }
//            $translation->setTranslations($transArray);
//            $this->dm->persist($translation);
//
//        }
//
//        $this->dm->flush();
//
//        return $this->printResult(array(
//                'result' => true,
//            )
//        );
    }

    /**
     * @Route("/stats/{projectId}", name="statistics")
     * @ Method("POST")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function getStatistics(Request $request, Project $project)
    {
        $this->init();
        $search = $request->get('search');

        $permissions = $this->translationsManager->getPermissionForUserAndProject($this->user, $project);

        if(!$permissions){
            return $this->printResult(array(
                    'result' => false,
                    'reason' => 'not enough permissions to do this',
                )
            );
        }

        $result = $this->translationsManager->getStatistics($project);

        var_dump($result); die;
        return $this->printResult($result);

    }

    public function searchMessagesAction(Request $request)
    {
        $project  = $request->getArgument('project');
        $search  = $request->getArgument('search');

        /** @var DocumentManager $dm */
        $dm = $this->getContainer()->get('doctrine.odm.mongodb.document_manager');
        /** @var EntityManager $em */
        $em = $this->getContainer()->get('doctrine.orm.default_entity_manager');

        /** @var ProjectRepository $projectRepository */
        $projectRepository = $em->getRepository('TranslationsBundle:Project');
        /** @var TranslationRepository $translationsRepository */
        $translationsRepository = $dm->getRepository('TranslationsBundle:Translation');

        if(intval($project)){
            $project = $projectRepository->find($project);
        }else{
            $project = $projectRepository->findOneBy(array('name' => trim(strtolower($project))));
        }
        /** @var Project $project */
        if(!$project){
            throw new \Exception('Project not found');
        }
        /** @var Translation[] $translations */
        $translations = $translationsRepository->findBy(array('projectId'=>$project->getId()));

        $output = array();
        foreach($translations as $translation){

            foreach($translation->getTranslations() as $locale=>$key){

                if(preg_match('/'.$search.'/i', $key['message'], $match)){

                    $output[] = sprintf("\tFound (%s) in <info>%s</info> in locale <comment>%s</comment>", $match[0], $translation->getKey(), $locale);

                }

            }

        }

        return $this->resultOk($output);
    }

    /**
     * @return ProjectRepository
     */
    protected function getProjectRepository()
    {
        return $this->em->getRepository('TranslationsBundle:Project');
    }

    /**
     * @return LanguageRepository
     */
    protected function getLanguageRepository()
    {
        return $this->em->getRepository('TranslationsBundle:Language');
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



}
