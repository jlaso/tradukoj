<?php

namespace JLaso\TranslationsBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Doctrine\Bundle\MongoDBBundle\Validator\Constraints\Unique as MongoDBUnique;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Catalog
 *
 * @MongoDBUnique(fields="projectId,catalog")
 * @MongoDB\Document(repositoryClass="JLaso\TranslationsBundle\Document\Repository\CatalogRepository")
 */
class Catalog
{
    /**
     * @MongoDB\Id
     */
    protected $id;

    /**
     *
     * @MongoDB\Int
     */
    protected $projectId;

    /**
     *
     * @MongoDB\String
     */
    protected $catalog;

    /**
     *
     * @MongoDB\Hash
     */
    protected $keys;

    /**
     * @MongoDB\Timestamp
     */
    protected $createdAt;

    /**
     * @MongoDB\Timestamp
     */
    protected $updatedAt;

    public function __construct()
    {
        $this->projectId = null;
        $this->keys      = null;
        $this->createdAt = new \MongoTimestamp();
        $this->updatedAt = new \MongoTimestamp();
        $this->catalog   = null;
    }

    /**
     * Get id
     *
     * @return id $id
     */
    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
    }

    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    public function getHash()
    {
        return base64_encode(sha1($this->getId()));
    }

    /**
     * @param mixed $catalog
     */
    public function setCatalog($catalog)
    {
        $this->catalog = $catalog;
    }

    /**
     * @return mixed
     */
    public function getCatalog()
    {
        return $this->catalog;
    }

    /**
     * @param mixed $keys
     */
    public function setKeys($keys)
    {
        $this->keys = $keys;
    }

    /**
     * @return mixed
     */
    public function getKeys()
    {
        return $this->keys;
    }

    /**
     * @param int $projectId
     */
    public function setProjectId($projectId)
    {
        $this->projectId = $projectId;
    }

    /**
     * @return int
     */
    public function getProjectId()
    {
        return $this->projectId;
    }

}
