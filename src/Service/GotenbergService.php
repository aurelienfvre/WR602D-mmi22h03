<?php

namespace App\Service;

use App\Entity\File;
use App\Entity\User;
use App\Repository\FileRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Security\Core\User\UserInterface;

class GotenbergService
{
    private HttpClientInterface $client;
    private string $gotenbergUrl;
    private FileRepository $fileRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(
        HttpClientInterface $client,
        string $gotenbergUrl,
        FileRepository $fileRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->client = $client;
        $this->gotenbergUrl = $gotenbergUrl;
        $this->fileRepository = $fileRepository;
        $this->entityManager = $entityManager;
    }

    /**
     * Vérifie si l'utilisateur peut générer plus de PDF aujourd'hui
     */
    public function canUserGeneratePdf(UserInterface $user): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        $subscription = $user->getSubscription();
        if (!$subscription) {
            return false;
        }

        $maxPdf = $subscription->getMaxPdf();
        
        // Calculer le début et la fin de la journée
        $now = new DateTimeImmutable();
        $startOfDay = $now->setTime(0, 0, 0);
        $endOfDay = $now->setTime(23, 59, 59);
        
        // Compter les PDF générés aujourd'hui
        $pdfCount = $this->fileRepository->countFileGeneratedByUserOnDate(
            $user->getId(),
            $startOfDay,
            $endOfDay
        );
        
