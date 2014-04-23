<?php
namespace JLaso\TranslationsBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="JLaso\TranslationsBundle\Entity\Repository\KeyRepository")
 * @ORM\Table(name="translations_key")
 * @ORM\HasLifecycleCallbacks
 */
class Key
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
     * @ORM\ManyToOne(targetEntity="JLaso\TranslationsBundle\Entity\Project", inversedBy="keys")
     * @ORM\JoinColumn(name="project_id", referencedColumnName="id")
     */
    protected $project;

    /**
     * @var Project $bundle
     *
     * @ORM\Column(name="bundle", type="string", length=40)
     */
    protected $bundle;

    /**
     * @var Project $catalog
     *
     * @ORM\Column(name="catalog", type="string", length=60)
     */
    protected $catalog;

    /**
     * @var string $key
     *
     * @ORM\Column(name="`key`", type="string", length=255)
     */
    protected $key;

    /**
     * @var string $comment
     *
     * @ORM\Column(name="comment", type="text", nullable=true)
     */
    protected $comment;

    /**
     * @var \DateTime $createdAt
     *
     * @ORM\Column(name="created_at", type="datetime")
     * @Assert\NotNull()
     * @Assert\DateTime()
     */
    protected $createdAt;

    /**
     * @var \DateTime $createdAt
     *
     * @ORM\Column(name="updated_at", type="datetime")
     * @Assert\NotNull()
     * @Assert\DateTime()
     */
    protected $updatedAt;

    /**
     * @var ArrayCollection $messages
     *
     * @ORM\OneToMany(targetEntity="JLaso\TranslationsBundle\Entity\Message", mappedBy="key", cascade={"remove"})
     */
    protected $messages;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->messages  = new ArrayCollection();
    }

    public function __toString()
    {
        return $this->project->getName() . '.' . $this->bundle;
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
     * @param \JLaso\TranslationsBundle\Entity\Project $bundle
     */
    public function setBundle($bundle)
    {
        $this->bundle = $bundle;
    }

    /**
     * @return \JLaso\TranslationsBundle\Entity\Project
     */
    public function getBundle()
    {
        return $this->bundle;
    }

    /**
     * @param string $comment
     */
    public function setComment($comment)
    {
        $this->comment = $comment;
    }

    /**
     * @return string
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * @param string $key
     */
    public function setKey($key)
    {
        $this->key = $key;
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @param \JLaso\TranslationsBundle\Entity\Project $project
     */
    public function setProject($project)
    {
        $this->project = $project;
    }

    /**
     * @return \JLaso\TranslationsBundle\Entity\Project
     */
    public function getProject()
    {
        return $this->project;
    }

    /**
     * @param ArrayCollection $messages
     */
    public function setMessages($messages)
    {
        $this->messages = $messages;
    }

    /**
     * @return Message[]
     */
    public function getMessages()
    {
        return $this->messages;
    }

    /**
     * @param \DateTime $updatedAt
     */
    public function setUpdatedAt($updatedAt = null)
    {
        $this->updatedAt = $updatedAt ?: new \DateTime();
    }

    /**
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @param \JLaso\TranslationsBundle\Entity\Project $catalog
     */
    public function setCatalog($catalog)
    {
        $this->catalog = $catalog;
    }

    /**
     * @return \JLaso\TranslationsBundle\Entity\Project
     */
    public function getCatalog()
    {
        return $this->catalog;
    }

}
