<?php

namespace JLaso\TranslationsBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Doctrine\Bundle\MongoDBBundle\Validator\Constraints\Unique as MongoDBUnique;

/**
 * ProjectInfo
 *
 * @MongoDBUnique(fields="projectId")
 * @MongoDB\Document(repositoryClass="JLaso\TranslationsBundle\Document\Repository\ProjectInfoRepository")
 */
class ProjectInfo
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
     * @MongoDB\Hash
     */
    protected $catalogs;

    /**
     *
     * @MongoDB\Hash
     */
    protected $bundles;

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
        $this->createdAt = new \MongoTimestamp();
        $this->updatedAt = new \MongoTimestamp();
        $this->catalogs  = array();
        $this->bundles   = array();
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

    /**
     * @return \MongoTimestamp
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
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
     * @param mixed $catalogs
     */
    public function setCatalogs($catalogs)
    {
        $this->catalogs = $catalogs;
    }

    /**
     * @return mixed
     */
    public function getCatalogs()
    {
        return $this->catalogs;
    }

    public function addCatalog($catalog)
    {
        if ($catalog) {
            if (!isset($this->catalogs[$catalog])) {
                $this->catalogs[$catalog] = 1;
            } else {
                $this->catalogs[$catalog]++;
            }
        }
    }

    public function subCatalog($catalog)
    {
        if ($catalog) {
            if (isset($this->catalogs[$catalog])) {
                $this->catalogs[$catalog]--;
                if ($this->catalogs[$catalog] == 0) {
                    unset($this->catalogs[$catalog]);
                }
            }
        }
    }

    /**
     * @param mixed $bundles
     */
    public function setBundles($bundles)
    {
        $this->bundles = $bundles;
    }

    /**
     * @return mixed
     */
    public function getBundles()
    {
        return $this->bundles;
    }

    public function addBundle($bundle)
    {
        if ($bundle) {
            if (!isset($this->bundles[$bundle])) {
                $this->bundles[$bundle] = 1;
            } else {
                $this->bundles[$bundle]++;
            }
        }
    }

    public function subBundle($bundle)
    {
        if ($bundle) {
            if (isset($this->bundles[$bundle])) {
                $this->bundles[$bundle]--;
                if ($this->bundles[$bundle] == 0) {
                    unset($this->bundles[$bundle]);
                }
            }
        }
    }

    public function getHash()
    {
        return base64_encode(sha1($this->getId()));
    }
}
