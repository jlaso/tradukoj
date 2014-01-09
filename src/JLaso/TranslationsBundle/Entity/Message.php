<?php
namespace JLaso\TranslationsBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;

/**
 * @ORM\Entity(repositoryClass="JLaso\TranslationsBundle\Entity\Repository\MessageRepository")
 * @ORM\Table(name="translations_message")
 * @ORM\HasLifecycleCallbacks
 */
class Message
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
     * @var Key $key
     *
     * @ORM\ManyToOne(targetEntity="JLaso\TranslationsBundle\Entity\Key", inversedBy="messages")
     * @ORM\JoinColumn(name="key_id", referencedColumnName="id")
     */
    protected $key;

    /**
     * @var string $language
     *
     * @ORM\Column(name="language", type="string", length=8)
     */
    protected $language;

    /**
     * @var string $message
     *
     * @ORM\Column(name="message", type="text", nullable=true)
     */
    protected $message;

    /**
     * @var \DateTime $createdAt
     *
     * @ORM\Column(name="created_at", type="datetime")
     * @Assert\NotNull()
     * @Assert\DateTime()
     */
    protected $createdAt;

    /**
     * @var bool $approved
     *
     * @ORM\Column(name="approved", type="boolean")
     */
    protected $approved;

    /**
     * @var \DateTime $createdAt
     *
     * @ORM\Column(name="updated_at", type="datetime")
     * @Assert\NotNull()
     * @Assert\DateTime()
     */
    protected $updatedAt;

    /**
     * @var TranslationLog[] $translationLogs
     *
     * @ORM\ManyToMany(targetEntity="TranslationLog", inversedBy="messages", cascade={"persist"})
     * @ORM\JoinTable(name="translation_log_message")
     **/
    protected $translationLogs;

    public function __construct()
    {
        $this->createdAt       = new \DateTime();
        $this->translationLogs = new ArrayCollection();
        $this->approved        = false;
    }

    public function __toString()
    {
        return $this->key->getBundle() . '/' . $this->language;
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
     * @param Key $key
     */
    public function setKey(Key $key)
    {
        $this->key = $key;
    }

    /**
     * @return Key
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @param string $language
     */
    public function setLanguage($language)
    {
        $this->language = $language;
    }

    /**
     * @return string
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * @param string $message
     */
    public function setMessage($message)
    {
        $this->message = $message;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @param \DateTime $updatedAt
     */
    public function setUpdatedAt($updatedAt = null)
    {
        $this->updatedAt = $updatedAt ?: new \DateTime();
    }

    /**
     * @param TranslationLog[] $translationLogs
     */
    public function setTranslationLogs($translationLogs)
    {
        $this->translationLogs = $translationLogs;
    }

    /**
     * @return TranslationLog[]
     */
    public function getTranslationLogs()
    {
        return $this->translationLogs;
    }

    /**
     * @param boolean $approved
     */
    public function setApproved($approved)
    {
        $this->approved = $approved;
    }

    /**
     * @return boolean
     */
    public function getApproved()
    {
        return $this->approved;
    }


//    /**
//     * @ORM\PrePersist
//     */
//    public function onPrePersist()
//    {
//        $this->updatedAt = new \DateTime();
//    }
//
//    /**
//     * @ORM\PreUpdate
//     */
//    public function onPreUpdate()
//    {
//        $this->updatedAt = new \DateTime();
//    }

    public function asArray()
    {
        return array(
            'id'         => $this->id,
            'key_id'     => $this->key->getId(),
            'key'        => $this->key->getKey(),
            'bundle'     => $this->key->getBundle(),
            'catalog'    => $this->key->getCatalog(),
            'language'   => $this->language,
            'message'    => $this->message,
            'created_at' => $this->createdAt->format('c'),
            'updates_at' => $this->updatedAt->format('c'),
        );
    }
}
