<?php

// src/Controller/FileController.php

namespace App\Controller;

use App\Entity\File;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

class FileController extends AbstractController
{
    #[Route('/file/download/{id}', name: 'file_download')]
    public function download(File $file, EntityManagerInterface $entityManager): Response
    {
        // Vérifier si l'utilisateur est autorisé à télécharger ce fichier
        if ($file->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à télécharger ce fichier.');
        }

        // Vérifier si l'utilisateur a accès à ce fichier selon son abonnement
        $user = $this->getUser();
        $subscription = $user->getSubscription();
        $maxPdf = $subscription ? $subscription->getMaxPdf() : 0;

        // Récupérer la position du fichier dans la liste des fichiers de l'utilisateur
        $userFiles = $entityManager->getRepository(File::class)
            ->findBy(['user' => $user], ['createdAt' => 'DESC']);

        $position = array_search($file, $userFiles);

        // Si le fichier est verrouillé (en-dehors de la limite d'abonnement)
        if ($position !== false && $position >= $maxPdf) {
            $this->addFlash('error', 'Ce fichier est verrouillé dans votre abonnement actuel. 
            Veuillez mettre à niveau pour y accéder.');
            return $this->redirectToRoute('subscription_change');
        }

        $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/pdfs/' . $file->getName();

        // Vérifier si le fichier existe
        if (!file_exists($filePath)) {
            $this->addFlash('error', 'Le fichier demandé n\'existe pas.');
            return $this->redirectToRoute('history');
        }

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $file->getName()
        );

        return $response;
    }

    #[Route('/file/delete/{id}', name: 'file_delete')]
    public function delete(File $file, EntityManagerInterface $entityManager): Response
    {
        // Vérifier si l'utilisateur est autorisé à supprimer ce fichier
        if ($file->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à supprimer ce fichier.');
        }

        // Supprimer le fichier physique
        $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/pdfs/' . $file->getName();
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Supprimer l'entrée de la base de données
        $entityManager->remove($file);
        $entityManager->flush();

        $this->addFlash('success', 'Le fichier a été supprimé avec succès.');

        return $this->redirectToRoute('history');
    }
}
