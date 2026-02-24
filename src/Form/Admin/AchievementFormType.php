<?php

namespace App\Form\Admin;

use App\Entity\Achievement;
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

class AchievementFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => false,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom est obligatoire.'),
                    new Assert\Length(max: 100),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('rarity', ChoiceType::class, [
                'label' => false,
                'choices' => [
                    'Commun'     => Achievement::RARITY_COMMON,
                    'Peu commun' => Achievement::RARITY_UNCOMMON,
                    'Rare'       => Achievement::RARITY_RARE,
                    'Épique'     => Achievement::RARITY_EPIC,
                    'Légendaire' => Achievement::RARITY_LEGENDARY,
                ],
            ])
            ->add('criteriaType', ChoiceType::class, [
                'label' => false,
                'mapped' => false,
                'required' => false,
                'placeholder' => '-- Aucun critère automatique --',
                'choices' => [
                    'Votes reçus sur les serveurs' => 'vote_count',
                    'Nombre de serveurs créés'     => 'server_count',
                    'Ancienneté du compte (jours)' => 'account_age',
                    'Commentaires postés'          => 'comment_count',
                    'Achat premium'                => 'premium_purchase',
                    'Votes donnés'                 => 'votes_given',
                    'Manuel uniquement'            => 'custom',
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
                'label' => 'Succès actif',
                'required' => false,
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
                        'maxSizeMessage' => "L'image ne doit pas dépasser 2 Mo.",
                    ]),
                ],
                'attr' => ['accept' => 'image/*'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'    => Achievement::class,
            'csrf_token_id' => 'achievement_form',
        ]);
    }
}
