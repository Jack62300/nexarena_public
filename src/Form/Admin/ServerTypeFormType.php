<?php

namespace App\Form\Admin;

use App\Entity\Category;
use App\Entity\ServerType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ServerTypeFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('category', EntityType::class, [
                'label' => false,
                'class' => Category::class,
                'choice_label' => 'name',
                'placeholder' => '-- Sélectionner --',
                'constraints' => [
                    new Assert\NotBlank(message: 'La catégorie est obligatoire.'),
                ],
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
                'attr' => ['placeholder' => 'PvP, RP, Creative...'],
            ])
            ->add('position', IntegerType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['min' => 0],
                'empty_data' => '0',
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Type actif',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ServerType::class,
            'csrf_token_id' => 'server_type_form',
        ]);
    }
}
