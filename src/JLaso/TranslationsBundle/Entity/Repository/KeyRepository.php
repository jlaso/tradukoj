<?php

namespace JLaso\TranslationsBundle\Entity\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use JLaso\TranslationsBundle\Entity\Key;
use JLaso\TranslationsBundle\Entity\Project;

class KeyRepository extends EntityRepository
{
    /**
     * @param Project $project
     * @param string  $bundle
     * @param string  $catalog
     *
     * @return Query
     */
    public function findAllKeysForProjectBundleAndCatalogQuery(Project $project, $bundle, $catalog)
    {
        $em = $this->getEntityManager();

        $queryBuilder = $em->createQueryBuilder();
        $queryBuilder->select('k')
            ->from('TranslationsBundle:Key', 'k')
            ->where('k.project = :project')
            ->andWhere('k.bundle = :bundle')
            ->andWhere('k.catalog = :catalog')
            ->setParameters(array(
                    'project' => $project,
                    'bundle'  => $bundle,
                    'catalog' => $catalog,
                ))
        ;

        return $queryBuilder->getQuery();
    }

    /**
     * @param Project $project
     * @param string  $bundle
     * @param string  $catalog
     *
     * @return Key[]
     */
    public function findAllKeysForProjectBundleAndCatalog(Project $project, $bundle, $catalog)
    {
        return $this->findAllKeysForProjectBundleAndCatalogQuery($project, $bundle, $catalog)->getResult();
    }

    /**
     * @param Project $project
     *
     * @return array
     */
    public function findAllBundlesForProject(Project $project)
    {
        $bundles = array();
        foreach($project->getKeys() as $key){
            $bundle = $key->getBundle();
            if(!isset($bundles[$bundle])){
                $bundles[$bundle] = $bundle;
            }
        }

        return $bundles;
    }
}
