<?php
namespace JLaso\TranslationsBundle\Controller;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use JLaso\TranslationsBundle\Document\File;
use JLaso\TranslationsBundle\Document\Repository\TranslatableDocumentRepository;
use JLaso\TranslationsBundle\Document\TranslatableDocument;
use JLaso\TranslationsBundle\Entity\Permission;
use JLaso\TranslationsBundle\Entity\Project;
use JLaso\TranslationsBundle\Entity\TranslationLog;
use JLaso\TranslationsBundle\Entity\Repository\LanguageRepository;
use JLaso\TranslationsBundle\Entity\Repository\TranslationLogRepository;
use JLaso\TranslationsBundle\Entity\Repository\ProjectRepository;
use JLaso\TranslationsBundle\Entity\Repository\UserRepository;
use JLaso\TranslationsBundle\Entity\Repository\PermissionRepository;
use JLaso\TranslationsBundle\Entity\User;
use JLaso\TranslationsBundle\Exception\AclException;
use JLaso\TranslationsBundle\Form\Type\UserProfileType;
use JLaso\TranslationsBundle\Service\MailerService;
use JLaso\TranslationsBundle\Service\Manager\TranslationsManager;
use JLaso\TranslationsBundle\Service\RestService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ODM\MongoDB\DocumentManager;
use JLaso\TranslationsBundle\Document\Repository\TranslationRepository;
use JLaso\TranslationsBundle\Document\Translation;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use JLaso\TranslationsBundle\Tools\ImageTools;


/**
 * @Cache(maxage="0")
 */
class UsersController extends BaseController
{
    const AVATAR_LIB = 'bundles/translations/avatar-lib';

    /** @var  EntityManager */
    protected $em;
    protected $config;
    /** @var  TranslationsManager */
    protected $translationsManager;
    /** @var User */
    protected $user;
    /** @var  Translator */
    protected $translator;

    protected function init()
    {
        $this->em                  = $this->container->get('doctrine.orm.default_entity_manager');
        $this->config              = $this->container->getParameter('jlaso_translations');
        $this->translationsManager = $this->container->get('jlaso.translations_manager');
        $this->user                = $this->get('security.context')->getToken()->getUser();
        $this->translator          = $this->container->get('translator');
    }

    /**
     * @Route("/users/{projectId}", name="users_index")
     * @Template()
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function indexAction(Project $project, Request $request)
    {
        $session = $request->getSession();

        $this->init();
        /** @var Permission $permission */
        $permission = $this->translationsManager->getPermissionForUserAndProject($this->user, $project);

        if(!$permission || ($permission->getPermissions(Permission::GENERAL_KEY) != Permission::OWNER) ){
            throw new AclException($this->translator->trans('error.acl.not_enough_permissions_to_manage_this_project'));
        }

        $managedLocales = explode(',',$project->getManagedLocales());

        /** @var Permission[] $permissions */
        $permissions = $this->getPermissionRepository()->findBy(array('project'=>$project));

        $usersData = array();
        foreach($permissions as $perm){
            $user = $perm->getUser();
            $usersData[] = array(
                'id'          => $user->getId(),
                'name'        => $user->getName(),
                'email'       => $user->getEmail(),
                'createdAt'   => $user->getCreatedAt(),
                'active'      => $user->getActive(),
                'permissions' => $this->expandPermissions($managedLocales, $perm->getPermissions()),
            );
        }

        // ldd($usersData, $permission->getPermissions());

