<?php

namespace App\Form\Admin;

use App\Entity\Role;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class RoleFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isSystem = $options['is_system'];

        $builder
            ->add('name', TextType::class, [
                'label' => false,
                'disabled' => $isSystem,
                'constraints' => $isSystem ? [] : [
                    new Assert\NotBlank(message: 'Le nom du rôle est obligatoire.'),
                    new Assert\Length(
                        min: 2,
                        max: 100,
                        minMessage: 'Le nom doit faire au moins {{ limit }} caractères.',
                        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.',
                    ),
                ],
                'attr' => ['placeholder' => 'Ex: Modérateur'],
            ])
            ->add('color', TextType::class, [
                'label' => false,
                'constraints' => [
                    new Assert\NotBlank(message: 'La couleur est obligatoire.'),
                    new Assert\Regex(
                        pattern: '/^#[0-9a-fA-F]{6}$/',
                        message: 'La couleur doit être au format hexadécimal (ex: #5a5c69).',
                    ),
                ],
            ])
            ->add('position', IntegerType::class, [
                'label' => false,
                'disabled' => $isSystem,
                'attr' => ['min' => 0, 'max' => 100],
                'empty_data' => '5',
            ])
            ->add('description', TextType::class, [
                'label' => false,
                'required' => false,
                'empty_data' => null,
                'attr' => ['placeholder' => 'Description courte'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Role::class,
            'csrf_token_id' => 'role_form',
            'is_system' => false,
        ]);

        $resolver->setAllowedTypes('is_system', 'bool');
    }
}
