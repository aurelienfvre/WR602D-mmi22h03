<?php

// src/Controller/GeneratePdfController.php

namespace App\Controller;

use App\Form\GeneratePdfType;
use App\Service\PdfQueueService;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

final class GeneratePdfController extends AbstractController
{
    private PdfQueueService $pdfQueueService;
    private SluggerInterface $slugger;

    public function __construct(
        PdfQueueService $pdfQueueService,
        SluggerInterface $slugger
    ) {
        $this->pdfQueueService = $pdfQueueService;
        $this->slugger = $slugger;
    }

    #[Route('/pdf', name: 'pdf_generate')]
    public function generatePdf(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();

        // Vérifier si l'utilisateur peut générer plus de PDF selon son abonnement
        if (!$this->pdfQueueService->canUserGeneratePdf($user)) {
            $this->addFlash('error', 'Vous avez atteint la limite de votre abonnement. '
                . 'Veuillez mettre à niveau pour générer plus de PDF.');
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
                    $extension = $uploadedFile->guessExtension() ?: 'bin';
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $extension;

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

                    // Ajouter à la file d'attente
                    $this->pdfQueueService->addFileToQueue(
                        $filePath,
                        $originalFilename,
                        $user,
                        $emailTo
                    );

                    // Message de succès
                    $this->addFlash('success', 'Votre fichier a été placé en file d\'attente pour conversion en PDF.');

                    if ($emailTo) {
                        $this->addFlash('info', 'Vous recevrez un email à '
                            . $emailTo . ' lorsque votre PDF sera prêt.');
                    } else {
                        $this->addFlash('info', 'Votre PDF sera disponible dans votre historique une fois généré.');
                    }

                    return $this->redirectToRoute('history');
                } elseif (!empty($formData['url'])) {
                    // Ajouter à la file d'attente
                    $this->pdfQueueService->addToQueue($formData['url'], $user, $emailTo);

                    // Rediriger avec un message
                    $this->addFlash('success', 'Votre demande de génération de PDF '
                        . 'a été placée en file d\'attente. Le document sera généré prochainement.');

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
                error_log("Exception dans GeneratePdfController: " . $e->getMessage());
                error_log($e->getTraceAsString());
                $this->addFlash('error', 'Erreur: ' . $e->getMessage());
            }
        } elseif ($form->isSubmitted()) {
            // Si le formulaire est soumis mais non valide, afficher les erreurs
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
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
