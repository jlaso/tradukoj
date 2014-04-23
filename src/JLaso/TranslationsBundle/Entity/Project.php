<?php
namespace JLaso\TranslationsBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="JLaso\TranslationsBundle\Entity\Repository\ProjectRepository")
 * @ORM\Table(name="translations_project")
 * @ORM\HasLifecycleCallbacks
 */
class Project
{
    /**
     * @var integer $id
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string $project
     *
     * @ORM\Column(name="project", type="string", length=40)
     */
    protected $project;

    /**
     * @var string $name
     *
     * @ORM\Column(name="name", type="string", length=255)
     */
    protected $name;

    /**
     * @var string $apiKey
     *
     * @ORM\Column(name="api_key", type="string", length=40, nullable=true)
     */
    protected $apiKey;

    /**
     * @var string $apiSecret
     *
     * @ORM\Column(name="api_secret", type="string", length=40, nullable=true)
     */
    protected $apiSecret;

    /**
     * @var \DateTime $createdAt
     *
     * @ORM\Column(name="created_at", type="datetime")
     * @Assert\NotNull()
     * @Assert\DateTime()
     */
    protected $createdAt;

    /**
     * @var string $managedLocales
     *
     * @ORM\Column(name="managed_locales", type="string", length=255)
     */
    protected $managedLocales;

//    /**
//     * @ORM\OneToMany(targetEntity="JLaso\TranslationsBundle\Entity\Key", mappedBy="project", cascade={"remove"})
//     */
//    protected $keys;

    /**
     * @ORM\OneToMany(targetEntity="JLaso\TranslationsBundle\Entity\Permission", mappedBy="project", cascade={"remove"})
     */
    protected $permissions;

    /**
     * @ORM\ManyToMany(targetEntity="User", mappedBy="projects")
     **/
    protected $users;

    public function __construct()
    {
        $this->createdAt      = new \DateTime();
//        $this->keys           = new ArrayCollection();
        $this->managedLocales = 'en';
        $this->users          = new ArrayCollection();
        $this->permissions    = new ArrayCollection();
        $this->apiKey         = uniqid();
    }

    public function __toString()
    {
        return $this->project;
    }

    /**
     * Get id
     *
     * @return int
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
     * @param string $apiKey
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * @return string
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * @param string $apiSecret
     */
    public function setApiSecret($apiSecret)
    {
        $this->apiSecret = $apiSecret;
    }

    /**
     * @return string
     */
    public function getApiSecret()
    {
        return $this->apiSecret;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $project
     */
    public function setProject($project)
    {
        $this->project = $project;
    }

    /**
     * @return string
     */
    public function getProject()
    {
        return $this->project;
    }

//    /**
//     * @param ArrayCollection $keys
//     */
//    public function setKeys($keys)
//    {
//        $this->keys = $keys;
//    }
//
//    /**
//     * @return Key[]
//     */
//    public function getKeys()
//    {
//        return $this->keys;
//    }

    /**
     * @param string $managedLocales
     */
    public function setManagedLocales($managedLocales)
    {
        $this->managedLocales = $managedLocales;
    }

    /**
     * @return string
     */
    public function getManagedLocales()
    {
        return $this->managedLocales;
    }

    /**
     * @param User[] $users
     */
    public function setUsers($users)
    {
        $this->users = $users;
    }

    /**
     * @return User[]
     */
    public function getUsers()
    {
        return $this->users;
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

}
