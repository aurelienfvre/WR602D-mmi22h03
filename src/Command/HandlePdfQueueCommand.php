<?php
// src/Command/HandlePdfQueueCommand.php

namespace App\Command;

use App\Service\GotenbergService;
use App\Service\PdfEmailService;
use App\Service\PdfQueueService;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use App\Entity\File;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'app:handle-queue',
    description: 'Process PDF generation queue',
)]
class HandlePdfQueueCommand extends Command
{
    private PdfQueueService $pdfQueueService;
    private GotenbergService $gotenbergService;
    private PdfEmailService $pdfEmailService;
    private EntityManagerInterface $entityManager;
    private string $pdfDirectory;

    public function __construct(
        PdfQueueService $pdfQueueService,
        GotenbergService $gotenbergService,
        PdfEmailService $pdfEmailService,
        EntityManagerInterface $entityManager,
        ParameterBagInterface $parameterBag
    ) {
        parent::__construct();
        $this->pdfQueueService = $pdfQueueService;
        $this->gotenbergService = $gotenbergService;
        $this->pdfEmailService = $pdfEmailService;
        $this->entityManager = $entityManager;
        
        // Résoudre le chemin correctement
        $this->pdfDirectory = $parameterBag->get('kernel.project_dir') . '/public/uploads/pdfs';
    }

    protected function configure(): void
    {
        $this->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Maximum number of PDFs to process', 10);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = (int) $input->getOption('limit');

        $io->title('Processing PDF Generation Queue');

        // Afficher le chemin du répertoire pour déboguer
        $io->note('PDF directory path: ' . $this->pdfDirectory);

        // Create PDF directory if it doesn't exist
        $filesystem = new Filesystem();
        if (!$filesystem->exists($this->pdfDirectory)) {
            $io->note('Directory does not exist, creating it now');
            $filesystem->mkdir($this->pdfDirectory, 0777);
            $io->note('Created PDF directory: ' . $this->pdfDirectory);
        } else {
            $io->note('Directory already exists');
            // Vérifier et corriger les permissions
            if (!is_writable($this->pdfDirectory)) {
                $io->warning('Directory is not writable, fixing permissions');
                chmod($this->pdfDirectory, 0777);
            }
        }

        // Get queue items to process
        $queueItems = $this->pdfQueueService->getNextBatch($limit);
        $count = count($queueItems);

        if ($count === 0) {
            $io->success('No PDFs in queue to process.');
            return Command::SUCCESS;
        }

        $io->note(sprintf('Found %d PDF(s) to process.', $count));
        $io->progressStart($count);

        $successCount = 0;
        $failCount = 0;

        foreach ($queueItems as $queueItem) {
            try {
                // Mark as processing
                $this->pdfQueueService->markAsProcessing($queueItem);

                // Generate a unique filename
                $filename = sprintf('pdf_%s_%s.pdf', 
                    $queueItem->getUser()->getId(), 
                    (new DateTimeImmutable())->format('YmdHis')
                );
                $filePath = $this->pdfDirectory . '/' . $filename;

                $io->note('Generating PDF to: ' . $filePath);

                // Use the Gotenberg service to generate the PDF and save it
                $pdfContent = $this->gotenbergService->generatePdfFromUrlToFile($queueItem->getUrl(), $filePath);
                
                // If PDF generation was successful
                if ($pdfContent) {
                    $io->note('PDF generation successful');
                    
                    // Create a File entity and save to database
                    $file = new File();
                    $file->setName($filename);
                    $file->setCreatedAt(new DateTimeImmutable());
                    $file->setUser($queueItem->getUser());
                    $this->entityManager->persist($file);

                    // If email is specified, send the PDF by email
                    if ($queueItem->getEmailTo()) {
                        $io->note('Sending email to: ' . $queueItem->getEmailTo());
                        $this->pdfEmailService->sendPdfByEmail(
                            $queueItem->getEmailTo(),
                            $filePath,
                            $filename
                        );
                    }

                    // Mark queue item as completed
                    $this->pdfQueueService->markAsCompleted($queueItem, $filePath);
                    $successCount++;
                } else {
                    $io->error('PDF generation failed');
                    // If PDF generation failed
                    $this->pdfQueueService->markAsFailed($queueItem);
                    $failCount++;
                }

                $this->entityManager->flush();
            } catch (\Exception $e) {
                // Log the error and mark as failed
                $io->error('Error processing item #' . $queueItem->getId() . ': ' . $e->getMessage());
                $io->error('Stack trace: ' . $e->getTraceAsString());
                $this->pdfQueueService->markAsFailed($queueItem);
                $failCount++;
            }

            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->success(sprintf('Processed %d PDFs: %d success, %d failures.', $count, $successCount, $failCount));

        return Command::SUCCESS;
    }
}