        // Vérifier si l'utilisateur peut générer plus de PDF
        return $pdfCount < $maxPdf;
    }

    /**
     * Enregistre un nouveau fichier PDF pour l'utilisateur
     */
    private function recordPdfFile(string $filename, UserInterface $user): File
    {
        $file = new File();
        $file->setName($filename);
        $file->setCreatedAt(new DateTimeImmutable());
        $file->setUser($user);
        
        $this->entityManager->persist($file);
        $this->entityManager->flush();
        
        return $file;
    }

    /**
     * Génère un PDF à partir d'un fichier téléchargé
     *
     * @param string $filePath Chemin du fichier source
     * @param string $outputPath Chemin où enregistrer le PDF
     * @param string $originalFilename Nom original du fichier
     * @param UserInterface|null $user Utilisateur
     * @return bool Succès ou échec
     */
    public function generatePdfFromFile(
        string $filePath,
        string $outputPath,
        string $originalFilename,
        ?UserInterface $user = null
    ): bool {
        // Vérifier si l'utilisateur peut générer plus de PDF
        if ($user && !$this->canUserGeneratePdf($user)) {
            error_log("Limite quotidienne de génération de PDF atteinte pour l'utilisateur " . $user->getId());
            return false;
        }

        try {
            // Déterminer le type MIME
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($filePath);
            
            error_log("Génération de PDF à partir du fichier: $filePath (type: $mimeType)");
            error_log("Chemin de sortie: $outputPath");
            
            // Obtenir l'extension du fichier
            $extension = pathinfo($filePath, PATHINFO_EXTENSION);
            
            // Traitement différent selon le type de fichier
            if (strpos($mimeType, 'text/html') === 0) {
                // Si c'est un fichier HTML, utiliser la conversion HTML
                // Pour HTML, Gotenberg s'attend à recevoir un fichier nommé "index.html"
                $tempDir = sys_get_temp_dir() . '/' . uniqid('html_convert_');
                mkdir($tempDir, 0777, true);
                
                // Copier le fichier HTML en tant que index.html
                $tempFile = $tempDir . '/index.html';
                copy($filePath, $tempFile);
                
                $response = $this->client->request('POST', $this->gotenbergUrl . '/forms/chromium/convert/html', [
                    'headers' => [
                        'Content-Type' => 'multipart/form-data',
                    ],
                    'body' => [
                        'files' => [
                            'index.html' => fopen($tempFile, 'r'),
                        ],
                    ],
                ]);
                
                // Nettoyer le répertoire temporaire
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
                if (file_exists($tempDir)) {
                    rmdir($tempDir);
                }
            } elseif (strpos($mimeType, 'image/') === 0) {
                // Pour les images
                $imageName = basename($filePath);
                
                // Créer un fichier HTML qui inclut l'image
                $tempDir = sys_get_temp_dir() . '/' . uniqid('img_convert_');
                mkdir($tempDir, 0777, true);
                
                // Copier l'image dans le répertoire temporaire
                $tempImagePath = $tempDir . '/' . $imageName;
                copy($filePath, $tempImagePath);
                
                // Créer un fichier HTML qui inclut l'image
                $htmlContent = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <title>Image Conversion</title>
                    <style>
                        body {
                            margin: 0;
                            padding: 0;
                            display: flex;
                            justify-content: center;
                            align-items: center;
                            min-height: 100vh;
                        }
                        img {
                            max-width: 100%;
                            height: auto;
                        }
                    </style>
                </head>
                <body>
                    <img src='$imageName' alt='Image'>
                </body>
                </html>";
                
                $tempHtmlPath = $tempDir . '/index.html';
                file_put_contents($tempHtmlPath, $htmlContent);
                
                $response = $this->client->request('POST', $this->gotenbergUrl . '/forms/chromium/convert/html', [
                    'headers' => [
                        'Content-Type' => 'multipart/form-data',
                    ],
                    'body' => [
                        'files' => [
                            'index.html' => fopen($tempHtmlPath, 'r'),
                            $imageName => fopen($tempImagePath, 'r'),
                        ],
                    ],
                ]);
                
                // Nettoyer le répertoire temporaire
                if (file_exists($tempHtmlPath)) {
                    unlink($tempHtmlPath);
                }
                if (file_exists($tempImagePath)) {
                    unlink($tempImagePath);
                }
                if (file_exists($tempDir)) {
                    rmdir($tempDir);
                }
            } elseif ($mimeType === 'application/pdf') {
                // Si c'est déjà un PDF, le copier simplement
                if (copy($filePath, $outputPath)) {
                    return true;
                }
                return false;
            } elseif (in_array($mimeType, [
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ])) {
                // Pour les documents Word
                $response = $this->client->request('POST', $this->gotenbergUrl . '/forms/libreoffice/convert', [
                    'headers' => [
                        'Content-Type' => 'multipart/form-data',
                    ],
                    'body' => [
                        'files' => [
                            'document.' . $extension => fopen($filePath, 'r'),
                        ],
                    ],
                ]);
            } else {
                // Fallback pour les autres types: essayer de les traiter comme du texte
                $tempDir = sys_get_temp_dir() . '/' . uniqid('text_convert_');
                mkdir($tempDir, 0777, true);
                
                // Copier le fichier dans le répertoire temporaire
                $tempFile = $tempDir . '/document.md';
                copy($filePath, $tempFile);
                
                $response = $this->client->request('POST', $this->gotenbergUrl . '/forms/chromium/convert/markdown', [
                    'headers' => [
                        'Content-Type' => 'multipart/form-data',
                    ],
                    'body' => [
                        'files' => [
                            'document.md' => fopen($tempFile, 'r'),
                        ],
                    ],
                ]);
                
                // Nettoyer le répertoire temporaire
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
                if (file_exists($tempDir)) {
                    rmdir($tempDir);
                }
            }
            
            // Traiter la réponse
            if (isset($response)) {
                if ($response->getStatusCode() !== 200) {
                    error_log("Erreur Gotenberg: " . $response->getStatusCode());
                    error_log("Message d'erreur: " . $response->getContent(false));
                    return false;
                }
                
                // Sauvegarder le fichier
                $pdfContent = $response->getContent();
                $bytesWritten = file_put_contents($outputPath, $pdfContent);
                
                if ($bytesWritten === false) {
                    error_log("Échec de l'écriture du fichier: $outputPath");
                    return false;
                }
                
                error_log("PDF généré avec succès: $outputPath ($bytesWritten octets)");
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            error_log("Exception lors de la génération du PDF: " . $e->getMessage());
            error_log($e->getTraceAsString());
            return false;
        }
    }

    public function generatePdfFromHtml(string $htmlContent, ?UserInterface $user = null): Response
    {
        // Vérifier si l'utilisateur peut générer plus de PDF
        if ($user && !$this->canUserGeneratePdf($user)) {
            return new Response("Limite quotidienne de génération de PDF atteinte. "
                . "Veuillez réessayer demain ou mettre à jour votre abonnement.", 403);
        }

        // Création d'un répertoire temporaire
        $tmpDir = sys_get_temp_dir() . '/' . uniqid('html_');
        mkdir($tmpDir, 0777, true);
        
        // Création d'un fichier temporaire nommé `index.html`
        $tmpFile = $tmpDir . '/index.html';
        file_put_contents($tmpFile, $htmlContent);

        try {
            $response = $this->client->request('POST', $this->gotenbergUrl . '/forms/chromium/convert/html', [
                'headers' => [
                    'Content-Type' => 'multipart/form-data',
                ],
                'body' => [
                    'files' => [
                        'index.html' => fopen($tmpFile, 'r'),
                    ],
                ],
            ]);

            // Supprimer le fichier temporaire après l'envoi
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
            if (file_exists($tmpDir)) {
                rmdir($tmpDir);
            }

            if ($response->getStatusCode() !== 200) {
                return new Response("Erreur lors de la génération du PDF : "
                    . $response->getContent(false), $response->getStatusCode());
            }

            // Enregistrer le fichier si un utilisateur est fourni
            if ($user) {
                $filename = 'pdf_' . $user->getId() . '_' . (new DateTimeImmutable())->format('YmdHis') . '.pdf';
                $filePath = $this->getUploadDirectory() . '/' . $filename;
                
                file_put_contents($filePath, $response->getContent(false));
                $this->recordPdfFile($filename, $user);
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

    public function generatePdfFromUrl(string $url, ?UserInterface $user = null): Response
    {
        // Vérifier si l'utilisateur peut générer plus de PDF
        if ($user && !$this->canUserGeneratePdf($user)) {
            return new Response("Limite quotidienne de génération de PDF atteinte. "
                . "Veuillez réessayer demain ou mettre à jour votre abonnement.", 403);
        }

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
                return new Response("Erreur lors de la génération du PDF : "
                    . $response->getContent(false), $response->getStatusCode());
            }

            // Enregistrer le fichier si un utilisateur est fourni
            if ($user) {
                $filename = 'pdf_' . $user->getId() . '_' . (new DateTimeImmutable())->format('YmdHis') . '.pdf';
                $filePath = $this->getUploadDirectory() . '/' . $filename;
                
                file_put_contents($filePath, $response->getContent(false));
                $this->recordPdfFile($filename, $user);
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

    public function generatePdfFromUrlToFile(string $url, string $filePath, ?UserInterface $user = null): bool
    {
        // Vérifier si l'utilisateur peut générer plus de PDF
        if ($user && !$this->canUserGeneratePdf($user)) {
            error_log("Limite quotidienne de génération de PDF atteinte pour l'utilisateur " . $user->getId());
            return false;
        }

        try {
            $response = $this->client->request('POST', $this->gotenbergUrl . '/forms/chromium/convert/url', [
                'headers' => [
                    'Content-Type' => 'multipart/form-data',
                ],
                'body' => [
                    'url' => $url,
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                error_log("Erreur lors de la génération du PDF depuis URL: " . $response->getStatusCode());
                return false;
            }

            // Sauvegarder le contenu dans un fichier
            $bytesWritten = file_put_contents($filePath, $response->getContent());
            
            if ($bytesWritten === false) {
                error_log("Échec de l'écriture du fichier PDF: $filePath");
                return false;
            }
            
            // Enregistrer le fichier si un utilisateur est fourni
            if ($user) {
                $filename = basename($filePath);
                $this->recordPdfFile($filename, $user);
            }
            
            return true;
        } catch (\Exception $e) {
            // Log l'erreur
            error_log("Erreur lors de la génération du PDF depuis URL : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère le chemin du répertoire d'upload des PDF
     */
    private function getUploadDirectory(): string
    {
        $dir = dirname(__DIR__, 2) . '/public/uploads/pdfs';
        
        // Créer le répertoire s'il n'existe pas
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
            error_log("Créé le répertoire PDF : " . $dir);
        }
        
        // Vérifier et corriger les permissions
        if (!is_writable($dir)) {
            chmod($dir, 0777);
            error_log("Permissions du répertoire PDF mises à jour");
        }
        
        return $dir;
    }
}
