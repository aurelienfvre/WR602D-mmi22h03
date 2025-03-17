<?php

namespace App\Tests\Service;

use App\Entity\PdfGenerationQueue;
use App\Entity\Subscription;
use App\Entity\User;
use App\Repository\FileRepository;
use App\Repository\PdfGenerationQueueRepository;
use App\Service\PdfQueueService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class PdfQueueServiceTest extends TestCase
{
    private $mockEntityManager;
    private $mockQueueRepository;
    private $mockFileRepository;
    private $pdfQueueService;

    protected function setUp(): void
    {
        // Créer des mocks pour les dépendances
        $this->mockEntityManager = $this->createMock(EntityManagerInterface::class);
        $this->mockQueueRepository = $this->createMock(PdfGenerationQueueRepository::class);
        $this->mockFileRepository = $this->createMock(FileRepository::class);

        // Créer le service avec les mocks
        $this->pdfQueueService = new PdfQueueService(
            $this->mockEntityManager,
            $this->mockQueueRepository,
            $this->mockFileRepository
        );
    }

    public function testCanUserGeneratePdf()
    {
        // Créer un mock d'abonnement
        $subscription = new Subscription();
        $subscription->setMaxPdf(10);

        // Créer un mock d'utilisateur avec l'abonnement
        $user = $this->createMock(User::class);
        $user->method('getSubscription')->willReturn($subscription);
        $user->method('getId')->willReturn(1);

        // Configurer le mock du FileRepository pour retourner un nombre de fichiers
        $this->mockFileRepository->method('countUserFiles')->willReturn(5);

        // Tester que l'utilisateur peut générer un PDF
        $canGenerate = $this->pdfQueueService->canUserGeneratePdf($user);
        $this->assertTrue($canGenerate);

        // Reconfigurer le mock pour tester la limite atteinte
        $this->mockFileRepository = $this->createMock(FileRepository::class);
        $this->mockFileRepository->method('countUserFiles')->willReturn(10);

        // Recréer le service avec le nouveau mock
        $this->pdfQueueService = new PdfQueueService(
            $this->mockEntityManager,
            $this->mockQueueRepository,
            $this->mockFileRepository
        );

        // Tester que l'utilisateur ne peut plus générer de PDF
        $canGenerate = $this->pdfQueueService->canUserGeneratePdf($user);
        $this->assertFalse($canGenerate);
    }

    public function testAddToQueue()
    {
        // Créer un utilisateur qui peut générer des PDFs
        $subscription = new Subscription();
        $subscription->setMaxPdf(10);

        $user = $this->createMock(User::class);
        $user->method('getSubscription')->willReturn($subscription);
        $user->method('getId')->willReturn(1);

        // Configurer le FileRepository pour permettre la génération
        $this->mockFileRepository->method('countUserFiles')->willReturn(5);

        // Configurer l'EntityManager pour vérifier qu'un élément est ajouté à la file
        $this->mockEntityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($queueItem) use ($user) {
                return $queueItem instanceof PdfGenerationQueue
                    && $queueItem->getUrl() === 'https://example.com'
                    && $queueItem->getStatus() === 'pending'
                    && $queueItem->getUser() === $user
                    && $queueItem->getEmailTo() === 'test@example.com';
            }));

        $this->mockEntityManager->expects($this->once())
            ->method('flush');

        // Appeler la méthode
        $queueItem = $this->pdfQueueService->addToQueue('https://example.com', $user, 'test@example.com');

        // Vérifier que l'élément retourné est correct
        $this->assertInstanceOf(PdfGenerationQueue::class, $queueItem);
        $this->assertEquals('https://example.com', $queueItem->getUrl());
        $this->assertEquals('pending', $queueItem->getStatus());
        $this->assertEquals($user, $queueItem->getUser());
        $this->assertEquals('test@example.com', $queueItem->getEmailTo());
    }

    public function testAddToQueueWhenLimitReached()
    {
        // Créer un utilisateur qui a atteint sa limite
        $subscription = new Subscription();
        $subscription->setMaxPdf(10);

        $user = $this->createMock(User::class);
        $user->method('getSubscription')->willReturn($subscription);
        $user->method('getId')->willReturn(1);

        // Configurer le FileRepository pour indiquer que la limite est atteinte
        $this->mockFileRepository->method('countUserFiles')->willReturn(10);

        // L'EntityManager ne devrait pas être appelé
        $this->mockEntityManager->expects($this->never())
            ->method('persist');

        $this->mockEntityManager->expects($this->never())
            ->method('flush');

        // Vérifier qu'une exception est lancée
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Limite d'abonnement atteinte");

        // Appeler la méthode
        $this->pdfQueueService->addToQueue('https://example.com', $user);
    }

    public function testGetNextBatch()
    {
        // Créer quelques éléments de file d'attente
        $queueItems = [
            new PdfGenerationQueue(),
            new PdfGenerationQueue()
        ];

        // Configurer le QueueRepository pour retourner ces éléments
        $this->mockQueueRepository->expects($this->once())
            ->method('findBy')
            ->with(
                ['status' => 'pending'],
                ['createdAt' => 'ASC'],
                5
            )
            ->willReturn($queueItems);

        // Appeler la méthode
        $result = $this->pdfQueueService->getNextBatch(5);

        // Vérifier le résultat
        $this->assertSame($queueItems, $result);
    }

    public function testMarkAsProcessing()
    {
        // Créer un élément de file d'attente
        $queueItem = new PdfGenerationQueue();

        // Vérifier que l'EntityManager est appelé pour mettre à jour le statut
        $this->mockEntityManager->expects($this->once())
            ->method('flush');

        // Appeler la méthode
        $this->pdfQueueService->markAsProcessing($queueItem);

        // Vérifier que le statut a été mis à jour
        $this->assertEquals('processing', $queueItem->getStatus());
    }

    public function testMarkAsCompleted()
    {
        // Créer un élément de file d'attente
        $queueItem = new PdfGenerationQueue();

        // Vérifier que l'EntityManager est appelé pour mettre à jour le statut
        $this->mockEntityManager->expects($this->once())
            ->method('flush');

        // Appeler la méthode
        $this->pdfQueueService->markAsCompleted($queueItem, '/path/to/file.pdf');

        // Vérifier que le statut et le chemin du fichier ont été mis à jour
        $this->assertEquals('completed', $queueItem->getStatus());
        $this->assertEquals('/path/to/file.pdf', $queueItem->getFilePath());
        $this->assertNotNull($queueItem->getProcessedAt());
    }

    public function testMarkAsFailed()
    {
        // Créer un élément de file d'attente
        $queueItem = new PdfGenerationQueue();

        // Vérifier que l'EntityManager est appelé pour mettre à jour le statut
        $this->mockEntityManager->expects($this->once())
            ->method('flush');

        // Appeler la méthode
        $this->pdfQueueService->markAsFailed($queueItem);

        // Vérifier que le statut a été mis à jour
        $this->assertEquals('failed', $queueItem->getStatus());
        $this->assertNotNull($queueItem->getProcessedAt());
    }
}