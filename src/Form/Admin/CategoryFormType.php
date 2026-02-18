<?php

namespace App\Form\Admin;

use App\Entity\Category;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class CategoryFormType extends AbstractType
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
            ->add('icon', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['placeholder' => 'fas fa-server'],
            ])
            ->add('description', TextareaType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['rows' => 4],
            ])
            ->add('position', IntegerType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['min' => 0],
                'empty_data' => '0',
            ])
            ->add('queryType', ChoiceType::class, [
                'label' => false,
                'required' => false,
                'placeholder' => 'Aucun (pas de query)',
                'choices' => [
                    'FiveM / RedM (CFX)' => 'cfx',
                    'Source Engine (Rust, CS2, GMod, ARK, DayZ...)' => 'source',
                    'Minecraft' => 'minecraft',
                ],
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
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Category::class,
            'csrf_token_id' => 'category_form',
        ]);
    }
}
