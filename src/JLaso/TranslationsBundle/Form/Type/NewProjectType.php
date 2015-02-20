<?php

namespace JLaso\TranslationsBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class NewProjectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('project', null, array(
                    'attr' => array(
                        'placeholder' => 'new_project.placeholder.project',
                    ),
                ))

            ->add('name', null, array(
                    'attr' => array(
                        'placeholder' => 'new_project.placeholder.name',
                    ),
                ))

            ->add('api_secret', null, array(
                    'label'       => 'new_project.placeholder.api_secret',
                    'constraints' => new NotBlank(),
                    'attr' => array(
                        'placeholder' => 'new_project.placeholder.api_secret',
                    ),
                ))
            ->add('managed_locales', null, array(
                    'attr' => array(
                        'placeholder' => 'new_project.placeholder.managed_locales',
                    ),
                ))
        ;
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
                'data_class' => 'JLaso\TranslationsBundle\Entity\Project',
            ));
    }

    public function getName()
    {
        return 'new_project';
    }
}
