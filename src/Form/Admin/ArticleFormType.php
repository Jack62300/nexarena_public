<?php

namespace App\Form\Admin;

use App\Entity\Article;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ArticleFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => false,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le titre est obligatoire.'),
                    new Assert\Length(
                        min: 3,
                        max: 255,
                        minMessage: 'Le titre doit faire au moins {{ limit }} caractères.',
                        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.',
                    ),
                ],
            ])
            ->add('content', TextareaType::class, [
                'label' => false,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le contenu est obligatoire.'),
                ],
                'attr' => ['rows' => 10],
            ])
            ->add('isPublished', CheckboxType::class, [
                'label' => "Publier l'article",
                'required' => false,
            ])
            ->add('imageFile', FileType::class, [
                'label' => false,
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Assert\File([
                        'maxSize' => '5M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                        'mimeTypesMessage' => 'Format non autorisé. Utilisez JPEG, PNG, GIF ou WebP.',
                        'maxSizeMessage' => "L'image ne doit pas dépasser 5 Mo.",
                    ]),
                ],
                'attr' => ['accept' => 'image/jpeg,image/png,image/gif,image/webp'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Article::class,
            'csrf_token_id' => 'article_form',
        ]);
    }
}
