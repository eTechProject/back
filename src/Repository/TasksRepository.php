<?php

namespace App\Repository;

use App\Entity\Tasks;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tasks>
 */
class TasksRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tasks::class);
    }

    //    /**
    //     * @return Tasks[] Returns an array of Tasks objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('t.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Tasks
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * Retourne le nombre de tâches par mois pour un client donné
     * @param int $clientId
     * @return array [ 'YYYY-MM' => count ]
     */
    public function countTasksByMonthForClient(int $clientId): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = "SELECT TO_CHAR(start_date, 'YYYY-MM') as month, COUNT(t.id) as taskCount FROM tasks t INNER JOIN service_orders o ON t.order_id = o.id WHERE o.client_id = :clientId GROUP BY month ORDER BY month ASC";
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery(['clientId' => $clientId]);
        $data = [];
        foreach ($result->fetchAllAssociative() as $row) {
            $data[$row['month']] = (int)$row['taskcount'];
        }
        return $data;
    }

    /**
     * Retourne le nombre d'incidents par mois pour un client donné
     * @param int $clientId
     * @return array [ 'YYYY-MM' => count ]
     */
    public function countIncidentsByMonthForClient(int $clientId): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = "SELECT TO_CHAR(start_date, 'YYYY-MM') as month, COUNT(t.id) as incidentCount FROM tasks t INNER JOIN service_orders o ON t.order_id = o.id WHERE o.client_id = :clientId AND t.status = 'INCIDENT' GROUP BY month ORDER BY month ASC";
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery(['clientId' => $clientId]);
        $data = [];
        foreach ($result->fetchAllAssociative() as $row) {
            $data[$row['month']] = (int)$row['incidentcount'];
        }
        return $data;
    }

    /**
     * Retourne le nombre de tâches par agent pour un client donné
     * @param int $clientId
     * @return array [ 'agentName' => count ]
     */
    public function countTasksByAgentForClient(int $clientId): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = "SELECT u.name as agentName, COUNT(t.id) as taskCount FROM tasks t INNER JOIN service_orders o ON t.order_id = o.id INNER JOIN agents a ON t.agent_id = a.id INNER JOIN users u ON a.user_id = u.id WHERE o.client_id = :clientId GROUP BY u.name ORDER BY taskCount DESC";
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery(['clientId' => $clientId]);
        $data = [];
        foreach ($result->fetchAllAssociative() as $row) {
            $data[$row['agentname']] = (int)$row['taskcount'];
        }
        return $data;
    }

    /**
     * Retourne les 3 agents ayant le plus de tâches pour un client donné
     * @param int $clientId
     * @return array [ 'agentName' => count ]
     */
    public function getTopAgentsForClient(int $clientId, int $limit = 3): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = "SELECT u.name as agentName, COUNT(t.id) as taskCount FROM tasks t INNER JOIN service_orders o ON t.order_id = o.id INNER JOIN agents a ON t.agent_id = a.id INNER JOIN users u ON a.user_id = u.id WHERE o.client_id = :clientId GROUP BY u.name ORDER BY taskCount DESC LIMIT $limit";
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery(['clientId' => $clientId]);
        $data = [];
        foreach ($result->fetchAllAssociative() as $row) {
            $data[$row['agentname']] = (int)$row['taskcount'];
        }
        return $data;
    }
}
