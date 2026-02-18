<?php

namespace App\Form\Admin;

use App\Entity\Server;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class AdminServerFormType extends AbstractType
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
            ->add('shortDescription', TextType::class, [
                'label' => false,
                'required' => false,
            ])
            ->add('ip', TextType::class, [
                'label' => false,
                'required' => false,
            ])
            ->add('port', IntegerType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['min' => 1, 'max' => 65535],
            ])
            ->add('slots', IntegerType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['min' => 0],
                'empty_data' => '0',
            ])
            ->add('website', TextType::class, [
                'label' => false,
                'required' => false,
                'constraints' => [
                    new Assert\Url(message: "L'URL du site n'est pas valide.", requireTld: true),
                ],
                'attr' => ['placeholder' => 'https://'],
            ])
            ->add('discordUrl', TextType::class, [
                'label' => false,
                'required' => false,
                'constraints' => [
                    new Assert\Url(message: "L'URL Discord n'est pas valide.", requireTld: true),
                ],
                'attr' => ['placeholder' => 'https://discord.gg/'],
            ])
            ->add('twitchChannel', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['placeholder' => 'nom_chaine'],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Actif',
                'required' => false,
            ])
            ->add('isApproved', CheckboxType::class, [
                'label' => 'Approuvé',
                'required' => false,
            ])
            ->add('isPrivate', CheckboxType::class, [
                'label' => 'Privé',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Server::class,
            'csrf_token_id' => 'admin_server_form',
        ]);
    }
}
