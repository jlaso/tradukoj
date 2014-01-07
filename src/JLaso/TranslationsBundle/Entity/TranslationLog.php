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

    /**
     * @var integer $id
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var integer $messageId
     *
     * @ORM\Column(name="message_id", type="integer")
     */
    protected $messageId;

    /**
     * @var Message $message
     *
     * @ORM\ManyToOne(targetEntity="JLaso\TranslationsBundle\Entity\Message", inversedBy="translation_logs")
     * @ORM\JoinColumn(name="message_id", referencedColumnName="id")
     */
    protected $message;

    /**
     * @var string $actionType
     *
     * @ORM\Column(name="action_type", type="string", length=255)
     */
    protected $actionType;

    /**
     * @ORM\Column(name="message_copy", type="text", nullable=true)
     */
    protected $messageCopy;

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
        $this->createdAt       = new \DateTime();
    }

    public function __toString()
    {
        return sprintf('%s %d', $this->actionType, $this->messageId);
    }

    public static function getActionTypes()
    {
        return array(
            self::TRANSLATE,
            self::APPROVE,
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
     * @param int $messageId
     */
    public function setMessageId($messageId)
    {
        $this->messageId = $messageId;
    }

    /**
     * @return int
     */
    public function getMessageId()
    {
        return $this->messageId;
    }

    /**
     * @param Message $message
     */
    public function setMessage(Message $message)
    {
        $this->message = $message;
    }

    /**
     * @return Message
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @param mixed $messageCopy
     */
    public function setMessageCopy($messageCopy)
    {
        $this->messageCopy = $messageCopy;
    }

    /**
     * @return mixed
     */
    public function getMessageCopy()
    {
        return $this->messageCopy;
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


}
