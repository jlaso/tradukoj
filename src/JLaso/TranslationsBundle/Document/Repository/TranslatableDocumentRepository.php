<?php

namespace JLaso\TranslationsBundle\Document\Repository;

use Doctrine\ODM\MongoDB;
use Doctrine\ODM\MongoDB\DocumentRepository;
use JLaso\TranslationsBundle\Document\TranslatableDocument;

class TranslatableDocumentRepository extends DocumentRepository
{
    public function getBundles($projectId)
    {
        $dm = $this->getDocumentManager();

        /** @var TranslatableDocument[] $result */
        $result = $dm->getRepository('TranslationsBundle:TranslatableDocument')
            ->findBy(array('projectId'=>$projectId));

        $bundles = array();
        foreach($result as $item){
            $bundles[$item->getBundle()] = null;
        }

        return array_keys($bundles);
    }

}
