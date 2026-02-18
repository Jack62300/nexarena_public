<?php

namespace App\Form\Admin;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class AdminUserFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $roleChoices = [];
        foreach ($options['role_choices'] as $role) {
            $roleChoices[$role->getName()] = $role->getTechnicalName();
        }

        $builder
            ->add('username', TextType::class, [
                'label' => false,
                'constraints' => [
                    new Assert\NotBlank(message: "Le nom d'utilisateur est obligatoire."),
                    new Assert\Length(
                        min: 3,
                        max: 30,
                        minMessage: "Le nom d'utilisateur doit faire au moins {{ limit }} caractères.",
                        maxMessage: "Le nom d'utilisateur ne peut pas dépasser {{ limit }} caractères.",
                    ),
                ],
            ])
            ->add('rolesField', ChoiceType::class, [
                'label' => false,
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'expanded' => true,
                'choices' => $roleChoices,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'csrf_token_id' => 'user_edit',
            'role_choices' => [],
        ]);

        $resolver->setAllowedTypes('role_choices', 'array');
    }
}
