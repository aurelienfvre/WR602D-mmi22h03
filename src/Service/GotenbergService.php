<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GotenbergService
{
    private HttpClientInterface $client;
    private string $gotenbergUrl;

    public function __construct(HttpClientInterface $client, string $gotenbergUrl)
    {
        $this->client = $client;
        $this->gotenbergUrl = $gotenbergUrl;
    }

    public function generatePdfFromHtml(string $htmlContent): Response
    {
        // Création d'un fichier temporaire nommé `index.html`
        $tmpDir = sys_get_temp_dir();
        $tmpFile = $tmpDir . '/index.html';
        file_put_contents($tmpFile, $htmlContent);

        try {
            $response = $this->client->request('POST', $this->gotenbergUrl . '/forms/chromium/convert/html', [
                'headers' => [
                    'Content-Type' => 'multipart/form-data',
                ],
                'body' => [
                    'files' => fopen($tmpFile, 'r'), // Envoi du fichier en tant que "index.html"
                ],
            ]);

            // Supprimer le fichier temporaire après l'envoi
            unlink($tmpFile);

            if ($response->getStatusCode() !== 200) {
                return new Response("Erreur lors de la génération du PDF : " . 
                $response->getContent(false), $response->getStatusCode());
            }

            return new StreamedResponse(function () use ($response) {
                echo $response->getContent();
            }, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="document.pdf"'
            ]);
        } catch (\Exception $e) {
            // Log l'erreur
            error_log("Erreur lors de la génération du PDF depuis HTML : " . $e->getMessage());
            return new Response("Erreur lors de la génération du PDF : " . $e->getMessage(), 500);
        }
    }

    public function generatePdfFromUrl(string $url): Response
    {
        try {
            error_log("Début de la requête pour l'URL : " . $url);
            error_log("URL Gotenberg complète : " . $this->gotenbergUrl . '/forms/chromium/convert/url');
            
            $response = $this->client->request('POST', $this->gotenbergUrl . '/forms/chromium/convert/url', [
                'headers' => [
                    'Content-Type' => 'multipart/form-data',
                ],
                'body' => [
                    'url' => $url,
                ],
            ]);
            
            error_log("Requête envoyée. Code de statut : " . $response->getStatusCode());


            if ($response->getStatusCode() !== 200) {
                return new Response("Erreur lors de la génération du PDF : " . 
                    $response->getContent(false), $response->getStatusCode());
            }

            return new StreamedResponse(function () use ($response) {
                echo $response->getContent();
            }, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="document.pdf"'
            ]);
        } catch (\Exception $e) {
            // Log l'erreur
            error_log("Erreur lors de la génération du PDF depuis URL : " . $e->getMessage());
            return new Response("Erreur lors de la génération du PDF : " . $e->getMessage(), 500);
        }
    }
}