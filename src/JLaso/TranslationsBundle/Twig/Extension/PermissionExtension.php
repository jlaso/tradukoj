<?php

namespace JLaso\TranslationsBundle\Twig\Extension;

use JLaso\TranslationsBundle\Service\Manager\TranslationsManager;
use JLaso\TranslationsBundle\Entity\Permission;

class PermissionExtension extends \Twig_Extension
{

    //protected $translationsManager;

    public function __construct(TranslationsManager $translationsManager)
    {
        //$this->translationsManager = $translationsManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return array(
            'permission' => new \Twig_Function_Method($this, 'permission'),
            'canAdmin' => new \Twig_Function_Method($this, 'canAdmin'),
        );
    }

    /**
     * @param array  $permissionArray
     * @param string $locale
     * @param null   $perm
     *
     * @return string
     */
    public function permission($permissionArray, $locale, $perm = null)
    {
        $permission = null;
        $permissions = isset($permissionArray[Permission::LOCALE_KEY]) ? $permissionArray[Permission::LOCALE_KEY] : array();
        if(isset($permissions[$locale])){
            $permission = $permissions[$locale];
        }else{
            $permission = isset($permissions[Permission::WILD_KEY]) ? $permissions[Permission::WILD_KEY] : '';
        }

        return Permission::checkPermission($permission, $perm);
    }

    /**
     * @param $permissionArray
     *
     * @return bool
     */
    public function canAdmin($permissionArray)
    {
        return (isset($permissionArray[Permission::GENERAL_KEY])  &&
               (($permissionArray[Permission::GENERAL_KEY] == Permission::ADMIN) ||
                ($permissionArray[Permission::GENERAL_KEY] == Permission::OWNER)));
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'permission';
    }
}
