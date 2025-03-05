<?php

namespace App\Tests\Service;

use App\Service\GotenbergService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GotenbergServiceTest extends TestCase
{
    public function testGeneratePdfFromHtml()
    {
        // Création d'une réponse simulée
        $mockResponse = new MockResponse('PDF content', ['http_code' => 200]);
        $mockHttpClient = new MockHttpClient($mockResponse);
        
        // Création du service avec le client HTTP simulé
        $gotenbergService = new GotenbergService($mockHttpClient, 'http://symfony-gotenberg:3000');
        
        // Test de la méthode
        $response = $gotenbergService->generatePdfFromHtml('<html><body>Test</body></html>');
        
        // Vérifications
        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertEquals('inline; filename="document.pdf"', $response->headers->get('Content-Disposition'));
    }
    
    public function testGeneratePdfFromUrl()
    {
        // Création d'une réponse simulée
        $mockResponse = new MockResponse('PDF content', ['http_code' => 200]);
        $mockHttpClient = new MockHttpClient($mockResponse);
        
        // Création du service avec le client HTTP simulé
        $gotenbergService = new GotenbergService($mockHttpClient, 'http://symfony-gotenberg:3000');
        
        // Test de la méthode
        $response = $gotenbergService->generatePdfFromUrl('https://example.com');
        
        // Vérifications
        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertEquals('inline; filename="document.pdf"', $response->headers->get('Content-Disposition'));
    }
}