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

        $files = $fileRepository->findBy(['user' => $this->getUser()], ['created_at' => 'DESC']);

        return $this->render('history/index.html.twig', [
            'files' => $files,
        ]);
    }
}
