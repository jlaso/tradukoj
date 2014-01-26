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

    public function getBundles($projectId)
    {
        $dm = $this->getDocumentManager();

        /** @var Translation[] $result */
        $result = $dm->getRepository('TranslationsBundle:Translation')->findBy(array('projectId'=>$projectId));

        $bundles = array();
        foreach($result as $item){
            $bundles[$item->getBundle()] = null;
        }

        return array_keys($bundles);
    }

    public function getKeys($projectId, $catalog, $onlyLanguage = '')
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
            $translations = $item->getTranslations();
            if(!$onlyLanguage || !isset($translations[$onlyLanguage]['message']) || !trim($translations[$onlyLanguage]['message'])){
                $keys[] = array(
                    'key' => $item->getKey(),
                    'id'  => $item->getId(),
                );
            }
        }

        return $keys;
    }

    public function getKeysByBundle($projectId, $bundle, $onlyLanguage = '')
    {
        $dm = $this->getDocumentManager();

        /** @var Translation[] $result */
        $result = $dm->getRepository('TranslationsBundle:Translation')
            ->findBy(array(
                    'projectId' => $projectId,
                    'bundle'    => $bundle,
                )
            );

        $keys = array();
        foreach($result as $item){
            $translations = $item->getTranslations();
            if(!$onlyLanguage || !isset($translations[$onlyLanguage]['message']) || !trim($translations[$onlyLanguage]['message'])){
                $keys[] = array(
                    'key' => $item->getKey(),
                    'id'  => $item->getId(),
                );
            }
        }

        return $keys;
    }

    public function searchKeys($projectId, $search)
    {
        $dm = $this->getDocumentManager();

        /** @var Translation[] $result */
        $result = $dm->getRepository('TranslationsBundle:Translation')
            ->findBy(array(
                    'projectId' => $projectId,
                    'key'       => $search,
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

    /**
     * @param $projectId
     * @param $catalog
     * @param $key
     *
     * @return Translation
     */
    public function getTranslation($projectId, $catalog, $key)
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
                    'projectId' => intval($projectId),
                    'catalog'   => trim($catalog),
                    'key'       => trim($key),
                )
            );

        //print("||$key||".count($result)); die;

        return $result; // ? $result->getTranslations() : array();
    }

}
