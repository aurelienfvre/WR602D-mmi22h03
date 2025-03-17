<?php

namespace App\Tests\Service;

use App\Service\PdfEmailService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Psr\Log\LoggerInterface;
use org\bovigo\vfs\vfsStream;

class PdfEmailServiceTest extends TestCase
{
    private $mockMailer;
    private $mockLogger;
    private $pdfEmailService;
    private $vfsRoot;

    protected function setUp(): void
    {
        // Créer un système de fichiers virtuel pour tester les fichiers
        $this->vfsRoot = vfsStream::setup('root');

        // Créer un fichier PDF factice
        vfsStream::newFile('test.pdf')
            ->withContent('PDF content')
            ->at($this->vfsRoot);

        // Créer des mocks pour les dépendances
        $this->mockMailer = $this->createMock(MailerInterface::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);

        // Créer le service avec les mocks
        $this->pdfEmailService = new PdfEmailService(
            $this->mockMailer,
            $this->mockLogger,
            'test@example.com'
        );
    }

    public function testSendPdfByEmail()
    {
        // Configurer le mock du mailer pour vérifier l'envoi de l'email
        $this->mockMailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($email) {
                return $email instanceof TemplatedEmail
                    && $email->getTo()[0]->getAddress() === 'recipient@example.com'
                    && $email->getSubject() === 'Votre document PDF généré par PDFPro';
            }));

        // Appeler la méthode avec le chemin du fichier virtuel
        $result = $this->pdfEmailService->sendPdfByEmail(
            'recipient@example.com',
            vfsStream::url('root/test.pdf'),
            'test.pdf'
        );

        // Vérifier que l'envoi a réussi
        $this->assertTrue($result);
    }

    public function testSendPdfByEmailWithNonExistentFile()
    {
        // Configurer le logger pour vérifier qu'une erreur est journalisée
        $this->mockLogger->expects($this->once())
            ->method('error')
            ->with($this->stringContains("Le fichier PDF n'existe pas"));

        // Le mailer ne devrait pas être appelé
        $this->mockMailer->expects($this->never())
            ->method('send');

        // Appeler la méthode avec un chemin de fichier inexistant
        $result = $this->pdfEmailService->sendPdfByEmail(
            'recipient@example.com',
            vfsStream::url('root/nonexistent.pdf'),
            'test.pdf'
        );

        // Vérifier que l'envoi a échoué
        $this->assertFalse($result);
    }

    public function testSendPdfByEmailWithMailerException()
{
    // Configurer le mailer pour lancer une exception
    $this->mockMailer->method('send')
        ->willThrowException(new \Exception('Email sending failed'));

    // Vérifier que l'erreur est journalisée (deux appels attendus)
    $this->mockLogger->expects($this->exactly(2))
        ->method('error');

    // Appeler la méthode
    $result = $this->pdfEmailService->sendPdfByEmail(
        'recipient@example.com',
        vfsStream::url('root/test.pdf'),
        'test.pdf'
    );

    // Vérifier que l'envoi a échoué
    $this->assertFalse($result);
}
}