<?php

namespace App\Form\Admin;

use App\Entity\DiscordCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class DiscordCommandFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => false,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom de la commande est obligatoire.'),
                    new Assert\Length(
                        max: 32,
                        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.',
                    ),
                    new Assert\Regex(
                        pattern: '/^[a-z0-9_-]+$/',
                        message: 'Uniquement minuscules, chiffres, tirets et underscores.',
                    ),
                ],
                'attr' => ['placeholder' => 'ma-commande', 'maxlength' => 32, 'style' => 'font-family:monospace;'],
            ])
            ->add('description', TextType::class, [
                'label' => false,
                'constraints' => [
                    new Assert\NotBlank(message: 'La description est obligatoire.'),
                    new Assert\Length(
                        max: 100,
                        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.',
                    ),
                ],
                'attr' => ['placeholder' => 'Description affichée dans Discord', 'maxlength' => 100],
            ])
            ->add('response', TextareaType::class, [
                'label' => false,
                'required' => false,
                'empty_data' => null,
                'attr' => ['placeholder' => 'Texte de la réponse...'],
            ])
            ->add('embedTitle', TextType::class, [
                'label' => false,
                'required' => false,
                'empty_data' => null,
                'constraints' => [
                    new Assert\Length(max: 256, maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.'),
                ],
                'attr' => ['placeholder' => "Titre de l'embed Discord", 'maxlength' => 256],
            ])
            ->add('embedDescription', TextareaType::class, [
                'label' => false,
                'required' => false,
                'empty_data' => null,
                'attr' => ['placeholder' => "Contenu de l'embed..."],
            ])
            ->add('embedColor', TextType::class, [
                'label' => false,
                'required' => false,
                'empty_data' => null,
                'constraints' => [
                    new Assert\Regex(
                        pattern: '/^#[0-9a-fA-F]{6}$/',
                        message: 'La couleur doit être au format hexadécimal (ex: #45f882).',
                    ),
                ],
            ])
            ->add('embedImage', UrlType::class, [
                'label' => false,
                'required' => false,
                'empty_data' => null,
                'default_protocol' => null,
                'constraints' => [
                    new Assert\Length(max: 500, maxMessage: "L'URL ne peut pas dépasser {{ limit }} caractères."),
                    new Assert\Url(message: "L'URL de l'image n'est pas valide.", requireTld: true),
                ],
                'attr' => ['placeholder' => 'https://example.com/image.png'],
            ])
            ->add('requiredRole', TextType::class, [
                'label' => false,
                'required' => false,
                'empty_data' => null,
                'constraints' => [
                    new Assert\Length(max: 100, maxMessage: "L'ID ne peut pas dépasser {{ limit }} caractères."),
                ],
                'attr' => ['placeholder' => 'Laisser vide pour aucune restriction', 'style' => 'font-family:monospace;'],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Commande active',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DiscordCommand::class,
            'csrf_token_id' => 'discord_command',
        ]);
    }
}
