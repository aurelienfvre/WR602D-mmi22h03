<?php
// src/Controller/GeneratePdfController.php

namespace App\Controller;

use App\Entity\File;
use App\Form\GeneratePdfType;
use App\Service\GotenbergService;
use App\Service\PdfQueueService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

final class GeneratePdfController extends AbstractController
{
    private GotenbergService $gotenbergService;
    private PdfQueueService $pdfQueueService;
    private SluggerInterface $slugger;
    private EntityManagerInterface $entityManager;
    
    public function __construct(
        GotenbergService $gotenbergService,
        PdfQueueService $pdfQueueService,
        SluggerInterface $slugger,
        EntityManagerInterface $entityManager
    ) {
        $this->gotenbergService = $gotenbergService;
        $this->pdfQueueService = $pdfQueueService;
        $this->slugger = $slugger;
        $this->entityManager = $entityManager;
    }

    #[Route('/pdf', name: 'pdf_generate')]
    public function generatePdf(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();
        
        // Vérifier si l'utilisateur peut générer plus de PDF selon son abonnement
        if (!$this->pdfQueueService->canUserGeneratePdf($user)) {
            $this->addFlash('error', 'Vous avez atteint la limite de votre abonnement. 
            Veuillez mettre à niveau pour générer plus de PDF.');
            return $this->redirectToRoute('subscription_change');
        }

        $form = $this->createForm(GeneratePdfType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();
            $emailTo = null;

            // Vérifier si l'utilisateur a coché la case pour envoyer par email
            if (isset($formData['sendByEmail']) && $formData['sendByEmail'] && isset($formData['emailAddress'])) {
                $emailTo = $formData['emailAddress'];
            }

            try {
                $uploadedFile = $form->get('uploadedFile')->getData();
                
                // Si un fichier a été téléchargé
                if ($uploadedFile) {
                    // Traitement du fichier téléchargé
                    $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $this->slugger->slug($originalFilename);
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $uploadedFile->guessExtension();
                    
                    // Déplacer le fichier dans un répertoire temporaire
                    $tempDirectory = $this->getParameter('kernel.project_dir') . '/var/tmp';
                    if (!file_exists($tempDirectory)) {
                        mkdir($tempDirectory, 0777, true);
                    }
                    
                    $filePath = $tempDirectory . '/' . $newFilename;
                    $uploadedFile->move($tempDirectory, $newFilename);
                    
                    // Debug: vérifier si le fichier a été correctement déplacé
                    if (!file_exists($filePath)) {
                        throw new \Exception("Erreur: Le fichier temporaire n'a pas été créé.");
                    }
                    
                    // Générer un nom pour le fichier PDF de sortie
                    $pdfFilename = 'pdf_' . $user->getId() . '_' . (new DateTimeImmutable())->format('YmdHis') . '.pdf';
                    $pdfPath = $this->getParameter('kernel.project_dir') . '/public/uploads/pdfs/' . $pdfFilename;
                    
                    // Assurez-vous que le répertoire de destination existe
                    $pdfDir = dirname($pdfPath);
                    if (!file_exists($pdfDir)) {
                        mkdir($pdfDir, 0777, true);
                    }
                    
                    // Convertir le fichier en PDF
                    $result = $this->gotenbergService->generatePdfFromFile(
                        $filePath,
                        $pdfPath,
                        $originalFilename,
                        $user
                    );
                    
                    // Supprimer le fichier temporaire
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                    
                    // Si la conversion a réussi, créer une entité File
                    if ($result) {
                        $file = new File();
                        $file->setName($pdfFilename);
                        $file->setCreatedAt(new DateTimeImmutable());
                        $file->setUser($user);
                        
                        $this->entityManager->persist($file);
                        $this->entityManager->flush();
                        
                        // Si un email est spécifié
                        if ($emailTo) {
                            $this->addFlash('info', 'Vous recevrez un email à ' . $emailTo . ' avec votre PDF.');
                        }
                        
                        $this->addFlash('success', 'Votre PDF a été généré avec succès.');
                        return $this->redirectToRoute('history');
                    } else {
                        throw new \Exception("Erreur lors de la génération du PDF.");
                    }
                } elseif (!empty($formData['url'])) {
                    // Ajouter à la file d'attente
                    $this->pdfQueueService->addToQueue($formData['url'], $user, $emailTo);
                    
                    // Rediriger avec un message
                    $this->addFlash('success', 'Votre demande de génération de PDF 
                    a été placée en file d\'attente. Le document sera généré prochainement.');
                    
                    if ($emailTo) {
                        $this->addFlash('info', 'Vous recevrez un email à '
                        . $emailTo . ' lorsque votre PDF sera prêt.');
                    } else {
                        $this->addFlash('info', 'Votre PDF sera disponible dans votre historique une fois généré.');
                    }
                    
                    return $this->redirectToRoute('history');
                } else {
                    $this->addFlash('error', 'Veuillez soit entrer une URL, soit télécharger un fichier.');
                }
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur: ' . $e->getMessage());
            }
        }

        // Récupérer les informations sur l'abonnement pour les afficher
        $subscription = $user->getSubscription();
        $maxPdf = $subscription ? $subscription->getMaxPdf() : 0;
        
        return $this->render('pdf/generate_pdf.html.twig', [
            'form' => $form->createView(),
            'subscription' => $subscription,
            'maxPdf' => $maxPdf
        ]);
    }
}
