<?php

namespace JLaso\TranslationsBundle\Entity\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use JLaso\TranslationsBundle\Entity\Language;

/**
 * LanguageRepository
 */
class LanguageRepository extends EntityRepository
{
    /**
     * @param $locales
     *
     * @return Query
     */
    public function findAllLanguageInQuery($locales)
    {
        $em = $this->getEntityManager();

        $queryBuilder = $em->createQueryBuilder();
        $queryBuilder->select('l')
            ->from('TranslationsBundle:Language', 'l')
            ->where('l.locale IN (:locales)')
            ->setParameter('locales', $locales)
        ;

        return $queryBuilder->getQuery();
    }

    /**
     * @param array $locales
     * @param bool  $asAssoc
     *
     * @return Language[]|array
     */
    public function findAllLanguageIn($locales, $asAssoc = false)
    {
        /** @var Language[] $languages */
        $languages =  $this->findAllLanguageInQuery($locales)->getResult();
        if ($asAssoc) {
            $aux = array();
            foreach ($languages as $language) {
                $aux[$language->getLocale()] = $language->asArray();
            }
            $languages = $aux;
        }

        return $languages;
    }
}
