<?php

namespace App\Controller;

use App\Repository\FileRepository;
use App\Repository\PdfGenerationQueueRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HistoryController extends AbstractController
{
    #[Route('/history', name: 'history')]
    public function index(FileRepository $fileRepository, PdfGenerationQueueRepository $queueRepository): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $user = $this->getUser();
        $subscription = $user->getSubscription();
        $maxPdf = $subscription ? $subscription->getMaxPdf() : 0;
        
        // Récupérer tous les fichiers avec leur statut de verrouillage
        $filesWithStatus = $fileRepository->findUserFilesWithLockStatus($user->getId(), $maxPdf);
        
        // Récupérer les PDFs en file d'attente (pending, processing)
        $pendingFiles = $queueRepository->findBy([
            'user' => $user,
            'status' => ['pending', 'processing', 'failed']
        ], ['createdAt' => 'DESC']);
        
        // Transformer les éléments en queue en format compatible avec l'affichage
        $pendingFilesForDisplay = [];
        foreach ($pendingFiles as $queueItem) {
            $pendingFilesForDisplay[] = [
                'file' => null,
                'queueItem' => $queueItem,
                'status' => $queueItem->getStatus(),
                'locked' => false
            ];
        }
        
        // Fusionner les deux collections
        $allFiles = array_merge($pendingFilesForDisplay, $filesWithStatus);
        
        // Trier par date de création (du plus récent au plus ancien)
        usort($allFiles, function ($a, $b) {
            $dateA = ($a['queueItem'] ?? null) ? $a['queueItem']->getCreatedAt() : $a['file']->getCreatedAt();
            $dateB = ($b['queueItem'] ?? null) ? $b['queueItem']->getCreatedAt() : $b['file']->getCreatedAt();
            
            return $dateB <=> $dateA; // tri décroissant (du plus récent au plus ancien)
        });
        
        // Compter le nombre total de fichiers (complétés uniquement)
        $totalFiles = count($filesWithStatus);
        
        // Calculer le nombre de PDF restants
        $remainingPdfs = $maxPdf - $totalFiles;
        
        // Compter le nombre de fichiers verrouillés
        $lockedFiles = array_reduce($filesWithStatus, function ($count, $item) {
            return $count + ($item['locked'] ? 1 : 0);
        }, 0);
        
        // Compter le nombre de fichiers en cours de génération
        $pendingFilesCount = count($pendingFiles);
        
        return $this->render('history/index.html.twig', [
            'filesWithStatus' => $allFiles,
            'totalFiles' => $totalFiles,
            'remainingPdfs' => $remainingPdfs,
            'maxPdf' => $maxPdf,
            'lockedFiles' => $lockedFiles,
            'pendingFiles' => $pendingFilesCount,
        ]);
    }
}
