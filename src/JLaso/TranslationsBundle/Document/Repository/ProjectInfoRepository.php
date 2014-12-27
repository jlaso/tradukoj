<?php

namespace JLaso\TranslationsBundle\Document\Repository;

use Doctrine\ODM\MongoDB;
use Doctrine\ODM\MongoDB\DocumentRepository;
use JLaso\TranslationsBundle\Document\Translation;

class ProjectInfoRepository extends DocumentRepository
{
    public function getCatalogs($projectId, $sorted = true, $onlyKeys = true)
    {
        $dm = $this->getDocumentManager();
        /** @var ProjectInfo $result */
        $result = $dm->getRepository('TranslationsBundle:ProjectInfo')->findOneBy(array('projectId'=>intval($projectId)));
        $result = $result ? $result->getCatalogs() : array();
        if($sorted && is_array($result)){
            ksort($result);
        }

        return $onlyKeys ? array_keys($result) : $result;
    }

    public function getBundles($projectId, $sorted = true, $onlyKeys = true)
    {
        $dm = $this->getDocumentManager();
        /** @var ProjectInfo $result */
        $result = $dm->getRepository('TranslationsBundle:ProjectInfo')->findOneBy(array('projectId'=>intval($projectId)));
        $result = $result ? $result->getBundles() : array();
        if($sorted && is_array($result)){
            ksort($result);
        }

        return $onlyKeys ? array_keys($result) : $result;
    }

    public function getProjectInfo($projectId)
    {
        $dm = $this->getDocumentManager();

        return $dm->getRepository('TranslationsBundle:ProjectInfo')->findOneBy(array('projectId'=>intval($projectId)));
    }

}
