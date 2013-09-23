<?php

namespace JLaso\TranslationsBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserRegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('email', null, array(
                    'attr' => array(
                        'placeholder' => 'register.placeholder.email',
                    )
                ))


            ->add('password', 'repeated', array(
                    'label' => '',
                    'type' => 'password',
//                    'invalid_message' => "account_settings.change_password.placeholder.password_mismatch",
                    'options' => array(
                        'required' => true
                    ),

                    'first_options'  => array(
                        'attr' => array(
                            'placeholder' => 'register.placeholder.password',
                        ),
                        'label' => '',
                    ),

                    'second_options' => array(
                        'attr' => array(
                            'placeholder' => 'register.placeholder.repeat_password',
                        ),
                        'label' => ''
                    ),
                ))

            ->add('confirmation', 'checkbox', array(
                    'label' => 'register.placeholder.terms',
                    'property_path' => false,
                    'constraints' => new NotBlank()
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
