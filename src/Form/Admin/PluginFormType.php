<?php

namespace App\Form\Admin;

use App\Entity\Plugin;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class PluginFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => false,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom est obligatoire.'),
                ],
            ])
            ->add('platform', ChoiceType::class, [
                'label' => false,
                'choices' => [
                    'Minecraft (Bukkit/Spigot/Paper)' => 'minecraft',
                    'FiveM (ESX/QBCore)' => 'fivem',
                    'Garry\'s Mod (DarkRP)' => 'gmod',
                    'Discord Bot (discord.js)' => 'discord',
                    'TeamSpeak (Python)' => 'teamspeak',
                    'Rust' => 'rust',
                    'ARK: Survival Evolved' => 'ark',
                    'CS2' => 'cs2',
                    'Unturned' => 'unturned',
                    'Autre' => 'other',
                ],
            ])
            ->add('category', ChoiceType::class, [
                'label' => false,
                'choices' => [
                    'Jeu' => 'game',
                    'Vocal' => 'vocal',
                    'Hébergement' => 'hosting',
                ],
            ])
            ->add('version', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['placeholder' => '1.0.0'],
            ])
            ->add('description', TextareaType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('longDescription', TextareaType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['rows' => 6],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Plugin actif',
                'required' => false,
            ])
            ->add('zipFile', FileType::class, [
                'label' => false,
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Assert\File([
                        'maxSize' => '50M',
                        'mimeTypes' => ['application/zip', 'application/x-zip-compressed'],
                        'mimeTypesMessage' => 'Seuls les fichiers ZIP sont autorisés.',
                        'maxSizeMessage' => 'Le fichier ne doit pas dépasser 50 Mo.',
                    ]),
                ],
                'attr' => ['accept' => '.zip'],
            ])
            ->add('iconFile', FileType::class, [
                'label' => false,
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Assert\File([
                        'maxSize' => '2M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                        'mimeTypesMessage' => 'Format non autorisé. Utilisez JPEG, PNG, GIF ou WebP.',
                        'maxSizeMessage' => "L'icône ne doit pas dépasser 2 Mo.",
                    ]),
                ],
                'attr' => ['accept' => 'image/jpeg,image/png,image/gif,image/webp'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Plugin::class,
            'csrf_token_id' => 'plugin_form',
        ]);
    }
}
