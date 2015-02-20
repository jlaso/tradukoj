<?php
/**
 * Created by PhpStorm.
 * User: jl
 * Date: 10/01/14
 * Time: 19:48
 */
namespace JLaso\TranslationsBundle\Model;

class Translation
{

    /**
     * @var string
     */
    protected $locale;
    /**
     * @var string
     */
    protected $message;
    /**
     * @var \DateTime
     */
    protected $updatedAt;
    /**
     * @var boolean
     */
    protected $approved;

    public function __construct($locale = null, $message = null, $updatedAt = null, $approved = false)
    {
        $this->locale    = $locale;
        $this->message   = $message;
        $this->updatedAt = $updatedAt ?: new \DateTime();
        $this->approved  = $approved;
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

    /**
     * @param \DateTime $updatedAt
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
    }

    /**
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }
}
