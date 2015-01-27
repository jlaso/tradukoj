<?php

namespace JLaso\TranslationsBundle\Controller;

use Doctrine\Common\Collections\ArrayCollection;

use JLaso\TranslationsBundle\Document\ProjectInfo;
use JLaso\TranslationsBundle\Document\Repository\ProjectInfoRepository;
use JLaso\TranslationsBundle\Entity\Permission;
use JLaso\TranslationsBundle\Entity\Project;
use JLaso\TranslationsBundle\Entity\Repository\ProjectRepository;
use JLaso\TranslationsBundle\Entity\TranslationLog;
use JLaso\TranslationsBundle\Entity\Repository\TranslationLogRepository;
use JLaso\TranslationsBundle\Entity\Repository\LanguageRepository;
use JLaso\TranslationsBundle\Entity\User;

use JLaso\TranslationsBundle\Exception\AclException;
use JLaso\TranslationsBundle\Form\Type\ExportToExcelType;
use JLaso\TranslationsBundle\Form\Type\NewProjectType;
use JLaso\TranslationsBundle\Model\ExportToExcel;
use JLaso\TranslationsBundle\Service\MailerService;
use JLaso\TranslationsBundle\Service\Manager\TranslationsManager;
use JLaso\TranslationsBundle\Service\RestService;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Doctrine\ORM\EntityManager;
use Doctrine\ODM\MongoDB\DocumentManager;

