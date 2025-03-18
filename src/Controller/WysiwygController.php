<?php

namespace App\Controller;

use App\Service\GotenbergService;
use App\Service\PdfQueueService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WysiwygController extends AbstractController
{
    #[Route('/wysiwyg/generate-pdf', name: 'wysiwyg_generate_pdf', methods: ['POST'])]
    public function generatePdf(Request $request, PdfQueueService $pdfQueueService): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();
        
        // Vérifier si l'utilisateur peut générer plus de PDF
        if (!$pdfQueueService->canUserGeneratePdf($user)) {
            $this->addFlash('error', 'Vous avez atteint la limite de votre abonnement. '
                . 'Veuillez mettre à niveau pour générer plus de PDF.');
            return $this->redirectToRoute('subscription_change');
        }
        
        $htmlContent = $request->request->get('html_content');
        $emailTo = $request->request->get('email_to');
        
        if (!$htmlContent) {
            $this->addFlash('error', 'Le contenu HTML est vide.');
            return $this->redirectToRoute('pdf_generate');
        }
        
        // Créer un fichier temporaire pour stocker le contenu HTML
        $tempDir = sys_get_temp_dir() . '/' . uniqid('html_wysiwyg_');
        mkdir($tempDir, 0777, true);
        
        // Rendre le template avec le contenu de l'éditeur
        $htmlDocument = $this->renderView('pdf/pdf_document.html.twig', [
            'content' => $htmlContent
        ]);
        
        $tempFilePath = $tempDir . '/index.html';
        file_put_contents($tempFilePath, $htmlDocument);

        try {
            // Ajouter à la file d'attente au lieu de générer directement
            $queueItem = $pdfQueueService->addFileToQueue(
                $tempFilePath,
                'document_wysiwyg.html',
                $user,
                $emailTo
            );
            
            // Message de succès
            $this->addFlash('success', 'Votre document a été placé en file d\'attente pour conversion en PDF.');
            
            if ($emailTo) {
                $this->addFlash('info', 'Vous recevrez un email à '
                    . $emailTo . ' lorsque votre PDF sera prêt.');
            } else {
                $this->addFlash('info', 'Votre PDF sera disponible dans votre historique une fois généré.');
            }
            
            return $this->redirectToRoute('history');
        } catch (\Exception $e) {
            // En cas d'erreur, rediriger vers la page d'accueil avec un message d'erreur
            $this->addFlash('error', 'Une erreur est survenue lors de la génération du PDF : ' . $e->getMessage());
            return $this->redirectToRoute('pdf_generate');
        }
    }
}
