<?php
/**
 * Created by PhpStorm.
 * User: jl
 * Date: 10/01/14
 * Time: 19:48
 */

namespace JLaso\TranslationsBundle\Model;


use JLaso\TranslationsBundle\Entity\Project;

class ExportToExcel
{

    /**
     * @var string
     */
    protected $locale;
    /** @var  boolean */
    protected $bundle_file;
    /**
     * @var Project
     */
    protected $project;
    /**
     * @var mixed
     */
    protected $locales;

    function __construct(Project $project, $locale = "")
    {
        $this->project     = $project;
        $this->locales     = $project->getManagedLocales();
        $this->locale      = $locale;
        $this->bundle_file = false;
    }

    /**
     * @param mixed $locale
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;
    }

    /**
     * @return mixed
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @param boolean $bundle_file
     */
    public function setBundleFile($bundle_file)
    {
        $this->bundle_file = $bundle_file;
    }

    /**
     * @return boolean
     */
    public function getBundleFile()
    {
        return $this->bundle_file;
    }


} 