<?php

namespace JLaso\TranslationsBundle\Document\Repository;

use Doctrine\ODM\MongoDB\DocumentRepository;
use JLaso\TranslationsBundle\Document\ProjectInfo;

class ProjectInfoRepository extends DocumentRepository
{
    /**
     * @param $projectId
     * @param bool $sorted
     * @param bool $onlyKeys
     *
     * @return mixed
     */
    public function getCatalogs($projectId, $sorted = true, $onlyKeys = true)
    {
        $projectInfo = $this->getProjectInfo($projectId);
        if (!$projectInfo instanceof ProjectInfo) {
            return array();
        }
        $result = $projectInfo->getCatalogs();
        if ($sorted && is_array($result)) {
            ksort($result);
        }

        return $onlyKeys ? array_keys($result) : $result;
    }

    /**
     * @param $projectId
     * @param bool $sorted
     * @param bool $onlyKeys
     *
     * @return mixed
     */
    public function getBundles($projectId, $sorted = true, $onlyKeys = true)
    {
        $projectInfo = $this->getProjectInfo($projectId);
        if (!$projectInfo instanceof ProjectInfo) {
            return array();
        }
        $result = $projectInfo->getBundles();
        if ($sorted && is_array($result)) {
            ksort($result);
        }

        return $onlyKeys ? array_keys($result) : $result;
    }

    /**
     * @param $projectId
     *
     * @return ProjectInfo
     */
    public function getProjectInfo($projectId)
    {
        $dm = $this->getDocumentManager();

        return $dm->getRepository('TranslationsBundle:ProjectInfo')->findOneBy(array('projectId' => intval($projectId)));
    }
}