use JLaso\TranslationsBundle\Document\File;
use JLaso\TranslationsBundle\Document\TranslatableDocument;
use JLaso\TranslationsBundle\Document\Repository\TranslatableDocumentRepository;
use JLaso\TranslationsBundle\Document\Translation;
use JLaso\TranslationsBundle\Document\Repository\TranslationRepository;
use WebDriver\Exception;

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
    /** @var string root */
    protected $root;

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
        $this->root                = realpath($this->get('kernel')->getRootDir() . "/..");
    }

    /**
     * @Route("/", name="home")
     */
    public function indexAction()
    {
        return $this->redirect($this->generateUrl('user_login'));
    }

    /**
     * @Route("/regenerate-project-info/{projectId}", name="regenerate_project_info")
     */
    public function regenerateProjectInfoAction($projectId)
    {
        $this->init();
        /** @var ProjectInfo $projectInfo */
        $projectInfo = $this->dm->getRepository("TranslationsBundle:ProjectInfo")->getProjectInfo($projectId);
        if(!$projectInfo){
            $projectInfo = new ProjectInfo();
            $projectInfo->setProjectId($projectId);
        }
        $projectInfo->setBundles(array());
        $projectInfo->setCatalogs(array());

        $translations = $this->getTranslationRepository()->findBy(array("projectId"=>intval($projectId)));
        //ldd($projectId, $translations);
        foreach($translations as $translation){
            $bundle = $translation->getBundle();
            $projectInfo->addBundle($bundle);
            $catalog = $translation->getCatalog();
            $projectInfo->addCatalog($catalog);
        }
        $this->dm->persist($projectInfo);
        $this->dm->flush();
        ld($projectInfo->getBundles());
        die("done!");
    }

    /**
     * @Route("/translations", name="user_index")
     * @Template()
     */
    public function userIndexAction($projectId = 0)
    {
        $this->init();

//        $t = $this->getTranslationRepository()->find("52d5a2dd346002d371000019");
//        //$t->setBundle(md5(date("U")));
//        $t->setBundle("AdminBundle0");
//        //$this->dm->persist($t);
//        $this->dm->flush();
//        ldd("t");

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
//var_dump($languages); die;
            $langInfo[$prjId] = $this->getTranslationRepository()->getKeysByLanguage($prjId, $languages);
//            foreach($managedLocales as $locale){
//                $langInfo[$prjId][$locale] = array(
//                    'keys' => 10,
//                    'info' => $languages[$locale],
//                );
//            }
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
     * clean data replacing typographic commas and escaping doubles commas
     *
     * @param string $data
     *
     * @return string
     */
    protected function clean($data)
    {
        $data = str_replace(
            array(
                '”','‘','’','´','“','€',"\r","\n",
            ),array(
                '"',"'","'","'",'"','&euro;','','',
            ),$data);

        return str_replace('"', '""', $data);
    }

    /**
     * @Route("/export-to-excel/{projectId}", name="export-to-excel")
     * @Template()
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function exportToExcelAction(Request $request, Project $project)
    {
        $this->init();
        //$permission = $this->translationsManager->userHasProject($this->user, $project);
        $permission = $this->translationsManager->getPermissionForUserAndProject($this->user, $project);

        if(!$permission instanceof Permission){
            throw new AclException($this->translator->trans('error.acl.not_enough_permissions_to_manage_this_project'));
        }

        $locales = preg_split("/,/", $project->getManagedLocales());
        $exportToExcel = new ExportToExcel($project);
        $form = $this->createForm(new ExportToExcelType($locales), $exportToExcel);

        if($request->isMethod('POST')){
            $form->bind($request);

            if ($form->isValid()) {

                $language = $locales[$exportToExcel->getLocale()];
                $tmpFile = tempnam('/tmp', 'excel');
                /** @var DocumentManager $dm */
                $dm = $this->container->get('doctrine.odm.mongodb.document_manager');
                /** @var TranslationRepository $translationsRepository */
                $translationsRepository = $dm->getRepository('TranslationsBundle:Translation');

                //$phpExcel = new \PHPExcel();
                $phpExcel  = $this->container->get('phpexcel');

                /** @var \PHPExcel $excel */
                $excel = $phpExcel->createPHPExcelObject();

                $excel->getProperties()->setCreator("Maarten Balliauw")
                    ->setLastModifiedBy("Maarten Balliauw")
                    ->setTitle("Office 2007 XLSX Test Document")
                    ->setSubject("Office 2007 XLSX Test Document")
                    ->setDescription("Test document for Office 2007 XLSX, generated using PHP classes.")
                    ->setKeywords("office 2007 openxml php")
                    ->setCategory("Test result file");

                $newSheet = clone $excel->getActiveSheet();
                $newSheet2 = clone $excel->getActiveSheet();

                /**
                 * MAIN SHEET
                 */

                $mainSheet = $excel->setActiveSheetIndex(0);
                $mainSheet
                    ->setTitle($language)
                    ->setCellValue('A1', "key (don't translate)")
                    ->setCellValue('B1', $language . " (don't translate)")
                    ->setCellValue('C1', 'New language (here the translation)');

                $mainSheet->getColumnDimension("A")->setAutoSize(true);
                $mainSheet->getColumnDimension("B")->setAutoSize(true);
                $mainSheet->getColumnDimension("C")->setAutoSize(true);

                $mainSheet->getStyle('A1')->getFont()->setName('Candara')->setSize(20)->setBold(true);
                $mainSheet->getStyle('B1')->getFont()->setName('Candara')->setSize(20)->setBold(true);
                $mainSheet->getStyle('C1')->getFont()->setName('Candara')->setSize(20)->setBold(true);

                /** @var Translation[] $translations */
                $translations = $translationsRepository->findBy(
                    array(
                        'projectId' => $project->getId(),
                        //'catalog'   => $catalog,
                        'deleted'   => false,
                    ),
                    array('key'=>'ASC')
                );

                $i = 2;
                $labels = array();
                $index = 1;

                foreach($translations as $translation){
                    foreach($translation->getTranslations() as $locale=>$key){

                        if($locale == $language){

                            $message = $key['message'];
                            if($exportToExcel->getCompressHtmlLabels()){
                                if(preg_match_all("|(</?[^<]*>)|i", $message, $matches)){
                                    foreach($matches[1] as $match){
                                        if($match && !isset($labels[$match])){
                                            $labels[$match] = sprintf("[%d]", $index++);
                                        }
                                        $subst = $labels[$match];
                                        $message = str_replace($match, $subst, $message);
                                    }
                                }
                            }
                            if($exportToExcel->getCompressVariables()){
                                if(preg_match_all("|(\%([^%]*)\%)|i", $message, $matches, PREG_SET_ORDER)){
                                    foreach($matches as $match){
                                        $varName = $match[2];
                                        $textVar = $match[1];
                                        if($textVar && !isset($labels[$textVar])){
                                            $labels[$textVar] = sprintf("(%d)", $index++);
                                        }
                                        $subst = $labels[$textVar];
                                        $subst = sprintf("%s%s%s", $subst, $varName, $subst);
                                        $message = str_replace($textVar, $subst, $message);
                                    }
                                }
                            }
                            $message = $this->clean($message);

                            $mainSheet
                                ->setCellValue("A{$i}", $translation->getKey())
                                ->setCellValue("B{$i}", $message);

                            $i++;
                        }

                    }
                }

                /**
                 * KEY SHEET
                 */

                $excel->addSheet($newSheet);

                $keySheet = $excel->setActiveSheetIndex(1);

                $keySheet->setTitle('keys');
                $keySheet->getColumnDimension("A")->setAutoSize(true);
                $keySheet->getColumnDimension("B")->setAutoSize(true);

                $i = 1;
                foreach($labels as $label=>$num){
                    $num0 = $num;
                    if(preg_match("|^\((.*?)\)$|",$num,$match)){
                        $col = "A";
                        $num = $match[1];
                    }else{
                        if(preg_match("|^\[(.*?)\]$|",$num,$match)){
                            $col = "B";
                            $num = $match[1];
                        }else{
                            die("error de interpretacion $num");
                        }
                    }
                    $keySheet
                        ->setCellValue("{$col}{$num}", $label)
                        //->setCellValue("C{$num}", $num0)
                        //->setCellValue("D{$i}", "$label=>$num0")
                        ;

                    $i++;
                }

                //$keySheet
                //    ->setCellValue("D1", print_r($labels, true));

                /**
                 * BUNDLE & FILE SHEETS
                 */

                if($exportToExcel->getBundleFile()){

                    // Info about bundles and filenames by keys
                    $newSheet2->setTitle("bundle");
                    $excel->addSheet($newSheet2);

                    $newSheet = clone $newSheet2;
                    $newSheet->setTitle("fileName");
                    $excel->addSheet($newSheet);

                    $bundleSheet = $excel->setActiveSheetIndex(2);
                    $fileSheet = $excel->setActiveSheetIndex(3);
                    $bundleSheet->getColumnDimension("A")->setAutoSize(true);
                    $fileSheet->getColumnDimension("A")->setAutoSize(true);

                    $i = 1;
                    $localeCol = array();
                    foreach($locales as $locale)
                    {
                        $localeCol[$locale] = count($localeCol) + 1;
                        $col = $this->column($localeCol[$locale]);
                        $bundleSheet
                            ->setCellValue("{$col}{$i}", $locale)
                            ->getColumnDimension("{$col}")->setAutoSize(true);
                        $fileSheet
                            ->setCellValue("{$col}{$i}", $locale)
                            ->getColumnDimension("{$col}")->setAutoSize(true);
                    }
                    $i++;

                    foreach($translations as $translation){

                        foreach($translation->getTranslations() as $locale=>$key){

                            $colName = $this->column($localeCol[$locale]);

                            $bundleSheet
                                ->setCellValue("A{$i}", $translation->getKey())
                                ->setCellValue("{$colName}{$i}", $translation->getBundle());

                            $fileSheet
                                ->setCellValue("A{$i}", $translation->getKey())
                                ->setCellValue("{$colName}{$i}", isset($key['fileName']) ? $key['fileName'] : '' );

                            $col++;
                        }
                        $i++;

                    }
                }

                $objWriter = new \PHPExcel_Writer_Excel5($excel);
                $objWriter->save($tmpFile);

                header('Content-Description: File Transfer');
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment;filename="' . $tmpFile . '.xls"');
                header('Content-Transfer-Encoding: binary');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($tmpFile));

                die(file_get_contents($tmpFile));

                return $this->redirect($this->generateUrl('user_index'));
            }

        }

        return array(
            'error'         => null,
            'form'          => $form->createView(),
            'project'       => $project,
            'action'        => 'export-to-excel',
            'permissions'   => $permission->getPermissions(),
        );
    }

    protected function column($col)
    {
        $result = "";
        while($col>26){
            $remain = $col%26;
            $result = $result . chr(65+$remain);
            $col = intval($col/26);
        };
        $result = $result . chr(65+$col);

        return $result;
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
        $catalogs  = $this->translationsManager->getAllCatalogsForProject($project);

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
        $tree      = $this->keysToPlainArray($keys);
        $languages = $this->getLanguageRepository()->findAllLanguageIn($managedLocales, true);
        $bundles   = $this->getProjectInfoRepository()->getBundles($project->getId());

        $html = $this->renderView("TranslationsBundle:Default:messages.html.twig",array(
                'translation'     => $translation,
                'managed_locales' => $managedLocales,
                'permissions'     => $permission->getPermissions(),
                'languages'       => $languages,
                'bundles'         => $bundles,
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
            $this->dm->flush();
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
        $bundle  = trim($request->get('bundle'));
        $current = trim($request->get('current'));
        if(!$catalog || !$current || !$keyNew || !$keyOld || !$bundle){
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
        if($key && ($keyNew!=$keyOld)){
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
        $translation->setBundle($bundle);
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
        $tree      = $this->keysToPlainArray($keys);
        $languages = $this->getLanguageRepository()->findAllLanguageIn($managedLocales, true);
        $bundles   = $this->getProjectInfoRepository()->getBundles($project->getId());

        $html = $this->renderView("TranslationsBundle:Default:messages.html.twig",array(
                'translation'     => $translation,
                'managed_locales' => $managedLocales,
                'permissions'     => $permission->getPermissions(),
                'languages'       => $languages,
                'bundles'         => $bundles,
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
        $bundles               = $this->getProjectInfoRepository()->getBundles($project->getId());
        $keys                  = $translationRepository->getKeysByBundle($project->getId(), $bundle);
        $keysAssoc             = array();
        foreach($keys as $key){
            $keysAssoc = $this->translationsManager->iniToAssoc($key['key'], $keysAssoc);
        }

        $managedLocales = explode(',',$project->getManagedLocales());
        $transData = array();
        $languages = $this->getLanguageRepository()->findAllLanguageIn($managedLocales, true);
        $projects  = $this->translationsManager->getProjectsForUser($this->user);
        $catalogs  = $this->translationsManager->getAllCatalogsForProject($project);

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
        /** @var TranslationRepository $translationRepository */
        $translationRepository = $this->dm->getRepository('TranslationsBundle:Translation');

        $managedLocales = explode(',', $project->getManagedLocales());
        $translation    = $this->translationsManager->getTranslation($project, $catalog ?: $bundle, $key);
        $permission     = $this->translationsManager->getPermissionForUserAndProject($this->user, $project);
        $languages      = $this->getLanguageRepository()->findAllLanguageIn($managedLocales, true);
        $bundles        = $this->getProjectInfoRepository()->getBundles($project->getId());

        $html = $this->renderView("TranslationsBundle:Default:messages.html.twig",array(
                'translation'     => $translation,
                'managed_locales' => $managedLocales,
                'permissions'     => $permission->getPermissions(),
                'languages'       => $languages,
                'bundles'         => $bundles,
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
        /** @var TranslationRepository $translationRepository */
        $translationRepository = $this->dm->getRepository('TranslationsBundle:Translation');
        /** @var TranslatableDocument $document */
        $translation = $transDocRepository->findOneBy(
            array(
                'projectId' => $projectId,
                'bundle'    => $bundle,
                'key'       => $key,
            )
        );
        $permission = $this->translationsManager->getPermissionForUserAndProject($this->user, $project);
        $languages  = $this->getLanguageRepository()->findAllLanguageIn($managedLocales, true);
        $bundles    = $this->getProjectInfoRepository()->getBundles($project->getId());

        $html = $this->renderView("TranslationsBundle:Default:document-messages.html.twig",array(
                'translation'     => $translation,
                'managed_locales' => $managedLocales,
                'permissions'     => $permission->getPermissions(),
                'languages'       => $languages,
                'bundles'         => $bundles,
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
        $normalized = array();

        foreach($translations as $translation){

            $bundle = "";
            $transArray = $translation->getTranslations();
            foreach($managedLocales as $locale){
                if(!isset($transArray[$locale])){
                    $transArray[$locale] = Translation::genTranslationItem('');
                    $normalized[] = $translation->getKey() .  "[$locale]";
                }else{
                    if(!$bundle && isset($transArray[$locale]['fileName']) && $transArray[$locale]['fileName']){
                        if(preg_match("@/(?<bundle>\w*?Bundle)/@", $transArray[$locale]['fileName'], $match)){
                            $bundle = $match['bundle'];
                        }else{
                            if(preg_match("@/app/@", $transArray[$locale]['fileName'])){
                                $bundle = "app*";
                            }
                        };
                    }
                }
            }
            if($bundle && !$translation->getBundle()){
                $normalized[] = $translation->getKey() .  " -> " . $bundle;
                $translation->setBundle($bundle);
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
                'result'     => true,
                'normalized' => $normalized,
            )
        );
    }

    /**
     * @Route("/normalize-bundle/{projectId}/{bundleName}/{keyStart}", name="normalize_bundle")
     * @ Method("POST")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function normalizeBundleAction(Request $request, Project $project, $bundleName, $keyStart = "*")
    {
        $this->init();
        $bundleName = preg_replace("/bundle$/i", "", $bundleName);
        $bundleName = ucfirst(strtolower($bundleName)) . "Bundle";

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
        $translations = $this->getTranslationRepository()->findBy(array('projectId' => $project->getId(), 'bundle'=>'' ));
        $normalized = array();

        foreach($translations as $translation){

            $key = $translation->getKey();
            if(($keyStart=="*") || preg_match("/^{$keyStart}/", $key)){
                $translation->setBundle($bundleName);
                $this->dm->persist($translation);
                $normalized[] = $key;
            }

        }

        $this->dm->flush();

        return $this->printResult(array(
                'result'     => true,
                'normalized' => $normalized,
            )
        );
    }

    /**
     * @Route("/change-bundle/{projectId}/bundle-origin/{bundleOrig}/to/{bundleDest}", name="normalize_bundle")
     * @ Method("POST")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function changeBundleAction(Request $request, Project $project, $bundleOrig, $bundleDest)
    {
        $this->init();

        $permissions = $this->translationsManager->getPermissionForUserAndProject($this->user, $project);
        $permissions = $permissions->getPermissions();

        if($permissions['general'] != Permission::OWNER){
            return $this->printResult(array(
                    'result' => false,
                    'reason' => 'not enough permissions to do this',
                )
            );
        }
        $bundleOrig = preg_replace("/bundle$/i", "", $bundleOrig);
        $bundleOrig = ucfirst(strtolower($bundleOrig)) . "Bundle";
        $bundleDest = preg_replace("/bundle$/i", "", $bundleDest);
        $bundleDest = ucfirst(strtolower($bundleDest)) . "Bundle";

        $managedLocales = explode(',',$project->getManagedLocales());

        /** @var Translation[] $translations */
        $translations = $this->getTranslationRepository()->findBy(array('projectId' => $project->getId(), 'bundle'=>$bundleOrig ));
        $normalized = array();

        foreach($translations as $translation){

            $translation->setBundle($bundleDest);
            $this->dm->persist($translation);
            $normalized[] = $key;

        }

        $this->dm->flush();

        return $this->printResult(array(
                'bundle-origin' => $bundleOrig,
                'bundle-dest'   => $bundleDest,
                'result'        => true,
                'normalized'    => $normalized,
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

        //var_dump($result); die;
        return $this->printResult($result);

    }

    /**
     * @Route("/upload-screenshot/{translationId}", name="upload_screenshot")
     * @Method("POST")
     * @ ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function uploadScrenshotAction(Request $request, $translationId)
    {
        $this->init();

        /** @var Translation $translation */
        $translation = $this->getTranslationRepository()->find($translationId);

        if(!$translation){
            throw $this->createNotFoundException();
        }
        $projectId = $translation->getProjectId();
        $project = $this->getProjectRepository()->find($projectId);

        $permissions = $this->translationsManager->getPermissionForUserAndProject($this->user, $project);

        // if permissions bla bla bla

        $directory = $this->root . "/web/uploads/{$projectId}/";
        $files = $request->files;
        foreach($files as $uploadedFile){
            break;  // I want only the first file, dont allow to upload more than one
        };
        /** @var UploadedFile $uploadedFile */
        $ext = $uploadedFile->getClientOriginalExtension();
        $baseName = uniqid();
        $name = $baseName . '.' . $ext;
        /** @var \Symfony\Component\HttpFoundation\File\File $file */
        $file = $uploadedFile->move($directory, $name);
        $destFile = $baseName . '.jpg';
        $name = $this->normalize($ext, $directory . $baseName, $directory . $destFile);

        $translation->setScreenshot($destFile);
        $this->dm->persist($translation);
        $this->dm->flush($translation);

        die('OK');
    }


    protected function normalize($ext, $file, $destImageFile, $width = null, $height = null, $quality = 90)
    {
        $imageFile = $file . '.' . $ext;
        switch($ext){
            case 'png':
            case 'PNG':
                $image    = imagecreatefrompng($imageFile);
                break;

            case 'jpg':
            case 'JPG':
            case 'jpeg':
            case 'JPEG':
                $image    = imagecreatefromjpeg($imageFile);
                break;

            case 'gif':
                $image    = imagecreatefromgif($imageFile);
                break;

            default:
                die("extension $ext don't recognized");
        }
        $w        = imagesx($image);
        $h        = imagesy($image);
        $width    = $width ? : $w;
        $height   = $height ? : $h;
        $newImage = imagecreatetruecolor($width, $height);
        unlink($imageFile);
        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $width, $height, $w, $h);
        imagejpeg($newImage, $destImageFile, $quality);
        chmod($destImageFile, 0777);

        return $destImageFile;
    }

    /**
     * @Route("/edit-screenshot/{translationId}", name="translation_screenshot")
     * @Template()
     */
    public function editScrenshotAction(Request $request, $translationId)
    {
        $this->init();

        /** @var Translation $translation */
        $translation = $this->getTranslationRepository()->find($translationId);

        //ldd($translation);

        if(!$translation){
            throw $this->createNotFoundException();
        }

        if($request->isMethod('POST')){

            $selection = $request->get('selection');
            $translation->setImageMaps($selection);
            $this->dm->persist($translation);
            $this->dm->flush($translation);
            return $this->printResult(
                array(
                    'result' => true,
                )
            );

        }

        //if($this->checkPermission(Permission::GENERAL_KEY, Permission::ADMIN_PERM)){

        $project = $this->getProjectRepository()->find($translation->getProjectId());

            return array(
                'translation' => $translation,
                'project'     => $project,
                'action'      => '',
                'permissions' => $this->user->getPermission(),
            );

        //}else{
            //return $this->createNotFoundException('message.not_eough_permissions');
        //}

    }


    /**
     * @Route("/select-screenshot/{translationId}", name="translation_select_screenshot")
     * @Template()
     */
    public function selectScrenshotAction(Request $request, $translationId)
    {
        $this->init();

        /** @var Translation $translation */
        $translation = $this->getTranslationRepository()->find($translationId);

        if(!$translation){
            throw $this->createNotFoundException();
        }

        if($request->isMethod('POST')){

            $file = $request->get('file');
            if(!preg_match("/^\/uploads\/\d+\/(?<file>.*?)$/", $file, $match)){
                return $this->printResult(
                    array(
                        'result' => false,
                        'message' => 'file not recognized ' . $file,
                    )
                );
            };
            $file = $match['file'];
            $translation->setScreenshot($file);
            $translation->setImageMaps(array());
            $this->dm->persist($translation);
            $this->dm->flush($translation);
            return $this->printResult(
                array(
                    'result' => true,
                )
            );

        }

        $projectId = $translation->getProjectId();
        $files = array();
        $finder = new Finder();
        $finder->files()->in($this->root . "/web/uploads/{$projectId}")->name('*.jpg');

        foreach($finder as $file){
            //$fileFull = $file->getRealpath();
            //$relativePath = $file->getRelativePath();
            //$fileName = $file->getRelativePathname();
            $files[] = '/uploads/' . $projectId . '/' . $file->getRelativePathname();
        }

        //if($this->checkPermission(Permission::GENERAL_KEY, Permission::ADMIN_PERM)){

        $project = $this->getProjectRepository()->find($translation->getProjectId());

            return array(
                'translation' => $translation,
                'project'     => $project,
                'action'      => '',
                'permissions' => $this->user->getPermission(),
                'files'       => $files,
            );

        //}else{
            //return $this->createNotFoundException('message.not_eough_permissions');
        //}

    }

    /**
     * @Route("/show-screenshot/{translationId}.jpg", name="translation_screenshot_show")
     */
    public function showScrenshotAction(Request $request, $translationId)
    {
        $this->init();

        /** @var Translation $translation */
        $translation = $this->getTranslationRepository()->find($translationId);
        $projectId   = $translation->getProjectId();
        $fileName    = $translation->getScreenshot();

        $image = imagecreatefromjpeg($this->root . "/web" . $fileName);

        $pink  = imagecolorallocate($image, 255, 105, 180);
        $white = imagecolorallocate($image, 255, 255, 255);
        //$green = imagecolorallocate($image, 132, 135, 28);

        $selection = $translation->getImageMaps();
        //var_dump($selection); die;

        list($w,$h) = getimagesize($this->root . "/web" . $fileName);

        $fx = $w/$selection['w'];
        $fy = $h/$selection['h'];
        $x1 = $selection['x1'] * $fx;
        $x2 = $selection['x2'] * $fx;
        $y1 = $selection['y1'] * $fy;
        $y2 = $selection['y2'] * $fy;

        for($i=1;$i<4;$i++){
            imagerectangle($image, $x1-$i, $y1-$i, $x2+$i, $y2+$i, $white);
        }
        for($i=0;$i<3;$i++){
            imagerectangle($image, $x1+$i, $y1+$i, $x2-$i, $y2-$i, $pink);
        }

//        $headers = array(
//            'Content-Type'     => 'image/jpeg',
//            'Content-Disposition' => 'inline; filename="'.$fileName.'"');
//        return new Response($image, 200, $headers);

        header('Content-Type: image/jpeg');

        imagejpeg($image);
        imagedestroy($image);
        die;

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



}
