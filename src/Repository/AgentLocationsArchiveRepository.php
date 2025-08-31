<?php

namespace App\Repository;

use App\Entity\AgentLocationsArchive;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AgentLocationsArchive>
 */
class AgentLocationsArchiveRepository extends ServiceEntityRepository
{
    
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgentLocationsArchive::class);
    }
    /**
     * @param int $taskId
     * @return AgentLocationsArchive|null
     */
    public function findByTaskId(int $taskId): ?AgentLocationsArchive
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.task = :taskId')
            ->setParameter('taskId', $taskId)
            ->getQuery()
            ->getOneOrNullResult();
    }


}
