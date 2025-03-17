<?php

namespace App\Controller;

use App\Repository\FileRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HistoryController extends AbstractController
{
    #[Route('/history', name: 'history')]
    public function index(FileRepository $fileRepository): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        $subscription = $user->getSubscription();
        $maxPdf = $subscription ? $subscription->getMaxPdf() : 0;

        // Récupérer tous les fichiers avec leur statut de verrouillage
        $filesWithStatus = $fileRepository->findUserFilesWithLockStatus($user->getId(), $maxPdf);

        // Compter le nombre total de fichiers
        $totalFiles = count($filesWithStatus);

        // Calculer le nombre de PDF restants
        $remainingPdfs = $maxPdf - $totalFiles;

        // Compter le nombre de fichiers verrouillés
        $lockedFiles = array_reduce($filesWithStatus, function ($count, $item) {
            return $count + ($item['locked'] ? 1 : 0);
        }, 0);

        return $this->render('history/index.html.twig', [
            'filesWithStatus' => $filesWithStatus,
            'totalFiles' => $totalFiles,
            'remainingPdfs' => $remainingPdfs,
            'maxPdf' => $maxPdf,
            'lockedFiles' => $lockedFiles,
        ]);
    }
}
