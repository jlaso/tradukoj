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
    /** @var  boolean */
    protected $compress_html_labels;
    /** @var  boolean */
    protected $compress_variables;
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

    /**
     * @param boolean $compress_html_labels
     */
    public function setCompressHtmlLabels($compress_html_labels)
    {
        $this->compress_html_labels = $compress_html_labels;
    }

    /**
     * @return boolean
     */
    public function getCompressHtmlLabels()
    {
        return $this->compress_html_labels;
    }

    /**
     * @param boolean $compress_variables
     */
    public function setCompressVariables($compress_variables)
    {
        $this->compress_variables = $compress_variables;
    }

    /**
     * @return boolean
     */
    public function getCompressVariables()
    {
        return $this->compress_variables;
    }



} 