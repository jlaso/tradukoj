<?php

namespace JLaso\TranslationsBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * @ORM\Entity(repositoryClass="JLaso\TranslationsBundle\Entity\Repository\UserRepository")
 * @ORM\Table(name="translations_user")
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="type", type="string")
 * @UniqueEntity(fields="email", message="Email yet exists!")
 */
class User implements UserInterface, EquatableInterface
{
    const ROLE_ADMIN      = 'ROLE_ADMIN';
    const ROLE_DEVELOPER  = 'ROLE_DEVELOPER';
    const ROLE_TRANSLATOR = 'ROLE_TRANSLATOR';

    /**
     * @var integer $id
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     *
     */
    protected $id;

    /**
     * @var string $name
     *
     * @ORM\Column(name="name", type="string", length=255)
     */
    protected $name;

    /**
     * @var string $surname
     *
     * @ORM\Column(name="surname", type="string", length=255)
     */
    protected $surname;

    /**
     * @var string $email
     *
     * @ORM\Column(name="email", type="string", length=255, unique=true)
     * @Assert\Email()
     */
    protected $email;

    /**
     * @var string $username
     *
     * @ORM\Column(name="avatar_url", type="string", length=255, unique=true, nullable=true)
     */
    protected $username;

    /**
     * @var string $avatarUrl
     *
     * @ORM\Column(name="username", type="string", length=255, nullable=true)
     */
    protected $avatarUrl;

    /**
     * @var string $password
     *
     * @ORM\Column(name="password", type="string", length=255)
     * @Assert\Length(min = 1)
     */
    protected $password;

    /**
     * Random string sent to the user email address in order to verify it
     *
     * @ORM\Column(name="confirmation_token", type="string", length=255, nullable=true)
     * 
     * @var string
     */
    protected $confirmationToken;

    /**
     * @Assert\Length(min = 1, max = 20, minMessage="Password too short. Minimum 6 characters|Password too short. Minimum 6 characters", maxMessage="Password too long. Maximum 20 characters|Password too long. Maximum 20 characters")
     */
    private $editPassword;

    /**
     * @var string $salt
     *
     * @ORM\Column(name="salt", type="string", length=255, nullable=true)
     */
    protected $salt;

    /**
     * @var array $roles
     *
     * @ORM\Column(name="roles", type="array")
     */
    protected $roles;

    /**
     * @var \DateTime $createdAt
     *
     * @ORM\Column(name="created_at", type="datetime")
     * @Assert\DateTime()
     */
    protected $createdAt;

    /**
     * @var Project[] $projects
     *
     * @ORM\ManyToMany(targetEntity="Project", inversedBy="users", cascade={"persist"})
     * @ORM\JoinTable(name="translations_user_project")
     **/
    protected $projects;

    /**
     * @ORM\OneToMany(targetEntity="JLaso\TranslationsBundle\Entity\Permission", mappedBy="user", cascade={"remove"})
     */
    protected $permissions;

    public function __construct()
    {
        $this->roles       = array();
        $this->salt        = base_convert(sha1(uniqid(mt_rand(), true)), 16, 36);
        $this->createdAt   = new \DateTime();
        $this->actived     = false;
        $this->name        = "";
        $this->surname     = "";
        $this->projects    = new ArrayCollection();
        $this->permissions = new ArrayCollection();
    }

    /**
     * @return bool
     */
    public function isAdmin()
    {
        return in_array('ROLE_ADMIN', $this->getRoles());
    }

    public function prettyRole()
    {
        switch(true){
            case(in_array('ROLE_ADMIN', $this->getRoles())):
                return 'ADMIN';
                break;
            case(in_array('ROLE_DEVELOPER', $this->getRoles())):
                return 'DEVELOPER';
                break;
            case(in_array('ROLE_TRANSLATOR', $this->getRoles())):
                return 'TRANSLATOR';
                break;
        }
    }

    /**
     * Set password
     *
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    /**
     * Get password
     *
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Get salt
     *
     * @return string
     */
    public function getSalt()
    {
        return $this->salt;
    }

    /**
     * Set roles
     *
     * @param  array $roles
     * @return User
     */
    public function setRoles($roles)
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * Get roles
     *
     * @return array
     */
    public function getRoles()
    {
        return $this->roles;
    }

    public function addRole($role)
    {
        $this->roles[] = $role;
    }

    public function removeRole($role)
    {
        $ind_role = array_search($role, $this->roles);
        if ($ind_role !== false) {
            unset($this->roles[$ind_role]);
        }
    }

    public function hasRole($role)
    {
        return in_array($role, $this->roles);
    }

    public function __toString()
    {
        return $this->fullName();
    }

    public function fullName()
    {
        return $this->name.' '.$this->surname;
    }

    /**
     * Métodos requeridos por la interfaz UserInterface
     */
    public function isEqualTo(UserInterface $user)
    {
        return $this->getEmail() == $user->getEmail();
    }

    public function eraseCredentials()
    {
    }

    public function serialize()
    {
       return serialize($this->getId());
    }

    public function unserialize($data)
    {
        $this->id = unserialize($data);
    }

    public function isActived()
    {
        return $this->actived;
    }

    /**
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param  string $name
     * @return User
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    public function getNameOrEmail()
    {
        return $this->name ?: $this->email;
    }

    /**
     * @param  string $surname
     * @return User
     */
    public function setSurname($surname)
    {
        $this->surname = $surname;

        return $this;
    }

    /**
     * @return string
     */
    public function getSurname()
    {
        return $this->surname;
    }

    /**
     * @param  string $email
     * @return User
     */
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param  boolean $actived
     * @return User
     */
    public function setActived($actived)
    {
        $this->actived = $actived;

        return $this;
    }

    /**
     * @return boolean
     */
    public function getActived()
    {
        return $this->actived;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param  \DateTime $createdAt
     * @return User
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getEditPassword()
    {
         return $this->editPassword;
    }

    public function setEditPassword($password)
    {

         $this->editPassword = $password;
    }

    /**
     * Set salt
     *
     * @param string $salt
     * @return User
     */
    public function setSalt($salt)
    {
        $this->salt = $salt;

        return $this;
    }

    /**
     * @param string $confirmationToken
     */
    public function setConfirmationToken($confirmationToken)
    {
        $this->confirmationToken = $confirmationToken;
    }

    /**
     * @return string
     */
    public function getConfirmationToken()
    {
        return $this->confirmationToken;
    }

    /**
     * @param string $username
     */
    public function setUsername($username)
    {
        $this->username = $username;
    }

    /**
     * @return string
     */
    public function getUsername()
    {
        return $this->username ?: $this->email;
    }

    /**
     * @param Project[] $projects
     */
    public function setProjects($projects)
    {
        $this->projects = $projects;
    }

    /**
     * @return Project[]
     */
    public function getProjects()
    {
        return $this->projects;
    }

    /**
     * @param Permission[] $permissions
     */
    public function setPermissions($permissions)
    {
        $this->permissions = $permissions;
    }

    /**
     * @return Permission[]
     */
    public function getPermissions()
    {
        return $this->permissions;
    }

    /**
     * @param string $avatarUrl
     */
    public function setAvatarUrl($avatarUrl)
    {
        $this->avatarUrl = $avatarUrl;
    }

    /**
     * @return string
     */
    public function getAvatarUrl()
    {
        return $this->avatarUrl;
    }


}