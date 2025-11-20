<?php

namespace App\Repository;

use App\Entity\MessageAttachment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MessageAttachment>
 */
class MessageAttachmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageAttachment::class);
    }

    /**
     * Find attachments by message ID
     */
    public function findByMessageId(int $messageId): array
    {
        return $this->createQueryBuilder('ma')
            ->andWhere('ma.message = :messageId')
            ->setParameter('messageId', $messageId)
            ->orderBy('ma.uploadedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find attachments by message and type
     */
    public function findByMessageAndType(int $messageId, string $type): array
    {
        return $this->createQueryBuilder('ma')
            ->andWhere('ma.message = :messageId')
            ->andWhere('ma.attachmentType = :type')
            ->setParameter('messageId', $messageId)
            ->setParameter('type', $type)
            ->orderBy('ma.uploadedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}