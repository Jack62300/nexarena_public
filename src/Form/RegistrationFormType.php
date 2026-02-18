<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'label' => false,
                'constraints' => [
                    new Assert\NotBlank(message: "Le nom d'utilisateur est obligatoire."),
                    new Assert\Length(
                        min: 3,
                        max: 30,
                        minMessage: "Le nom d'utilisateur doit contenir au moins {{ limit }} caractères.",
                        maxMessage: "Le nom d'utilisateur ne peut pas dépasser {{ limit }} caractères.",
                    ),
                    new Assert\Regex(
                        pattern: '/^[a-zA-Z0-9_\-]+$/',
                        message: "Le nom d'utilisateur ne peut contenir que des lettres, chiffres, tirets et underscores.",
                    ),
                ],
                'attr' => ['placeholder' => "Nom d'utilisateur", 'autocomplete' => 'username'],
            ])
            ->add('email', EmailType::class, [
                'label' => false,
                'constraints' => [
                    new Assert\NotBlank(message: "L'adresse email est obligatoire."),
                    new Assert\Email(message: "L'adresse email n'est pas valide."),
                ],
                'attr' => ['placeholder' => 'Email', 'autocomplete' => 'email'],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => false,
                    'attr' => ['placeholder' => 'Mot de passe', 'autocomplete' => 'new-password'],
                ],
                'second_options' => [
                    'label' => false,
                    'attr' => ['placeholder' => 'Confirmer le mot de passe', 'autocomplete' => 'new-password'],
                ],
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le mot de passe est obligatoire.'),
                    new Assert\Length(
                        min: 10,
                        minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'registration',
        ]);
    }
}
