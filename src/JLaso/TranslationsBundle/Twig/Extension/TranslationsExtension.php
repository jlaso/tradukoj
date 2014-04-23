<?php

namespace JLaso\TranslationsBundle\Twig\Extension;

use JLaso\TranslationsBundle\Service\Manager\TranslationsManager;

class TranslationsExtension extends \Twig_Extension
{

    protected $translationsManager;

    public function __construct(TranslationsManager $translationsManager)
    {
        $this->translationsManager = $translationsManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return array(
            'keyToHtmlId' => new \Twig_Function_Method($this, 'keyToHtmlId'),
        );
    }

    /**
     * @param $id
     *
     * @return string
     */
    public function keyToHtmlId($id)
    {
        return $this->translationsManager->keyToHtmlId($id);
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'translations';
    }
}
