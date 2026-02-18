<?php

namespace App\Form;

use App\Entity\FeaturedBooking;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class FeaturedBookingFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('scope', ChoiceType::class, [
                'label' => false,
                'choices' => [
                    'Page d\'accueil' => FeaturedBooking::SCOPE_HOMEPAGE,
                    'Page de jeu' => FeaturedBooking::SCOPE_GAME,
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le scope est obligatoire.'),
                ],
            ])
            ->add('position', IntegerType::class, [
                'label' => false,
                'constraints' => [
                    new Assert\NotBlank(message: 'La position est obligatoire.'),
                    new Assert\Range(
                        min: 1,
                        max: 5,
                        notInRangeMessage: 'La position doit être entre {{ min }} et {{ max }}.',
                    ),
                ],
                'attr' => ['min' => 1, 'max' => 5],
            ])
            ->add('startsAt', DateTimeType::class, [
                'label' => false,
                'widget' => 'single_text',
                'constraints' => [
                    new Assert\NotBlank(message: 'La date de début est obligatoire.'),
                ],
            ])
            ->add('duration', ChoiceType::class, [
                'label' => false,
                'mapped' => false,
                'choices' => [
                    '12 heures' => 12,
                    '24 heures' => 24,
                    '36 heures' => 36,
                    '48 heures' => 48,
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'La durée est obligatoire.'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FeaturedBooking::class,
            'csrf_token_id' => 'featured_booking_form',
        ]);
    }
}