        return array(
            'action'         => 'users',
            'project'        => $project,
            'permissions'    => $permission->getPermissions(),
            'users'          => $usersData,
            'managedLocales' => $managedLocales,
        );
    }

    /**
     * @Route("/my-profile", name="user_profile")
     * @Template()
     */
    public function myProfileAction(Request $request)
    {
        $session = $request->getSession();

        $this->init();

        $oldPassword = $this->user->getPassword();
        $form = $this->createForm(new UserProfileType(), $this->user);

        if($request->isMethod("POST")){

            $form->submit($request);
            if($form->isValid()){

                if($this->user->getPassword()){
                    /** @var EncoderFactory $encoderFactory */
                    $encoderFactory = $this->container->get('security.encoder_factory');
                    $encoder = $encoderFactory->getEncoder($this->user);
                    $this->user->setPassword($encoder->encodePassword($this->user->getPassword(), $this->user->getSalt()));
                }else{
                    $this->user->setPassword($oldPassword);
                }

                $this->em->persist($this->user);
                $this->em->flush($this->user);

                $this->addSuccessFlash("messages.profile_saved_successfully");
            }else{
                $this->addNoticeFlash("messages.profile_not_valid_form");
            }

        }

        $finder = new Finder();
        $rootDir = dirname($this->get('kernel')->getRootDir());
        $finder->files()->in($rootDir . '/web/' . self::AVATAR_LIB)->name('*.png');

        $avatarFiles = array();
        foreach($finder->files() as $file){
            //$fullFileName = $file->getRealpath();
            $relativePath = $file->getRelativePath();
            $fileName = $file->getRelativePathname();

            $avatarFiles[] = '/'.self::AVATAR_LIB.'/' . $fileName;
        }

        return array(
            'action'      => 'my-profile',
            'form'        => $form->createView(),
            'project'     => null,
            'error'       => null,
            'avatarFiles' => $avatarFiles,
        );
    }


    /**
     * @Route("/add-user/{projectId}", name="users_add_new_user")
     * @Template()
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function newUserAction(Project $project, Request $request)
    {
        $session = $request->getSession();

        $this->init();
        /** @var Permission $permission */
        $permission = $this->translationsManager->getPermissionForUserAndProject($this->user, $project);

        if(!$permission || ($permission->getPermissions(Permission::GENERAL_KEY) != Permission::OWNER) ){
            throw new AclException($this->translator->trans('error.acl.not_enough_permissions_to_manage_this_project'));
        }

        $email = $request->get('email');
        $user = $this->getUserRepository()->findOneBy(array('email'=>$email));
        if(!$user){
            $user = new User();
            $user->setEmail($email);
//            //$user->set

            /** @var MailerService $mailer */
            $mailer = $this->get('jlaso.mailer_service');
            try{
                $send = $mailer->sendWelcomeMessage($user);
            }catch(\Exception $e){

            }
            if(is_string($send)){
                $this->addNoticeFlash($send);
            }else{
                $this->addNoticeFlash('user.permissions.user_invited');
            }
        }else{
            $managedLocales = explode(',',$project->getManagedLocales());

            /** @var Permission[] $permissions */
            $permissions = $this->getPermissionRepository()->findBy(array('project'=>$project));

            $usersData = array();
            $alreadyExists = false;
            foreach($permissions as $perm){
                $currentUser = $perm->getUser();
                $usersData[] = array(
                    'id'          => $currentUser->getId(),
                    'name'        => $currentUser->getName(),
                    'email'       => $currentUser->getEmail(),
                    'createdAt'   => $currentUser->getCreatedAt(),
                    'active'      => $currentUser->getActive(),
                    'permissions' => $this->expandPermissions($managedLocales, $perm->getPermissions()),
                );
                if($currentUser->getEmail() == $email){
                    $this->addNoticeFlash('user.permissions.user_already_exists');
                    $alreadyExists = true;
                }
            }

            if(!$alreadyExists){
                $permission = new Permission();
                $permission->setUser($user);
                $permission->setProject($project);
                $permission->addPermission(Permission::COLLABORATOR);
                // Give permission to write in all languages
                $permission->addPermission(Permission::READ_PERM, '*');
                $this->em->persist($permission);
                $this->em->flush();
            }
        }

        // ldd($usersData, $permission->getPermissions());
        return $this->redirect($this->generateUrl('users_index', array('projectId'=>$project->getId())));
//        return array(
//            'action'         => 'users',
//            'project'        => $project,
//            'permissions'    => $permission->getPermissions(),
//            'users'          => $usersData,
//            'managedLocales' => $managedLocales,
//        );
    }

    /**
     * @Route("/remove-user/{projectId}/{email}", name="users_remove_user")
     * @Template()
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function removeUserAction(Project $project, $email, Request $request)
    {
        $session = $request->getSession();

        $this->init();
        /** @var Permission $permission */
        $permission = $this->translationsManager->getPermissionForUserAndProject($this->user, $project);

        if(!$permission || ($permission->getPermissions(Permission::GENERAL_KEY) != Permission::OWNER) ){
            throw new AclException($this->translator->trans('error.acl.not_enough_permissions_to_manage_this_project'));
        }

        $user = $this->getUserRepository()->findOneBy(array('email'=>$email));
        if(!$user){
            $this->addNoticeFlash("User doesn't exists yet in our system");
        }else{
            $managedLocales = explode(',',$project->getManagedLocales());

            /** @var Permission[] $permissions */
            $permissions = $this->getPermissionRepository()->findBy(array('project'=>$project));

            $usersData = array();
            $exists = false;
            foreach($permissions as $perm){
                $currentUser = $perm->getUser();
                if($currentUser->getEmail() != $email){/*
                    $usersData[] = array(
                        'id'          => $currentUser->getId(),
                        'name'        => $currentUser->getName(),
                        'email'       => $currentUser->getEmail(),
                        'createdAt'   => $currentUser->getCreatedAt(),
                        'active'      => $currentUser->getActive(),
                        'permissions' => $this->expandPermissions($managedLocales, $perm->getPermissions()),
                    );*/
                }else{
                    $exists = true;
                    $this->em->remove($perm);
                }
            }

            if($exists){
                $this->em->flush();
            }
        }

        // ldd($usersData, $permission->getPermissions());
        return $this->redirect($this->generateUrl('users_index', array('projectId'=>$project->getId())));
