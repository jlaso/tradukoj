<?php
namespace JLaso\TranslationsBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="JLaso\TranslationsBundle\Entity\Repository\TranslationLogRepository")
 * @ORM\Table(name="translation_log")
 */
class TranslationLog
{
    const TRANSLATE  = 'translate';
    const APPROVE    = 'approve';
    const DISAPPROVE = 'disapprove';
    const NEW_KEY    = 'new_key';
    const CHANGE_KEY = 'change_key';
    const REMOVE_KEY = 'remove_key';

    const TRANSLATIONS_GROUP = 'trans-keys';
    const DOCUMENTS_GROUP    = 'trans-docs';

    /**
     * @var integer $id
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var integer $translationId
     *
     * @ORM\Column(name="translation_id", type="string", length=255)
     */
    protected $translationId;

    /**
     * @var string $locale
     *
     * @ORM\Column(name="`group`", type="string", length=20)
     */
    protected $group;

    /**
     * @var string $locale
     *
     * @ORM\Column(name="locale", type="string", length=20)
     */
    protected $locale;

    /**
     * @var string $actionType
     *
     * @ORM\Column(name="action_type", type="string", length=255)
     */
    protected $actionType;

    /**
     * @ORM\Column(name="message", type="text", nullable=true)
     */
    protected $message;

    /**
     * @var User $user
     *
     * @ORM\ManyToOne(targetEntity="JLaso\TranslationsBundle\Entity\User", inversedBy="users")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    protected $user;

    /**
     * @var \Datetime $createdAt
     *
     * @ORM\Column(name="created_at", type="datetime")
     * @Assert\NotNull()
     * @Assert\DateTime()
     */
    protected $createdAt;

    public function __construct()
    {
        $this->group     = self::TRANSLATIONS_GROUP;
        $this->createdAt = new \DateTime();
    }

    public function __toString()
    {
        return sprintf('%s:%s-%s', $this->actionType, $this->translationId, $this->locale);
    }

    public static function getActionTypes()
    {
        return array(
            self::TRANSLATE,
            self::APPROVE,
            self::DISAPPROVE,
        );
    }


    //////////////////////
    // GETTER Y SETTERS //
    //////////////////////

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
     * @return Order
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = ($createdAt == null) ? new \DateTime() : $createdAt;

        return $this;
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
     * @param string $actionType
     */
    public function setActionType($actionType)
    {
        $this->actionType = $actionType;
    }

    /**
     * @return string
     */
    public function getActionType()
    {
        return $this->actionType;
    }


    /**
     * @param User $user
     */
    public function setUser(User $user)
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
     * @param mixed $locale
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;
    }

    /**
     * @return mixed
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @param mixed $message
     */
    public function setMessage($message)
    {
        $this->message = $message;
    }

    /**
     * @return mixed
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @param int $translationId
     */
    public function setTranslationId($translationId)
    {
        $this->translationId = $translationId;
    }

    /**
     * @return int
     */
    public function getTranslationId()
    {
        return $this->translationId;
    }

    /**
     * @param string $group
     */
    public function setGroup($group)
    {
        $this->group = $group;
    }

    /**
     * @return string
     */
    public function getGroup()
    {
        return $this->group;
    }



}
