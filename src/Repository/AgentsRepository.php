<?php

namespace App\Repository;

use App\Entity\Agents;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;


class AgentsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Agents::class);
    }
    
    public function searchAgents(?string $name, ?string $status): array
  {
    $qb = $this->createQueryBuilder('a')
               ->join('a.user', 'u');

    if ($name) {
        $qb->andWhere('LOWER(u.name) LIKE :name')
           ->setParameter('name', '%' . strtolower($name) . '%');
    }

    if ($status) {
        $qb->andWhere('a.status = :status')
           ->setParameter('status', $status);
    }

    return $qb->getQuery()->getResult();
    }

}