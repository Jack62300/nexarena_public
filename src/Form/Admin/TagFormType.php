<?php

namespace App\Form\Admin;

use App\Entity\Tag;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class TagFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => false,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom est obligatoire.'),
                    new Assert\Length(
                        min: 2,
                        max: 30,
                        minMessage: 'Le nom doit faire au moins {{ limit }} caractères.',
                        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.',
                    ),
                ],
                'attr' => ['placeholder' => 'Ex: PvP, ESX, QBCore...', 'maxlength' => 30],
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
                'label' => 'Actif',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Tag::class,
            'csrf_token_id' => 'tag_form',
        ]);
    }
}
