<?php
/**
 * @author jlaso@joseluislaso.es
 */

namespace JLaso\TranslationsBundle\Service\Manager;

use Doctrine\ORM\EntityManagerInterface;
use JLaso\TranslationsBundle\Entity\Project;
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
        if(count($user->getProjects()) > count($project->getUsers())){
            foreach($project->getUsers() as $currentUser){
                if($currentUser->isEqualTo($user)){
                    return true;
                }
            }
        }else{
            $id = $project->getId();
            foreach($user->getProjects() as $currentProject){
                if($currentProject->getId() == $id){
                    return true;
                }
            }
        }
        return false;
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


}