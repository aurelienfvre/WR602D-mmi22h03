<?php

namespace App\Controller;

use App\Form\GeneratePdfType;
use App\Service\GotenbergService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GeneratePdfController extends AbstractController
{
    private GotenbergService $gotenbergService;

    public function __construct(GotenbergService $gotenbergService)
    {
        $this->gotenbergService = $gotenbergService;
    }

    #[Route('/pdf', name: 'pdf_generate')]
    public function generatePdf(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        // Décommenter pour sécuriser la route
        // $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $form = $this->createForm(GeneratePdfType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();

            // Débogage
            error_log("Données du formulaire : " . print_r($formData, true));

            $url = $formData['url'];

            // Débogage
            error_log("URL extraite : " . $url);

            // Ajouter un target="_blank" n'est pas possible directement avec les réponses HTTP
            // Nous utilisons Content-Disposition: inline pour que le navigateur ouvre le PDF
            return $this->gotenbergService->generatePdfFromUrl($url);
        }

        return $this->render('pdf/generate_pdf.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
