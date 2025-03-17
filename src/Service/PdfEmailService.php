<?php
// src/Service/PdfEmailService.php

namespace App\Service;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Psr\Log\LoggerInterface;

class PdfEmailService
{
    private MailerInterface $mailer;
    private string $defaultSender;
    private ?LoggerInterface $logger;

    public function __construct(
        MailerInterface $mailer,
        LoggerInterface $logger = null,
        string $defaultSender = 'noreply@pdfpro.com'
    ) {
        $this->mailer = $mailer;
        $this->logger = $logger;
        $this->defaultSender = $defaultSender;
    }

    public function sendPdfByEmail(string $emailTo, string $pdfPath, string $pdfName): bool
    {
        // Vérifier que le fichier existe et est lisible
        if (!file_exists($pdfPath)) {
            $this->log('error', "Le fichier PDF n'existe pas au chemin: {$pdfPath}");
            return false;
        }

        if (!is_readable($pdfPath)) {
            $this->log('error', "Le fichier PDF existe mais n'est pas lisible: {$pdfPath}");
            return false;
        }

        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->defaultSender, 'PDFPro'))
                ->to($emailTo)
                ->subject('Votre document PDF généré par PDFPro')
                ->htmlTemplate('emails/pdf_generated.html.twig')
                ->context([
                    'pdfName' => $pdfName,
                ])
                ->attachFromPath($pdfPath, $pdfName);

            $this->log('info', "Envoi d'email avec pièce jointe à {$emailTo}, fichier: {$pdfPath}");
            $this->mailer->send($email);
            $this->log('info', "Email envoyé avec succès à {$emailTo}");
            return true;
        } catch (\Exception $e) {
            $this->log('error', "Erreur lors de l'envoi du mail avec pièce jointe: " . $e->getMessage());
            $this->log('error', $e->getTraceAsString());
            return false;
        }
    }

    private function log(string $level, string $message): void
    {
        if ($this->logger) {
            $this->logger->$level($message);
        } else {
            error_log($message); // Fallback si pas de logger
        }
    }
}
