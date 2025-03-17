<?php

namespace App\Repository;

use App\Entity\File;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use DateTimeInterface;

/**
 * @extends ServiceEntityRepository<File>
 */
class FileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, File::class);
    }

    /**
     * Compte les fichiers PDF générés par un utilisateur à une date donnée
     * Cette méthode est utilisée pour vérifier si l'utilisateur a atteint sa limite quotidienne
     */
    public function countFileGeneratedByUserOnDate(int $userId,
    DateTimeInterface $startOfDay, DateTimeInterface $endOfDay): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.user = :userId')
            ->andWhere('f.createdAt BETWEEN :startOfDay AND :endOfDay')
            ->setParameter('userId', $userId)
            ->setParameter('startOfDay', $startOfDay)
            ->setParameter('endOfDay', $endOfDay)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte le nombre total de fichiers PDF d'un utilisateur
     */
    public function countUserFiles(int $userId): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.user = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Récupère tous les fichiers d'un utilisateur, triés par date
     */
    public function findUserFilesOrderedByDate(int $userId): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
    /**
     * Récupère les fichiers accessibles en fonction de la limite d'abonnement
     * et indique lesquels sont verrouillés
     */
    public function findUserFilesWithLockStatus(int $userId, int $maxPdfLimit): array
    {
        $allFiles = $this->findUserFilesOrderedByDate($userId);
        $result = [];
        foreach ($allFiles as $index => $file) {
            $result[] = [
                'file' => $file,
                'locked' => $index >= $maxPdfLimit
            ];
        }
        return $result;
    }
}
