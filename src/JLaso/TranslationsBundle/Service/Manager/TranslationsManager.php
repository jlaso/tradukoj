<?php
/**
 * @author jlaso@joseluislaso.es
 */

namespace JLaso\TranslationsBundle\Service\Manager;

use Doctrine\ORM\EntityManagerInterface;
use JLaso\TranslationsBundle\Entity\Message;
use JLaso\TranslationsBundle\Entity\Permission;
use JLaso\TranslationsBundle\Entity\Project;
use JLaso\TranslationsBundle\Entity\Repository\PermissionRepository;
use JLaso\TranslationsBundle\Entity\TranslateLog;
use JLaso\TranslationsBundle\Entity\User;

class TranslationsManager
{

    /** @var  EntityManagerInterface */
    protected $em;

    function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
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
        $node = str_replace('.', '-', $keyedArray);
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
     * @param Message $msg
     * @param string  $action
     * @param User    $user
     */
    public function saveLog(Message $msg, $action, User $user)
    {
        $log = new TranslateLog();
        $log->setMessage($msg);
        $log->setMessageCopy($msg->getMessage());
        $log->setMessageId($msg->getId());
        $log->setActionType($action);
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





}