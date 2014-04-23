<?php
namespace JLaso\TranslationsBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * @ORM\Entity(repositoryClass="JLaso\TranslationsBundle\Entity\Repository\PermissionRepository")
 * @ORM\Table(name="translations_permission")
 * @ORM\HasLifecycleCallbacks
 * @UniqueEntity(fields="project_id,user_id", message="errors.only_one_permission_per_project_and_user")
 */
class Permission
{
    const WRITE_PERM   = 'WRITE';
    const READ_PERM    = 'READ';
    const ADMIN_PERM   = 'ADMIN';
    const NONE_PERM    = 'NONE';

    const OWNER        = 'OWNER';
    const ADMIN        = 'ADMIN';

    const COLLABORATOR = 'COLLABORATOR';
    const INVITED      = 'INVITED';

    const LOCALE_KEY   = 'locale';
    const GENERAL_KEY  = 'general';
    const WILD_KEY     = '*';

    /**
     * @var integer $id
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var Project $project
     *
     * @ORM\ManyToOne(targetEntity="JLaso\TranslationsBundle\Entity\Project", inversedBy="projects")
     * @ORM\JoinColumn(name="project_id", referencedColumnName="id")
     */
    protected $project;

    /**
     * @var User $user
     *
     * @ORM\ManyToOne(targetEntity="JLaso\TranslationsBundle\Entity\User", inversedBy="permissions")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    protected $user;

    /**
     * @var string $permissions
     *
     * {
     *   'general': 'OWNER|ADMIN|READ',
     *   'locale': { 'es|en..|*': 'WRITE|READ', ... }
     * }
     *
     * @ORM\Column(name="permissions", type="string", length=255, nullable=true)
     */
    protected $permissions;

    /**
     * @var \DateTime $createdAt
     *
     * @ORM\Column(name="created_at", type="datetime")
     * @Assert\NotNull()
     * @Assert\DateTime()
     */
    protected $createdAt;

    public function __construct()
    {
        $this->createdAt   = new \DateTime();
        $this->permissions = json_encode(array());
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set createdAt
     *
     * @param  \DateTime $createdAt
     */
    public function setCreatedAt($createdAt = null)
    {
        $this->createdAt = ($createdAt == null) ? new \DateTime() : $createdAt;
    }

    /**
     * Get createdAt
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param Project $project
     */
    public function setProject($project)
    {
        $this->project = $project;
    }

    /**
     * @return Project
     */
    public function getProject()
    {
        return $this->project;
    }

    /**
     * @param User $user
     */
    public function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param array $permissions
     */
    public function setPermissions($permissions = array())
    {
        $this->permissions = json_encode($permissions);
    }

    /**
     * @param null $key
     *
     * @return array
     */
    public function getPermissions($key = null)
    {
        $permissions = json_decode($this->permissions, true);
        if(null===$key){
            return $permissions;
        }

        return isset($permissions[$key]) ? $permissions[$key] : null;
    }

    protected function allLanguageCodes()
    {
        $managed_locales = explode(",",$this->project->getManagedLocales());
        $managed_locales[] = self::WILD_KEY;
        
        return $managed_locales;
    }

    public function addPermission($permission, $language = null)
    {
        $permissions = $this->getPermissions();

        if(null === $language){
            if(!in_array($permission, self::getAllowedGeneralPermissions())){
                throw new \Exception(sprintf('addPermission: Permission %s not recognized', $permission));
            }
            $permissions[self::GENERAL_KEY] = $permission;
        }else{
            if(!in_array($permission, self::getAllowedLocalePermissions())){
                throw new \Exception(sprintf('addPermission: Permission %s not recognized', $permission));
            }
            if(!in_array($language, $this->allLanguageCodes())){
                throw new \Exception(sprintf('addPermission: Language %s not recognized', $language));
            }
            $permissions[self::LOCALE_KEY][$language] = $permission;
        }
        $this->setPermissions($permissions);

    }

    public function isOwner()
    {
        $permissions = $this->getPermissions();

        return ($permissions[self::GENERAL_KEY] == self::OWNER);
    }

    public function isCollaborator()
    {
        $permissions = $this->getPermissions();

        return ($permissions[self::GENERAL_KEY] == self::COLLABORATOR);
    }

    public function isInvited()
    {
        $permissions = $this->getPermissions();

        return ($permissions[self::GENERAL_KEY] == self::INVITED);
    }

    public function canManageLocaleAs($locale, $permission)
    {
        $permissions = $this->getPermissions();
        $permissions = $permissions[self::LOCALE_KEY];
        // see if the locale has specific permissions
        if(isset($permissions[$locale])){
            return $this->checkPermission($permissions[$locale], $permission);
        }else{
            // else if there are general permissions (WILD_KEY) for all locales
            if(isset($permissions[self::WILD_KEY])){
                return $this->checkPermission($permissions[self::WILD_KEY], $permission);
            }
        }

        return false;
    }

    public static function checkPermission($currentPermission, $permissionInquiried)
    {
        switch(true){
            case $currentPermission == self::ADMIN_PERM:
                return true;
                break;
            case $currentPermission == self::WRITE_PERM:
                return ($permissionInquiried != self::ADMIN_PERM);
                break;
            case $currentPermission == self::READ_PERM:
                return ($permissionInquiried == self::READ_PERM);
                break;
            case $currentPermission == self::NONE_PERM:
                return false;
                break;
            case !$currentPermission:
                return false;
                break;
            default:
                throw new \Exception('checkPermission: Permission ' . $currentPermission . ' not recognized');
        }
    }


    public static function getAllowedLocalePermissions()
    {
        return array(
            self::NONE_PERM,
            self::READ_PERM,
            self::WRITE_PERM,
            self::ADMIN_PERM,
        );
    }

    public static function getAllowedGeneralPermissions()
    {
        return array(
            self::OWNER,
            self::ADMIN,
            self::COLLABORATOR,
            self::INVITED,
        );
    }



}
