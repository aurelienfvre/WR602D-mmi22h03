<?php

// src/Form/GeneratePdfType.php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;

class GeneratePdfType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('url', UrlType::class, [
                'label' => 'Entrez une URL',
                'default_protocol' => 'https',
                'attr' => [
                    'placeholder' => 'https://example.com',
                ],
                'required' => false,
            ])
            ->add('uploadedFile', FileType::class, [
                'label' => 'Ou téléchargez un fichier',
                'required' => false,
                'mapped' => false, // Ne pas lier directement à une entité
                'constraints' => [
                    new File([
                        'maxSize' => '10M',
                        'mimeTypes' => [
                            'text/html',
                            'text/plain',
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        ],
                        'mimeTypesMessage' => 'Veuillez télécharger un fichier de type HTML,
                        texte, image (JPEG, PNG, GIF), PDF ou Word (.doc, .docx).',
                    ])
                ],
                'attr' => [
                    'class' => 'file-input',
                    'accept' => '.html,.txt,.pdf,.jpeg,.jpg,.png,.gif,.doc,.docx',
                    'data-dropzone' => 'true'
                ],
            ])
            ->add('sendByEmail', CheckboxType::class, [
                'label' => 'Envoyer par email',
                'required' => false,
            ])
            ->add('emailAddress', EmailType::class, [
                'label' => 'Adresse email',
                'required' => false,
                'attr' => [
                    'placeholder' => 'votre@email.com',
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Générer le PDF',
            ]);

        // Ajouter un écouteur d'événement pour valider le formulaire
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            $data = $form->getData();

            // Vérifier si à la fois l'URL et le fichier uploadé sont vides
            if (empty($data['url']) && !$form->get('uploadedFile')->getData()) {
                $form->addError(new FormError('Veuillez soit entrer une URL, soit télécharger un fichier.'));
            }

            // Vérifier si l'email est requis mais non renseigné
            if (isset($data['sendByEmail']) && $data['sendByEmail'] && empty($data['emailAddress'])) {
                $form->get('emailAddress')->addError(new FormError('L\'adresse email est requise lorsque
                 l\'option d\'envoi par email est activée.'));
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
