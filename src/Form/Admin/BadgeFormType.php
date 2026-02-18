<?php

namespace App\Form\Admin;

use App\Entity\Badge;
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

class BadgeFormType extends AbstractType
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
            ->add('criteriaType', ChoiceType::class, [
                'label' => false,
                'mapped' => false,
                'required' => false,
                'placeholder' => '-- Aucun critère automatique --',
                'choices' => [
                    'Nombre de votes' => 'vote_count',
                    'Nombre de serveurs' => 'server_count',
                    'Ancienneté du compte' => 'account_age',
                    'Nombre de commentaires' => 'comment_count',
                    'Achat premium' => 'premium_purchase',
                    'Manuel (personnalisé)' => 'custom',
                ],
            ])
            ->add('criteriaThreshold', IntegerType::class, [
                'label' => false,
                'mapped' => false,
                'required' => false,
                'attr' => ['min' => 1],
                'empty_data' => '1',
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Badge actif',
                'required' => false,
            ])
            ->add('iconFile', FileType::class, [
                'label' => false,
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Assert\File([
                        'maxSize' => '1M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'],
                        'mimeTypesMessage' => 'Format non autorisé. Utilisez JPEG, PNG, GIF, WebP ou SVG.',
                        'maxSizeMessage' => "L'icône ne doit pas dépasser 1 Mo.",
                    ]),
                ],
                'attr' => ['accept' => 'image/*'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Badge::class,
            'csrf_token_id' => 'badge_form',
        ]);
    }
}
