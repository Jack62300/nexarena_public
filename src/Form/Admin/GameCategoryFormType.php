<?php

namespace App\Form\Admin;

use App\Entity\Category;
use App\Entity\GameCategory;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class GameCategoryFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('category', EntityType::class, [
                'label' => false,
                'class' => Category::class,
                'choice_label' => 'name',
                'placeholder' => '-- Sélectionner --',
                'required' => false,
                'query_builder' => function ($er) {
                    return $er->createQueryBuilder('c')
                        ->orderBy('c.position', 'ASC');
                },
            ])
            ->add('name', TextType::class, [
                'label' => false,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom est obligatoire.'),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['rows' => 4],
            ])
            ->add('color', TextType::class, [
                'label' => false,
                'required' => false,
                'constraints' => [
                    new Assert\Regex(
                        pattern: '/^#[0-9a-fA-F]{6}$/',
                        message: 'La couleur doit être au format hexadécimal (ex: #45f882).',
                    ),
                ],
            ])
            ->add('position', IntegerType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['min' => 0],
                'empty_data' => '0',
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Catégorie active',
                'required' => false,
            ])
            ->add('imageFile', FileType::class, [
                'label' => false,
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Assert\File([
                        'maxSize' => '5M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'],
                        'mimeTypesMessage' => 'Format non autorisé. Utilisez JPEG, PNG, GIF, WebP ou SVG.',
                        'maxSizeMessage' => "L'image ne doit pas dépasser 5 Mo.",
                    ]),
                ],
                'attr' => ['accept' => 'image/*'],
            ])
            ->add('iconFile', FileType::class, [
                'label' => false,
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Assert\File([
                        'maxSize' => '2M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'],
                        'mimeTypesMessage' => 'Format non autorisé. Utilisez JPEG, PNG, GIF, WebP ou SVG.',
                        'maxSizeMessage' => "L'icône ne doit pas dépasser 2 Mo.",
                    ]),
                ],
                'attr' => ['accept' => 'image/*'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => GameCategory::class,
            'csrf_token_id' => 'game_category_form',
        ]);
    }
}
