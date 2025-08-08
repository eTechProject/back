<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationTarget;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * Récupère les notifications pour un utilisateur spécifique
     */
    public function findByUser(User $user, bool $unreadOnly = false): array
    {
        $qb = $this->createQueryBuilder('n')
            ->where('n.user = :user')
            ->orWhere('n.cible = :all')
            ->setParameter('user', $user)
            ->setParameter('all', NotificationTarget::ALL)
            ->orderBy('n.createdAt', 'DESC');

        if ($unreadOnly) {
            $qb->andWhere('n.isRead = false');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère les notifications non lues pour un utilisateur
     */
    public function findUnreadByUser(User $user): array
    {
        return $this->findByUser($user, true);
    }

        /**
     * Compte le nombre de notifications non lues pour un utilisateur
     */
    public function countUnreadByUser(int $userId): int
    {
        return $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.user = :userId')
            ->andWhere('n.isRead = false')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les notifications avec pagination pour un utilisateur
     */
    public function findPaginatedByUser(int $userId, int $page, int $limit, bool $onlyUnread = false): array
    {
        $qb = $this->createQueryBuilder('n')
            ->where('n.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('n.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        if ($onlyUnread) {
            $qb->andWhere('n.isRead = false');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte le total de notifications pour un utilisateur
     */
    public function countByUser(int $userId, bool $onlyUnread = false): int
    {
        $qb = $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.user = :userId')
            ->setParameter('userId', $userId);

        if ($onlyUnread) {
            $qb->andWhere('n.isRead = false');
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Marque toutes les notifications comme lues pour un utilisateur
     */
    public function markAllAsReadForUser(int $userId): int
    {
        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.isRead', 'true')
            ->where('n.user = :userId')
            ->andWhere('n.isRead = false')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->execute();
    }

    /**
     * Récupère les notifications par cible
     */
    public function findByTarget(NotificationTarget $target, int $limit = 50): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.cible = :target')
            ->setParameter('target', $target)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les notifications non lues pour un utilisateur
     */
    public function findUnreadForUser(User $user): array
    {
        return $this->createQueryBuilder('n')
            ->where('(n.user = :user OR n.cible = :all)')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user)
            ->setParameter('all', NotificationTarget::ALL)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les notifications non lues pour un utilisateur
     */
    public function countUnreadForUser(User $user): int
    {
        return $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('(n.user = :user OR n.cible = :all)')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user)
            ->setParameter('all', NotificationTarget::ALL)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Supprime les anciennes notifications (plus de 30 jours)
     */
    public function deleteOldNotifications(): int
    {
        $thirtyDaysAgo = new \DateTime('-30 days');
        
        return $this->createQueryBuilder('n')
            ->delete()
            ->where('n.createdAt < :date')
            ->andWhere('n.isRead = true')
            ->setParameter('date', $thirtyDaysAgo)
            ->getQuery()
            ->execute();
    }
}
