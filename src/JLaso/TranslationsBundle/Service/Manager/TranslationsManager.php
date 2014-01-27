<?php
/**
 * @author jlaso@joseluislaso.es
 */

namespace JLaso\TranslationsBundle\Service\Manager;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use JLaso\TranslationsBundle\Entity\Permission;
use JLaso\TranslationsBundle\Entity\Project;
use JLaso\TranslationsBundle\Entity\Repository\PermissionRepository;
use JLaso\TranslationsBundle\Entity\TranslationLog;
use JLaso\TranslationsBundle\Entity\User;
use JLaso\TranslationsBundle\Document\Translation;
use Doctrine\ODM\MongoDB\DocumentManager;
use JLaso\TranslationsBundle\Document\Repository\TranslationRepository;
use JLaso\TranslationsBundle\Entity\Repository\TranslationLogRepository;



class TranslationsManager
{

    /** @var  EntityManagerInterface */
    protected $em;
    /** @var  DocumentManager */
    protected $dm;

    function __construct(EntityManagerInterface $em, DocumentManager $dm)
    {
        $this->em = $em;
        $this->dm = $dm;
    }

    /**
     * Ini format to assoc: $keyedArray["key.subkey.subsubkey.etc"] returns $array["key"]["subkey"]["subsubkey"]["etc"]
     *
     * @param string $keyedArray in format key.subkey.subsubkey.etc
     * @param array  $arrayAssoc
     *
     * @return array
     */
    public function iniToAssoc($keyedArray, $arrayAssoc)
    {
        $node = $keyedArray; //str_replace('.', '-', $keyedArray);
        $keys = explode('.', $keyedArray);
        for($i=count($keys); $i>0; $i--){
            $k = $keys[$i-1];
            $node = array( $k => $node);
        }
        $result = array_merge_recursive($arrayAssoc, $node);

        return $result;
    }

    /**
     * @param User    $user
     * @param Project $project
     *
     * @return bool
     */
    public function userHasProject(User $user, Project $project)
    {
        $permission = $this->getPermissionForUserAndProject($user, $project);
        if($permission instanceof Permission){
            return $permission->getPermissions();
        }

        return false;
    }

    /**
     * @param User    $user
     * @param Project $project
     *
     * @return Permission
     */
    public function getPermissionForUserAndProject(User $user, Project $project)
    {
        return $this->getPermissionRepository()->findPermissionForProjectAndUser($project, $user);
    }

    /**
     * @param Project $project
     * @param         $criteria
     * @param         $key
     *
     * @return Translation
     */
    public function getTranslation(Project $project, $criteria, $key)
    {
        if(strpos($criteria, "Bundle") !== false){
            $bundle = $criteria;
            $translation = $this->getTranslationRepository()->findOneBy(array(
                    'projectId' => intval($project->getId()),
                    'bundle'    => trim($bundle),
                    'key'       => trim($key),
                )
            );
        }else{
            $catalog = $criteria;
            $translation = $this->getTranslationRepository()->getTranslation($project->getId(), $catalog, $key);
        }
        if(!$translation){
            return null;
        }
        $managedLocales = explode(',',$project->getManagedLocales());

        return $this->normalizeTranslation($translation, $managedLocales);
    }

    /**
     * Normalize the translations array info, creating locales and deleting if case
     *
     * @param Translation $translation
     * @param array       $managedLocales
     * @param bool        $deletesIfNotExistsLocaleInManaged
     *
     * @return Translation
     */
    public function normalizeTranslation(Translation $translation, $managedLocales = array(), $deletesIfNotExistsLocaleInManaged = false)
    {
        $transArray = $translation->getTranslations();
        // normalize the translation array
        foreach($managedLocales as $locale){
            if(!isset($transArray[$locale])){
                $transArray[$locale] = Translation::genTranslationItem('');
            }
        }
        // deletes message if locale do not exists yet in managed locales
        if($deletesIfNotExistsLocaleInManaged){
            foreach($transArray as $locale=>$data){
                if(!in_array($locale, $managedLocales)){
                    unset($transArray[$locale]);
                }
            }
        }
        $translation->setTranslations($transArray);

        return $translation;
    }

    /**
     * @param Project $project
     *
     * @return array
     */
    public function getAllBundlesForProject(Project $project)
    {
        return $this->getTranslationRepository()->getBundles($project->getId());
    }

    /**
     * saves the message into $translation[$key][$locale][$message] and normalize rest of translations of this key
     *
     * @param Project $project
     * @param         $criteria
     * @param         $key
     * @param         $locale
     * @param         $message
     *
     * @return Translation
     */
    public function putTranslation(Project $project, $criteria, $key, $locale, $message)
    {
        // first get the record
        if(strpos($criteria, "Bundle") != false){
            $translation = $this->getTranslationRepository()->getTranslationByBundle($project->getId(), $criteria, $key);
        }else{
            $translation = $this->getTranslationRepository()->getTranslation($project->getId(), $criteria, $key);
        }
        if(!$translation){
            return null;
        }
        $managedLocales = explode(',',$project->getManagedLocales());
        $translation = $this->normalizeTranslation($translation, $managedLocales);
        // now normalize (creates items for managed locales that not exists)
        $transArray = $translation->getTranslations();
        $transArray[$locale] = array_merge(
            $transArray[$locale],
            Translation::genTranslationItem($message)
        );
        $translation->setTranslations($transArray);
        // last, return

        return $translation;
    }

    /**
     * @param User $user
     *
     * @return Project[]
     */
    public function getProjectsForUser(User $user)
    {
        $projects = array();
        $permissions = $user->getPermissions();
        foreach($permissions as $permission){
            $projects[] = $permission->getProject();
        }

        return $projects;
    }

    /**
     * @param string $key
     *
     * @return string
     */
    public function keyToHtmlId($key)
    {
        return str_replace('.','-',$key);
    }

    /**
     * @param        $group
     * @param        $translationId
     * @param string $locale
     * @param        $message
     * @param string $action
     * @param User   $user
     *
     * @internal param int $translation
     */
    public function saveLog($translationId, $locale, $message, $action, User $user, $group = TranslationLog::TRANSLATIONS_GROUP)
    {
        $log = new TranslationLog();
        $log->setGroup($group);
        $log->setTranslationId($translationId);
        $log->setMessage($message);
        $log->setActionType($action);
        $log->setLocale($locale);
        $log->setUser($user);
        $this->em->persist($log);
        $this->em->flush();
    }

    /**
     * @return PermissionRepository
     */
    protected function getPermissionRepository()
    {
        return $this->em->getRepository('TranslationsBundle:Permission');
    }

    /**
     * @return TranslationLogRepository
     */
    protected function getTranslationLogRepository()
    {
        return $this->em->getRepository('TranslationsBundle:TranslationLog');
    }

    /**
     * @return TranslationRepository
     */
    protected function getTranslationRepository()
    {
        return $this->dm->getRepository('TranslationsBundle:Translation');
    }





}