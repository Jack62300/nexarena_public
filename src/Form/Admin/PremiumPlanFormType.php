<?php

namespace App\Form\Admin;

use App\Entity\PremiumPlan;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class PremiumPlanFormType extends AbstractType
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
            ->add('price', MoneyType::class, [
                'label' => false,
                'required' => false,
                'currency' => false,
                'divisor' => 1,
                'attr' => ['placeholder' => '0.00'],
            ])
            ->add('currency', ChoiceType::class, [
                'label' => false,
                'choices' => [
                    'EUR (€)' => 'EUR',
                    'USD ($)' => 'USD',
                    'GBP (£)' => 'GBP',
                ],
                'empty_data' => 'EUR',
            ])
            ->add('nexbitsPrice', IntegerType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['min' => 0, 'placeholder' => '0'],
                'empty_data' => '0',
            ])
            ->add('tokensGiven', IntegerType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['min' => 0],
                'empty_data' => '0',
            ])
            ->add('boostTokensGiven', IntegerType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['min' => 0],
                'empty_data' => '0',
            ])
            ->add('planType', ChoiceType::class, [
                'label' => false,
                'choices' => [
                    'Par défaut' => PremiumPlan::TYPE_DEFAULT,
                    'Personnalisé' => PremiumPlan::TYPE_CUSTOM,
                ],
                'empty_data' => PremiumPlan::TYPE_DEFAULT,
            ])
            ->add('position', IntegerType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['min' => 0],
                'empty_data' => '0',
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Plan actif',
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
            'data_class' => PremiumPlan::class,
            'csrf_token_id' => 'premium_plan_form',
        ]);
    }
}
