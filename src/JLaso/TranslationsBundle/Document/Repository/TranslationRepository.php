<?php

namespace JLaso\TranslationsBundle\Document\Repository;

use Doctrine\ODM\MongoDB\DocumentRepository;
use JLaso\TranslationsBundle\Document\Translation;

class TranslationRepository extends DocumentRepository
{
//    public function getCatalogs($projectId)
//    {
//        $dm = $this->getDocumentManager();
//
//        /** @var Translation[] $result */
//        $result = $dm->getRepository('TranslationsBundle:Translation')->findBy(array('projectId'=>$projectId));
//
//        $catalogs = array();
//        foreach($result as $item){
//            $catalogs[$item->getCatalog()] = null;
//        }
//
//        return array_keys($catalogs);
//    }
//
//    public function getBundles($projectId)
//    {
//        $dm = $this->getDocumentManager();
//
//        /** @var Translation[] $result */
//        $result = $dm->getRepository('TranslationsBundle:Translation')->findBy(array('projectId'=>$projectId));
//
//        $bundles = array();
//        foreach($result as $item){
//            $bundles[$item->getBundle()] = null;
//        }
//
//        return array_keys($bundles);
//    }

    public function getKeys($projectId, $catalog, $onlyLanguage = '', $approvedFilter = 'all')
    {
        $dm = $this->getDocumentManager();

        /** @var Translation[] $result */
        $result = $dm->getRepository('TranslationsBundle:Translation')
            ->findBy(array(
                    'projectId' => $projectId,
                    'catalog'   => $catalog,
                ), array('key'));

        $keys = array();

        foreach ($result as $item) {
            $translations = $item->getTranslations();

            switch (true) {

                case(!$onlyLanguage):
                    $show = true;
                    break;

                case(isset($translations[$onlyLanguage]) && ($approvedFilter == 'all')):
                    $show = !isset($translations[$onlyLanguage]['message']) || !trim($translations[$onlyLanguage]['message']);
                    break;

                case(isset($translations[$onlyLanguage])):
                    $show = (($approvedFilter == 'approved')  &&  $translations[$onlyLanguage]['approved'])
                        || (($approvedFilter == 'disapproved')  &&  !$translations[$onlyLanguage]['approved']);
                    break;

                default:
                    $show = false;
                    break;

            }

            if ($show) {
                $key = $item->getKey();
                $keys[$key] = array(
                    'key' => $key,
                    'id'  => $item->getId(),
                );
            }
        }
        ksort($keys, SORT_STRING);  // ideally SORT_NATURAL ^ SORT_FLAG_CASE  but current server don't support this flags

        return $keys;
    }

    public function getKeysByBundle($projectId, $bundle, $onlyLanguage = '', $approvedFilter = 'all')
    {
        $dm = $this->getDocumentManager();

        /** @var Translation[] $result */
        $result = $dm->getRepository('TranslationsBundle:Translation')
            ->findBy(array(
                    'projectId' => $projectId,
                    'bundle'    => $bundle,
                ), array('key'));

        $keys = array();
        foreach ($result as $item) {
            $translations = $item->getTranslations();

            switch (true) {

                case(!$onlyLanguage):
                    $show = true;
                    break;

                case(isset($translations[$onlyLanguage]) && ($approvedFilter == 'all')):
                    $show = !isset($translations[$onlyLanguage]['message']) || !trim($translations[$onlyLanguage]['message']);
                    break;

                case(isset($translations[$onlyLanguage])):
                    $show = (($approvedFilter == 'approved')  &&  $translations[$onlyLanguage]['approved'])
                        || (($approvedFilter == 'disapproved')  &&  !$translations[$onlyLanguage]['approved']);
                    break;

                default:
                    $show = false;
                    break;

            }

            if ($show) {
                $key = $item->getKey();
                $keys[] = array(
                    'key' => $key,
                    'id'  => $item->getId(),
                );
            }
        }
        ksort($keys, SORT_STRING);  // ideally SORT_NATURAL ^ SORT_FLAG_CASE  but current server doesn't support this flags

        return $keys;
    }

    public function getKeysByLanguage($projectId, $locales)
    {
        $dm = $this->getDocumentManager();

        /** @var Translation[] $result */
        $result = $dm->getRepository('TranslationsBundle:Translation')
            ->findBy(array(
                    'projectId' => $projectId,
                ), array('key'));

        $temp = array();

        foreach ($locales as $locale => $info) {
            $temp[$locale] = array(
                'approved' => 0,
                'pending' => 0,
                'info' => array( 'name' => $info['name'], 'locale' => $locale ),
                'keys' => 0,
            );
        }

        foreach ($result as $item) {
            $translations = $item->getTranslations();

            foreach ($locales as $locale => $info) {
                if ($translations[$locale]['message']) {
                    if ($translations[$locale]['approved']) {
                        $temp[$locale]['approved']++;
                    } else {
                        $temp[$locale]['pending']++;
                    }
                }
            }
        }

        return $temp;
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
        foreach ($result as $item) {
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

    public function getTranslationByBundle($projectId, $bundle, $key)
    {
        $result = $this
            ->findOneBy(array(
                    'projectId' => intval($projectId),
                    'bundle'    => trim($bundle),
                    'key'       => trim($key),
                )
            );

        return $result; // ? $result->getTranslations() : array();
    }
}
