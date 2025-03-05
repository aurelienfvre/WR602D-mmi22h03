<?php

namespace App\Controller;

use App\Service\GotenbergService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PdfController extends AbstractController
{
    private GotenbergService $gotenbergService;

    public function __construct(GotenbergService $gotenbergService)
    {
        $this->gotenbergService = $gotenbergService;
    }

    #[Route('/generate-pdf', name: 'generate_pdf')]
    public function generatePdf(): Response
    {
        #$this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $htmlContent = "<html><body><h1>Mon premier PDF sécurisé avec Gotenberg</h1></body></html>";

        return $this->gotenbergService->generatePdfFromHtml($htmlContent);
    }
}
