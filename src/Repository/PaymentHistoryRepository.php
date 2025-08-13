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

    /**
     * Retourne le total des paiements par mois pour un client donné
     * @param int $clientId
     * @return array [ 'YYYY-MM' => total ]
     */
    public function sumPaymentsByMonthForClient(int $clientId): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = "SELECT TO_CHAR(ph.date, 'YYYY-MM') as month, SUM(ph.amount::float) as total FROM payment_history ph INNER JOIN payment p ON ph.payment_id = p.id WHERE p.client_id = :clientId GROUP BY month ORDER BY month ASC";
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery(['clientId' => $clientId]);
        $data = [];
        foreach ($result->fetchAllAssociative() as $row) {
            $data[$row['month']] = (float)$row['total'];
        }
        return $data;
    }
}
