<?php
namespace JLaso\TranslationsBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="JLaso\TranslationsBundle\Entity\Repository\PermissionRepository")
 * @ORM\Table(name="translations_permission")
 * @ORM\HasLifecycleCallbacks
 */
class Permission
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
     * @var Project $project
     *
     * @ORM\ManyToOne(targetEntity="JLaso\TranslationsBundle\Entity\Project", inversedBy="projects")
     * @ORM\JoinColumn(name="project_id", referencedColumnName="id")
     */
    protected $project;

    /**
     * @var User $user
     *
     * @ORM\ManyToOne(targetEntity="JLaso\TranslationsBundle\Entity\User", inversedBy="users")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    protected $user;

    /**
     * @var string $permissions
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


}
