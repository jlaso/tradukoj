<?php

namespace JLaso\TranslationsBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class ExportToExcelType extends AbstractType
{
    /** @var  mixed */
    protected $locales;

    public function __construct($locales)
    {
        $this->locales = is_array($locales) ? $locales : preg_split("/,/", $locales);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('locale', 'choice', array(
                    'required'    => true,
                    'choices'     => $this->locales,
                    'constraints' => array(
                        //new NotBlank(),
                    ),
                    'attr'        => array(
                        'placeholder' => 'export_to_excel.placeholder.locale',
                        'class'       => 'uniform',
                    ),
                ))

            ->add('bundle_file', 'checkbox', array(
                    'required'    => false,
                    'attr'        => array(
                        'placeholder' => 'export_to_excel.placeholder.bundle_file',
                        'class'       => 'uniform',
                    ),
                ))

            ->add('compress_html_labels', 'checkbox', array(
                    'required'    => false,
                    'attr'        => array(
                        'placeholder' => 'export_to_excel.placeholder.compress_html_labels',
                        'class'       => 'uniform',
                    ),
                ))

            ->add('compress_variables', 'checkbox', array(
                    'required'    => false,
                    'attr'        => array(
                        'placeholder' => 'export_to_excel.placeholder.compress_variables',
                        'class'       => 'uniform',
                    ),
                ))
        ;
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
                'data_class' => 'JLaso\TranslationsBundle\Model\ExportToExcel',
            ));
    }

    public function getName()
    {
        return 'export_to_excel';
    }
}