//        return array(
//            'action'         => 'users',
//            'project'        => $project,
//            'permissions'    => $permission->getPermissions(),
//            'users'          => $usersData,
//            'managedLocales' => $managedLocales,
//        );
    }

    protected function expandPermissions($managedLocales, $permissionsArray)
    {
        $result = array();
        foreach($managedLocales as $locale)
        {
            if(isset($permissionsArray[Permission::LOCALE_KEY][$locale])){
                $result[$locale] = $permissionsArray[Permission::LOCALE_KEY][$locale];
            }else{
                if(isset($permissionsArray[Permission::LOCALE_KEY][Permission::WILD_KEY])){
                    $result[$locale] = $permissionsArray[Permission::LOCALE_KEY][Permission::WILD_KEY];
                }else{
                    $result[$locale] = Permission::NONE_PERM;
                }
            }
        }

        return $result;
    }

    /**
     * @Route("/change-user-permissions/{projectId}", name="change_user_permission")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function changeUserPermissionsAction(Project $project, Request $request)
    {
        $session = $request->getSession();

        $this->init();
        /** @var Permission $permission */
        $permission = $this->translationsManager->getPermissionForUserAndProject($this->user, $project);

        if(!$permission || ($permission->getPermissions(Permission::GENERAL_KEY) != Permission::OWNER) ){
            return $this->exception('error.acl.not_enough_permissions_to_manage_this_project');
        }

        $managedLocales = explode(',',$project->getManagedLocales());

        $userId = $request->get('user');
        $locale = $request->get('locale');
        if(!in_array($locale, $managedLocales)){
            return $this->exception(sprintf('the locale %s is not managed by this project', $locale));
        }
        $newPermission = $request->get('permission');
        if(!in_array($newPermission, Permission::getAllowedLocalePermissions())){
            return $this->exception(sprintf('the permission %s is not recognized', $newPermission));
        }

        $user = $this->getUserRepository()->find($userId);
        if(!$user){
            return $this->exception('error.permissions.no_user_found');
        }

        /** @var Permission $permission */
        $permission = $this->getPermissionRepository()->findPermissionForProjectAndUser($project, $user);
        if(!$permission){
            return $this->exception('error.permissions.no_permissions_for_this_user');
        }

        $permission->addPermission($newPermission, $locale);
        $this->em->persist($permission);
        $this->em->flush($permission);

        return $this->resultOk(array(
                'locale'     => $locale,
                'user'       => $user->getId(),
                'permission' => $newPermission,
            )
        );
    }



    /**
     * @Route("/user-change-avatar", name="user_change_avatar")
     */
    public function userChangeAvatarAction(Request $request)
    {
        $session = $request->getSession();

        $this->init();

        $avatar = $request->get('avatar');

        $this->user->setAvatarUrl($avatar);
        $this->em->persist($this->user);
        $this->em->flush($this->user);

        return $this->resultOk(array(
                'avatar' => $avatar,
            )
        );
    }

    /**
     * @Route("/user-upload-avatar", name="user_upload_avatar")
     */
    public function userUploadAvatarAction(Request $request)
    {
        $session = $request->getSession();

        $this->init();

        $directory = dirname($this->get('kernel')->getRootDir()) . '/web/uploads';

        $name = sprintf("avatar-%06d.png", $this->user->getId());
        foreach($request->files as $file){
            $file->move($directory, $name);
            break;
        }

        $avatar = "/uploads/" . $name;

        ImageTools::resizeImage($directory . '/' . $name, 100, 100, array('action' => ImageTools::COPY_ORIG_DEST));

        $this->user->setAvatarUrl($avatar);
        $this->em->persist($this->user);
        $this->em->flush($this->user);

        return $this->redirect($this->generateUrl('user_profile'));
    }


    /**
     * @return UserRepository
     */
    protected function getUserRepository()
    {
        return $this->em->getRepository('TranslationsBundle:User');
    }

    /**
     * @return PermissionRepository
     */
    protected function getPermissionRepository()
    {
        return $this->em->getRepository('TranslationsBundle:Permission');
    }


}
