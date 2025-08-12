<?php

namespace App\Repository;

use App\Entity\Payment;
use App\Enum\PaymentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    /**
     * @return Payment[] Returns an array of Payment objects
     */
    public function findActivePayments(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->setParameter('status', PaymentStatus::ACTIF)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Payment[] Returns an array of Payment objects
     */
    public function findExpiredPayments(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status OR (p.endDate IS NOT NULL AND p.endDate < :now)')
            ->setParameter('status', PaymentStatus::EXPIRE)
            ->setParameter('now', new \DateTime())
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByClient(int $clientId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.client = :clientId')
            ->setParameter('clientId', $clientId)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
