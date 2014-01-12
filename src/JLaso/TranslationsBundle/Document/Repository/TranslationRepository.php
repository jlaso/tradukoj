<?php

namespace JLaso\TranslationsBundle\Document\Repository;

use Doctrine\ODM\MongoDB;
use Doctrine\ODM\MongoDB\DocumentRepository;
use JLaso\TranslationsBundle\Document\Translation;

class TranslationRepository extends DocumentRepository
{
    public function getCatalogs($projectId)
    {
        $dm = $this->getDocumentManager();

        /** @var Translation[] $result */
        $result = $dm->getRepository('TranslationsBundle:Translation')->findBy(array('projectId'=>$projectId));

        $catalogs = array();
        foreach($result as $item){
            $catalogs[$item->getCatalog()] = null;
        }

        return array_keys($catalogs);
    }

    public function getKeys($projectId, $catalog)
    {
        $dm = $this->getDocumentManager();

        /** @var Translation[] $result */
        $result = $dm->getRepository('TranslationsBundle:Translation')
            ->findBy(array(
                    'projectId' => $projectId,
                    'catalog'   => $catalog,
                )
            );

        $keys = array();
        foreach($result as $item){
            $keys[] = array(
                'key' => $item->getKey(),
                'id'  => $item->getId(),
            );
        }

        return $keys;
    }

    public function getMessagesDocument($projectId, $catalog, $key)
    {
//        /** @var Translation $result */
//        $query = $this
//            ->createQueryBuilder('TranslationsBundle:Translation')
//            //->field('projectId')->equals(intval($projectId))
//            ->field('catalog')->equals($catalog)
//            ->field('key')->equals($key)
//            ->getQuery()
//        ;
//
//        //var_dump($query->getQuery());
//
//        $result = $query->execute();


        $result = $this
            ->findOneBy(array(
                    //'projectId' => intval($projectId),
                    //'catalog'   => trim($catalog),
                    'key'       => trim($key),
                )
            );

        //print("||$key||".count($result)); die;

        return $result; // ? $result->getTranslations() : array();
    }

}
