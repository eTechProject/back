<?php

namespace App\Repository;

use App\Entity\PaymentHistory;
use App\Enum\PaymentHistoryStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PaymentHistory>
 */
class PaymentHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentHistory::class);
    }

    /**
     * @return PaymentHistory[] Returns an array of PaymentHistory objects
     */
    public function findByPayment(int $paymentId): array
    {
        return $this->createQueryBuilder('ph')
            ->andWhere('ph.payment = :paymentId')
            ->setParameter('paymentId', $paymentId)
            ->orderBy('ph.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return PaymentHistory[] Returns an array of PaymentHistory objects
     */
    public function findSuccessfulPayments(): array
    {
        return $this->createQueryBuilder('ph')
            ->andWhere('ph.status = :status')
            ->setParameter('status', PaymentHistoryStatus::SUCCESS)
            ->orderBy('ph.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return PaymentHistory[] Returns an array of PaymentHistory objects
     */
    public function findFailedPayments(): array
    {
        return $this->createQueryBuilder('ph')
            ->andWhere('ph.status = :status')
            ->setParameter('status', PaymentHistoryStatus::FAILED)
            ->orderBy('ph.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return PaymentHistory[] Returns an array of PaymentHistory objects
     */
    public function findByProvider(string $provider): array
    {
        return $this->createQueryBuilder('ph')
            ->andWhere('ph.provider = :provider')
            ->setParameter('provider', $provider)
            ->orderBy('ph.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getTotalAmountByStatus(string $status): float
    {
        $result = $this->createQueryBuilder('ph')
            ->select('SUM(ph.amount) as total')
            ->andWhere('ph.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }
}
