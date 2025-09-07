<?php

namespace App\Repository;

use App\Entity\Messages;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\DTO\Dashboard\Request\DashboardFiltersDTO;
use Doctrine\ORM\QueryBuilder;


/**
 * @extends ServiceEntityRepository<Messages>
 */
class MessagesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Messages::class);
    }

    /**
     * Returns messages for a specific agent and order
     * @param int $userId
     * @param int $orderId
     * @param DashboardFiltersDTO|null $filters
     * @return Messages[]
     */
    public function findMessagesForAgentAndOrder(int $userId, int $orderId, ?DashboardFiltersDTO $filters = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->where('(m.sender = :userId OR m.receiver = :userId)')
            ->andWhere('m.order = :orderId')
            ->setParameter('userId', $userId)
            ->setParameter('orderId', $orderId);

        // Apply date filters if provided
        if ($filters !== null) {
            $this->applyDateFilters($qb, $filters);
        }

        return $qb
            ->orderBy('m.sentAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Apply date filters to the query builder based on DashboardFiltersDTO
     * @param QueryBuilder $qb
     * @param DashboardFiltersDTO $filters
     */
    private function applyDateFilters($qb, DashboardFiltersDTO $filters): void
    {
        $now = new \DateTime();
        
        // Handle predefined date choices
        if ($filters->choice !== null) {
            switch ($filters->choice) {
                case 'today':
                    $startOfDay = clone $now;
                    $startOfDay->setTime(0, 0, 0);
                    $endOfDay = clone $now;
                    $endOfDay->setTime(23, 59, 59);
                    
                    $qb->andWhere('m.sentAt BETWEEN :startDate AND :endDate')
                    ->setParameter('startDate', $startOfDay)
                    ->setParameter('endDate', $endOfDay);
                    break;
                    
                case 'last7days':
                    $startDate = clone $now;
                    $startDate->modify('-7 days')->setTime(0, 0, 0);
                    
                    $qb->andWhere('m.sentAt >= :startDate')
                    ->setParameter('startDate', $startDate);
                    break;
                    
                case 'thisMonth':
                    $startOfMonth = clone $now;
                    $startOfMonth->modify('first day of this month')->setTime(0, 0, 0);
                    
                    $qb->andWhere('m.sentAt >= :startDate')
                    ->setParameter('startDate', $startOfMonth);
                    break;
                    
                case 'last30days':
                    $startDate = clone $now;
                    $startDate->modify('-30 days')->setTime(0, 0, 0);
                    
                    $qb->andWhere('m.sentAt >= :startDate')
                    ->setParameter('startDate', $startDate);
                    break;
                    
                case 'thisYear':
                    $startOfYear = clone $now;
                    $startOfYear->setDate((int)$now->format('Y'), 1, 1)->setTime(0, 0, 0);
                    
                    $qb->andWhere('m.sentAt >= :startDate')
                    ->setParameter('startDate', $startOfYear);
                    break;
            }
        }
        // Handle custom date range
        elseif ($filters->dateStart !== null || $filters->dateEnd !== null) {
            if ($filters->dateStart !== null) {
                $startDate = new \DateTime($filters->dateStart);
                $startDate->setTime(0, 0, 0);
                $qb->andWhere('m.sentAt >= :startDate')
                ->setParameter('startDate', $startDate);
            }
            
            if ($filters->dateEnd !== null) {
                $endDate = new \DateTime($filters->dateEnd);
                $endDate->setTime(23, 59, 59);
                $qb->andWhere('m.sentAt <= :endDate')
                ->setParameter('endDate', $endDate);
            }
        }
    }

    /**
     * Find messages for a specific order within a date range
     * @param int $orderId
     * @param \DateTimeInterface $startDate
     * @param \DateTimeInterface|null $endDate
     * @return Messages[]
     */
    public function findMessagesByOrderAndDateRange(int $orderId, \DateTimeInterface $startDate, ?\DateTimeInterface $endDate = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->where('m.order = :orderId')
            ->andWhere('m.sentAt >= :startDate')
            ->setParameter('orderId', $orderId)
            ->setParameter('startDate', $startDate);

        if ($endDate !== null) {
            $qb->andWhere('m.sentAt <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        return $qb
            ->orderBy('m.sentAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
