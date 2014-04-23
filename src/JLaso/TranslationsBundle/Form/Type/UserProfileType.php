<?php

namespace JLaso\TranslationsBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('email', null, array(
                    'attr'        => array(
                        'placeholder' => 'profile.placeholder.email',
                    ),
                    'required'    => true,                    
                    'constraints' => new NotBlank(),
                ))


            ->add('password', 'repeated', array(
                    'label'   => '',
                    'type'    => 'password',
                    'options' => array(
                        'required' => false,
                    ),

                    'first_options'  => array(
                        'attr' => array(
                            'placeholder' => 'profile.placeholder.password',
                        ),
                        'label' => '',
                    ),

                    'second_options' => array(
                        'attr' => array(
                            'placeholder' => 'profile.placeholder.repeat_password',
                        ),
                        'label' => ''
                    ),
                ))

            ->add('username', 'text', array(
                    'label'       => 'profile.placeholder.username',
                    'constraints' => new NotBlank(),
                    'required'    => true,
                ))

            ->add('name', 'text', array(
                    'label'       => 'profile.placeholder.name',
                    'required'    => false,
                ))

            ->add('surname', 'text', array(
                    'label'       => 'profile.placeholder.surname',
                    'required'    => false,
                ))

        ;
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
                'data_class' => 'JLaso\TranslationsBundle\Entity\User'
            ));
    }

    public function getName()
    {
        return 'user_registration';
    }
}
