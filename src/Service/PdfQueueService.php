<?php
// src/Service/PdfQueueService.php

namespace App\Service;

use App\Entity\PdfGenerationQueue;
use App\Entity\User;
use App\Repository\FileRepository;
use App\Repository\PdfGenerationQueueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class PdfQueueService
{
    private EntityManagerInterface $entityManager;
    private PdfGenerationQueueRepository $queueRepository;
    private FileRepository $fileRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        PdfGenerationQueueRepository $queueRepository,
        FileRepository $fileRepository
    ) {
        $this->entityManager = $entityManager;
        $this->queueRepository = $queueRepository;
        $this->fileRepository = $fileRepository;
    }

    /**
     * Vérifie si l'utilisateur peut générer un nouveau PDF selon son abonnement
     */
    public function canUserGeneratePdf(UserInterface $user): bool
    {
        // Vérifier si l'utilisateur a un abonnement
        if (!$user instanceof User || !$user->getSubscription()) {
            return false;
        }

        // Récupérer la limite de l'abonnement
        $maxPdf = $user->getSubscription()->getMaxPdf();
        
        // Compter les fichiers PDF existants
        $currentPdfCount = $this->fileRepository->countUserFiles($user->getId());
        
        // Vérifier si l'utilisateur peut générer plus de PDF
        return $currentPdfCount < $maxPdf;
    }

    public function addToQueue(string $url, UserInterface $user, ?string $emailTo = null): PdfGenerationQueue
    {
        // Vérifier si l'utilisateur peut générer un nouveau PDF
        if (!$this->canUserGeneratePdf($user)) {
            throw new \Exception("Limite d'abonnement atteinte. Impossible de générer plus de PDF.");
        }
        
        $queueItem = new PdfGenerationQueue();
        $queueItem->setUrl($url);
        $queueItem->setStatus('pending');
        $queueItem->setCreatedAt(new \DateTimeImmutable());
        $queueItem->setUser($user);
        
        if ($emailTo) {
            $queueItem->setEmailTo($emailTo);
        }

        $this->entityManager->persist($queueItem);
        $this->entityManager->flush();

        return $queueItem;
    }

    public function getNextBatch(int $limit = 10): array
    {
        return $this->queueRepository->findBy(
            ['status' => 'pending'],
            ['createdAt' => 'ASC'],
            $limit
        );
    }

    public function markAsProcessing(PdfGenerationQueue $queueItem): void
    {
        $queueItem->setStatus('processing');
        $this->entityManager->flush();
    }

    public function markAsCompleted(PdfGenerationQueue $queueItem, string $filePath): void
    {
        $queueItem->setStatus('completed');
        $queueItem->setProcessedAt(new \DateTimeImmutable());
        $queueItem->setFilePath($filePath);
        $this->entityManager->flush();
    }

    public function markAsFailed(PdfGenerationQueue $queueItem): void
    {
        $queueItem->setStatus('failed');
        $queueItem->setProcessedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }
}
