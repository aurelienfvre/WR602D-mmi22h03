<?php

namespace App\Controller;

use App\Service\GotenbergService;
use App\Service\PdfQueueService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PdfController extends AbstractController
{
    private GotenbergService $gotenbergService;
    private PdfQueueService $pdfQueueService;

    public function __construct(
        GotenbergService $gotenbergService,
        PdfQueueService $pdfQueueService
    ) {
        $this->gotenbergService = $gotenbergService;
        $this->pdfQueueService = $pdfQueueService;
    }

    #[Route('/generate-pdf', name: 'generate_pdf')]
    public function generatePdf(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();

        // Vérifier si l'utilisateur peut générer plus de PDF selon son abonnement
        if (!$this->pdfQueueService->canUserGeneratePdf($user)) {
            $this->addFlash('error', 'Vous avez atteint la limite de votre abonnement. 
            Veuillez mettre à niveau pour générer plus de PDF.');
            return $this->redirectToRoute('subscription_change');
        }

        $htmlContent = "<html><body><h1>Mon premier PDF sécurisé avec Gotenberg</h1></body></html>";

        // Si on veut générer le PDF immédiatement mais l'enregistrer en base aussi, ajouter:
        // $this->pdfQueueService->addToQueue("html-content", $user);

        return $this->gotenbergService->generatePdfFromHtml($htmlContent, $user);
    }
}
