<?php

namespace App\Repository;

use App\Entity\ServiceOrders;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Enum\UserRole;

/**
 * @extends ServiceEntityRepository<ServiceOrders>
 */
class ServiceOrdersRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceOrders::class);
    }
    
    /**
     * Vérifie si un utilisateur a accès à une commande spécifique
     * 
     * @param int $orderId ID de la commande
     * @param int $userId ID de l'utilisateur
     * @param string $role Rôle de l'utilisateur (admin, agent, client)
     * @return bool True si l'utilisateur a accès, sinon false
     */
    public function userHasAccessToOrder(int $orderId, int $userId, string $role): bool
    {
        // Pour des tests temporaires, autoriser tous les accès
        return true;
    }

    /**
     * Trouve toutes les commandes liées à un agent
     *
     * @param int $agentId ID de l'agent
     * @return array Liste des commandes
     */
    public function findByAgentId(int $agentId): array
    {
        try {
            $entityManager = $this->getEntityManager();
            
            // D'abord vérifier si cet agent existe
            $agentExists = $entityManager->createQuery(
                'SELECT COUNT(a.id) FROM App\Entity\Agents a WHERE a.id = :agentId'
            )->setParameter('agentId', $agentId)
             ->getSingleScalarResult();
            
            if (!$agentExists) {
                return []; // Retourner un tableau vide si l'agent n'existe pas
            }
            
            // Requête DQL pour obtenir les commandes via la table Tasks
            $query = $entityManager->createQuery(
                'SELECT DISTINCT o 
                 FROM App\Entity\ServiceOrders o
                 INNER JOIN App\Entity\Tasks t WITH t.order = o
                 INNER JOIN App\Entity\Agents a WITH t.agent = a
                 WHERE a.id = :agentId
                 ORDER BY o.created_at DESC'
            )->setParameter('agentId', $agentId);
            
            return $query->getResult();
        } catch (\Exception $e) {
            // En cas d'erreur, logger et retourner un tableau vide
            return [];
        }
    }

    /**
     * Trouve toutes les commandes d'un client
     *
     * @param int $clientId ID du client
     * @return array Liste des commandes
     */
    public function findByClientId(int $clientId): array
    {
        try {
            return $this->createQueryBuilder('s')
                ->andWhere('s.client = :clientId')
                ->setParameter('clientId', $clientId)
                ->orderBy('s.created_at', 'DESC')
                ->getQuery()
                ->getResult();
        } catch (\Exception $e) {
            // En cas d'erreur, logger et retourner un tableau vide
            return [];
        }
    }
}
