<?php

namespace App\Repository;

use App\Entity\Agents;
use App\Enum\Status;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Agents>
 */
class AgentsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Agents::class);
    }

    /**
     * Find all agents who are available for new tasks.
     * 
     * An agent is considered available if:
     * - They have no tasks assigned, OR
     * - All their tasks have status 'completed' or 'cancelled'
     * 
     * This excludes agents who have tasks with status 'pending' or 'in_progress'
     * 
     * @return Agents[] Returns an array of Agents objects
     */
    public function findAvailableAgents(): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('App\Entity\Tasks', 't', 'WITH', 't.agent = a.id AND t.status NOT IN (:availableStatuses)')
            ->where('t.id IS NULL')
            ->setParameter('availableStatuses', [Status::COMPLETED->value, Status::CANCELLED->value])
            ->orderBy('a.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return Agents[] Returns an array of Agents objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('a.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Agents
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
