<?php

namespace JLaso\TranslationsBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Doctrine\Bundle\MongoDBBundle\Validator\Constraints\Unique as MongoDBUnique;

/**
 * TranslatableDocument
 *
 * @MongoDBUnique(fields="projectId,document")
 * @MongoDB\Document(repositoryClass="JLaso\TranslationsBundle\Document\Repository\TranslatableDocumentRepository")
 */
class TranslatableDocument
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
    protected $bundle;

    /**
     *
     * @MongoDB\String
     */
    protected $key;

    /**
     *
     * @MongoDB\String
     */
    protected $comment;

    /**
     * @MongoDB\EmbedMany(targetDocument="File")
     */
    protected $files;

    /**
     * @MongoDB\Timestamp
     */
    protected $createdAt;

    /**
     * @MongoDB\Timestamp
     */
    protected $updatedAt;

    /**
     * @MongoDB\Boolean
     */
    protected $deleted;

    public function __construct()
    {
        $this->projectId = null;
        $this->files     = null;
        $this->createdAt = new \MongoTimestamp();
        $this->updatedAt = new \MongoTimestamp();
        $this->bundle    = null;
        $this->key       = null;
        $this->deleted   = false;
        $this->comment   = null;
    }

    /**
     * @param mixed $bundle
     */
    public function setBundle($bundle)
    {
        $this->bundle = $bundle;
    }

    /**
     * @return mixed
     */
    public function getBundle()
    {
        return $this->bundle;
    }

    /**
     * @param mixed $comment
     */
    public function setComment($comment)
    {
        $this->comment = $comment;
    }

    /**
     * @return mixed
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * @param mixed $createdAt
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return mixed
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param mixed $deleted
     */
    public function setDeleted($deleted)
    {
        $this->deleted = $deleted;
    }

    /**
     * @return mixed
     */
    public function getDeleted()
    {
        return $this->deleted;
    }

    /**
     * @param mixed $key
     */
    public function setKey($key)
    {
        $this->key = $key;
    }

    /**
     * @return mixed
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @param mixed $files
     */
    public function setFiles($files)
    {
        $this->files = $files;
    }

    /**
     * @return File[]
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $projectId
     */
    public function setProjectId($projectId)
    {
        $this->projectId = $projectId;
    }

    /**
     * @return mixed
     */
    public function getProjectId()
    {
        return $this->projectId;
    }

    /**
     * @param mixed $updatedAt
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
    }

    /**
     * @return mixed
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    public function getHash()
    {
        return base64_encode(sha1($this->getId()));
    }
}
