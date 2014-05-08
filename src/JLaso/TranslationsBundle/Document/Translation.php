<?php

namespace JLaso\TranslationsBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Doctrine\Bundle\MongoDBBundle\Validator\Constraints\Unique as MongoDBUnique;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Translation
 *
 * @MongoDBUnique(fields="projectId,catalog,key")
 * @MongoDB\Document(repositoryClass="JLaso\TranslationsBundle\Document\Repository\TranslationRepository")
 */
class Translation
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
     * Array con la info para cada locale.
     * [locale]
     * {
     *   message,
     *   updatedAt,
     *   approved,
     *   filename,
     * }
     * @MongoDB\Hash
     */
    protected $translations;

    /**
     * @MongoDB\Timestamp
     */
    protected $createdAt;

    /**
     * @MongoDB\Timestamp
     */
    protected $updatedAt;

    /**
     * Imagen de captura utilizada en esta key
     *
     * @MongoDB\String
     */
    protected $screenshot;

    /**
     * @MongoDB\Boolean
     */
    protected $deleted;

    /**
     * Array con la posicion X,Y y W,H de la imagen de captura utilizada en esta cada key
     * para mostrar al traductor su integracion en la web
     *
     * @MongoDB\Hash
     */
    protected $imageMaps;

    public function __construct()
    {
        $this->projectId            = null;
        $this->translations         = null;
        $this->createdAt            = new \MongoTimestamp();
        $this->updatedAt            = new \MongoTimestamp();
        $this->screenshot           = null;
        $this->imageMaps            = null;
        $this->catalog              = null;
        $this->deleted              = false;
        $this->bundle               = '';
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
     * @param mixed $imageMaps
     */
    public function setImageMaps($imageMaps)
    {
        $this->imageMaps = $imageMaps;
    }

    /**
     * @return mixed
     */
    public function getImageMaps()
    {
        return $this->imageMaps;
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
     * @param mixed $screenshot
     */
    public function setScreenshot($screenshot)
    {
        $this->screenshot = $screenshot;
    }

    /**
     * @return mixed
     */
    public function getScreenshot()
    {
        return $this->screenshot;
    }

    /**
     * @param mixed $translations
     */
    public function setTranslations($translations)
    {
//        foreach($translations as $locale=>$translation){
//            $translation['message'] = isset($translation['message']) ? utf8_encode($translation['message']) : '';
//        }
        $this->translations = $translations;
    }

    /**
     * @return mixed
     */
    public function getTranslations()
    {
        $translations = $this->translations;
//        foreach($translations as $locale=>$translation){
//            //$translation['message'] = isset($translation['message']) ? utf8_decode($translation['message']) : '';
//            $translation['updatedAt'] = json_decode($translation[])
//        }
        return $translations;
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

    public static function genTranslationItem($message, $approved = false, $updatedAt = null, $fileName = '')
    {
        // @TODO: Que pasa con fileName ?
        return array(
            'message'   => $message,
            'approved'  => $approved,
            'updatedAt' => $updatedAt ? clone updatedAt : new \DateTime(),
            'fileName'  => $fileName,
        );
    }

    public function getHash()
    {
        return base64_encode(sha1($this->getId()));
    }

}
