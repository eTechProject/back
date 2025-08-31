<?php

namespace App\Repository;

use App\Entity\AgentLocationsRaw;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AgentLocationsRaw>
 */
class AgentLocationsRawRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgentLocationsRaw::class);
    }
    /**
     * @param int $taskId
     * @return AgentLocationsRaw|null
     */
    public function findFirstLocationForTask(int $taskId): ?AgentLocationsRaw
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.task = :taskId')
            ->setParameter('taskId', $taskId)
            ->orderBy('a.recordedAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param int $taskId
     * @return AgentLocationsRaw|null
     */
    public function findLastLocationForTask(int $taskId): ?AgentLocationsRaw
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.task = :taskId')
            ->setParameter('taskId', $taskId)
            ->orderBy('a.recordedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
