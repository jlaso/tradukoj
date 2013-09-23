<?php

namespace JLaso\TranslationsBundle\Entity\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use JLaso\TranslationsBundle\Entity\Message;
use JLaso\TranslationsBundle\Entity\Project;

class MessageRepository extends EntityRepository
{

    /**
     * @param Project $project
     * @param string  $bundle
     * @param string  $catalog
     * @param string  $locale
     *
     * @return Query
     */
    public function findAllMessagesOfProjectBundleCatalogAndLocaleQuery(Project $project, $bundle, $catalog, $locale)
    {
        $em = $this->getEntityManager();
        $queryBuilder = $em->createQueryBuilder();
        $queryBuilder->select('m', 'k')
            ->from('TranslationsBundle:Message', 'm')
            ->leftJoin('m.key', 'k')
            ->where('k.project = :projectId')
            ->andWhere('k.bundle = :bundle')
            ->andWhere('k.catalog = :catalog')
            ->andWhere('m.language = :locale')
            ->setParameters(array(
                    'projectId' => $project->getId(),
                    'bundle'    => $bundle,
                    'catalog'   => $catalog,
                    'locale'    => $locale,
                )

            )
            ->orderBy('k.key')
        ;
        //print($queryBuilder->getQuery()->getDQL());

        return $queryBuilder->getQuery();
    }

    /**
     * @param Project $project
     * @param string  $bundle
     * @param string  $catalog
     * @param string  $locale
     *
     * @return Message[]
     */
    public function findAllMessagesOfProjectBundleCatalogAndLocale(Project $project, $bundle, $catalog, $locale)
    {
        $result = $this->findAllMessagesOfProjectBundleCatalogAndLocaleQuery($project, $bundle, $catalog, $locale)->getResult();
        /*$r = array();
        foreach($result as $i){
            $r[] = $i->asArray();
        }
        print_r($r); die;*/

        return $result;
    }

}
