<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Entity\Subscription;
use App\Service\GotenbergService;
use App\Repository\FileRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GotenbergServiceTest extends TestCase
{
    private $mockHttpClient;
    private $mockFileRepository;
    private $mockEntityManager;
    private $gotenbergService;

    protected function setUp(): void
    {
        // Création des mocks pour les dépendances
        $this->mockHttpClient = new MockHttpClient([
            new MockResponse('PDF content', ['http_code' => 200])
        ]);

        // Mock pour FileRepository
        $this->mockFileRepository = $this->createMock(FileRepository::class);

        // Mock pour EntityManagerInterface
        $this->mockEntityManager = $this->createMock(EntityManagerInterface::class);

        // Création du service avec tous les mocks nécessaires
        $this->gotenbergService = new GotenbergService(
            $this->mockHttpClient,
            'http://symfony-gotenberg:3000',
            $this->mockFileRepository,
            $this->mockEntityManager
        );
    }

    public function testGeneratePdfFromHtml()
    {
        // Test de la méthode
        $response = $this->gotenbergService->generatePdfFromHtml('<html><body>Test</body></html>');

        // Vérifications
        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertEquals('inline; filename="document.pdf"', $response->headers->get('Content-Disposition'));
    }

    public function testGeneratePdfFromUrl()
    {
        // Test de la méthode
        $response = $this->gotenbergService->generatePdfFromUrl('https://example.com');

        // Vérifications
        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertEquals('inline; filename="document.pdf"', $response->headers->get('Content-Disposition'));
    }

    public function testCanUserGeneratePdf()
    {
        // Créer un mock d'utilisateur avec abonnement
        $subscription = new Subscription();
        $subscription->setMaxPdf(10);

        $user = $this->createMock(User::class);
        $user->method('getSubscription')->willReturn($subscription);
        $user->method('getId')->willReturn(1);

        // Configurer le mock du FileRepository pour retourner un nombre de fichiers
        $this->mockFileRepository->method('countFileGeneratedByUserOnDate')->willReturn(5);

        // Tester que l'utilisateur peut générer un PDF
        $canGenerate = $this->gotenbergService->canUserGeneratePdf($user);
        $this->assertTrue($canGenerate);

        // Reconfigurer le mock pour tester la limite atteinte
        $this->mockFileRepository = $this->createMock(FileRepository::class);
        $this->mockFileRepository->method('countFileGeneratedByUserOnDate')->willReturn(10);

        // Recréer le service avec le nouveau mock
        $this->gotenbergService = new GotenbergService(
            $this->mockHttpClient,
            'http://symfony-gotenberg:3000',
            $this->mockFileRepository,
            $this->mockEntityManager
        );

        // Tester que l'utilisateur ne peut plus générer de PDF
        $canGenerate = $this->gotenbergService->canUserGeneratePdf($user);
        $this->assertFalse($canGenerate);
    }
}