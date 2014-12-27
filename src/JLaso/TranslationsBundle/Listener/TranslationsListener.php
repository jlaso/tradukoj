<?php

namespace JLaso\TranslationsBundle\Listener;

use Doctrine\ODM\MongoDB\Event\LifecycleEventArgs;
use Doctrine\ODM\MongoDB\Event\PreFlushEventArgs;
use JLaso\TranslationsBundle\Document\ProjectInfo;
use JLaso\TranslationsBundle\Document\Translation;


class TranslationsListener
{
    /**
     * To maintain the bundle and catalog of the key last viewed
     *
     * @var mixed
     */
    protected $cache;

    /**
     * @param LifecycleEventArgs $eventArgs
     */
    public function postUpdate(LifecycleEventArgs $eventArgs)
    {
        $document = $eventArgs->getDocument();
        $dm       = $eventArgs->getDocumentManager();
        if ($document instanceof Translation) {
            /** @var Translation $document */
            $projectId   = $document->getProjectId();
            $projectInfo = $dm->getRepository('TranslationsBundle:ProjectInfo')->getProjectInfo($projectId);
            if(!$projectInfo){
                $projectInfo = new ProjectInfo();
                $projectInfo->setProjectId($projectId);
            }
            if(isset($this->cache["id"] && ($this->cache["id"]==$document->getId()]))){
                $projectInfo->subBundle($this->cache['bundle']);
                $projectInfo->subCatalog($this->cache['catalog']);
            }
            $projectInfo->addBundle($document->getBundle());
            $projectInfo->addCatalog($document->getCatlog());
            $dm->persist($projectInfo);
            $dm->flush();
            return;
        }
    }

    /**
     * @param LifecycleEventArgs $eventArgs
     */
    public function postPersist(LifecycleEventArgs $eventArgs)
    {
        $document = $eventArgs->getDocument();
        $dm       = $eventArgs->getDocumentManager();
        if ($document instanceof Translation) {
            /** @var Translation $document */
            $projectId   = $document->getProjectId();
            $projectInfo = $dm->getRepository('TranslationsBundle:ProjectInfo')->getProjectInfo($projectId);
            $projectInfo->addBundle($document->getBundle());
            $projectInfo->addCatalog($document->getCatalog());
            $dm->persist($projectInfo);
            $dm->flush();
            return;
        }
    }

    /**
     * @param LifecycleEventArgs $eventArgs
     */
    public function preRemove(LifecycleEventArgs $eventArgs)
    {
        $document = $eventArgs->getDocument();
        $dm       = $eventArgs->getDocumentManager();
        if ($document instanceof Translation) {
            /** @var Translation $document */
            $projectId   = $document->getProjectId();
            $projectInfo = $dm->getRepository('TranslationsBundle:ProjectInfo')->getProjectInfo($projectId);
            $projectInfo->subBundle($document->getBundle());
            $projectInfo->subCatalog($document->getCatalog());
            $dm->persist($projectInfo);
            $dm->flush();
            return;
        }
    }

    /**
     * @param LifecycleEventArgs $eventArgs
     */
    public function postLoad(LifecycleEventArgs $eventArgs)
    {
        //echo('OK postLoad<hr/>');
        $document = $eventArgs->getDocument();

        if (!$document instanceof Translation) {
            return;
        }
        $this->cache = array(
            'id'      => $document->getId(),
            'bundle'  => $document->getBundle(),
            'catalog' => $document->getCatalog(),
        );
    }

}

