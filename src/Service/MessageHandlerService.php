<?php

namespace App\Service;

use App\DTO\Message\MessageDTO;
use App\DTO\Message\MessageAttachmentDTO;
use App\Entity\Messages;
use App\Entity\MessageAttachment;
use App\Entity\User;
use App\Service\CryptService;
use App\Service\MessageService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;

class MessageHandlerService
{
    public function __construct(
        private MessageService $messageService,
        private CryptService $cryptService,
        private LoggerInterface $logger,
        private EntityManagerInterface $em
    ) {
    }

    public function validateAccess(?User $user, int $orderId): ?JsonResponse
    {
        if (!$user) {
            return new JsonResponse([
                'error' => 'Utilisateur non authentifié',
                'status' => 'error'
            ], 401);
        }

        if (!$this->messageService->userHasAccessToOrder($orderId, $user->getId(), $user->getRole()->value)) {
            $this->logger->warning('Tentative d\'accès non autorisé', [
                'user_id' => $user->getId(),
                'role' => $user->getRole()->value,
                'order_id' => $orderId
            ]);

            return new JsonResponse([
                'error' => 'Vous n\'êtes pas autorisé à accéder à cette commande',
                'status' => 'error'
            ], 403);
        }

        return null;
    }

    public function createMessageResponse(array $data, array $files = []): JsonResponse
    {
        try {
            $message = $this->messageService->createMessage($data, $files);
            $messageDTO = $this->createMessageDTO($message);

            return new JsonResponse([
                'data' => $messageDTO,
                'status' => 'success',
                'message' => 'Message envoyé avec succès'
            ]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    public function getMessagesResponse(int $orderId, array $criteria): JsonResponse
    {
        try {
            $result = $this->messageService->getMessagesForOrder($orderId, $criteria);
            $messages = $result['messages'] ?? [];
            $totalItems = $result['total'] ?? count($messages);
            $totalPages = ceil($totalItems / $criteria['limit']);

            // Les messages sont déjà transformés par MessageService.transformMessagesToArray()
            // Pas besoin de les re-transformer

            // Réordonner les messages par date croissante
            usort($messages, function($a, $b) {
                return strtotime($a['sent_at']) <=> strtotime($b['sent_at']);
            });

            $messageDTOs = array_map(function($message) {
                return new MessageDTO(
                    encryptedId: $message['id'],
                    order_id: $message['order_id'],
                    sender_id: $message['sender_id'],
                    receiver_id: $message['receiver_id'],
                    content: $message['content'],
                    sent_at: $message['sent_at']
                );
            }, $messages);

            return new JsonResponse([
                'data' => $messageDTOs,
                'total' => $totalItems,
                'page' => $criteria['page'],
                'pages' => $totalPages,
                'status' => 'success'
            ]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 404);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Une erreur inattendue s\'est produite',
                'status' => 'error'
            ], 500);
        }
    }

    public function buildCriteria(Request $request): array
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));

        $allowedSortFields = ['sent_at', 'content', 'sender_id', 'receiver_id'];
        $sortBy = in_array($request->query->get('sort'), $allowedSortFields)
            ? $request->query->get('sort')
            : 'sent_at';

        $sortOrder = strtoupper($request->query->get('order', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $criteria = [
            'page' => $page,
            'limit' => $limit,
            'sort' => $sortBy,
            'order' => $sortOrder
        ];

        if ($senderId = $request->query->get('sender_id')) {
            $criteria['sender_id'] = $senderId;
        }

        if ($fromDate = $request->query->get('from_date')) {
            try {
                new \DateTime($fromDate);
                $criteria['from_date'] = $fromDate;
            } catch (\Exception $e) {
                // Ignorer la date invalide
            }
        }

        return $criteria;
    }

    private function createMessageDTO($message): MessageDTO
    {
        $attachments = [];
        foreach ($message->getAttachments() as $attachment) {
            $attachments[] = $this->createAttachmentDTO($attachment);
        }

        return new MessageDTO(
            encryptedId: $this->cryptService->encryptId((string) $message->getId(), \App\Enum\EntityType::MESSAGE->value),
            order_id: $this->cryptService->encryptId((string) $message->getOrder()->getId(), \App\Enum\EntityType::SERVICE_ORDER->value),
            sender_id: $this->cryptService->encryptId((string) $message->getSender()->getId(), \App\Enum\EntityType::USER->value),
            receiver_id: $this->cryptService->encryptId((string) $message->getReceiver()->getId(), \App\Enum\EntityType::USER->value),
            content: $message->getContent(),
            sent_at: $message->getSentAt()->format('Y-m-d H:i:s'),
            attachments: $attachments
        );
    }

    private function createAttachmentDTO(MessageAttachment $attachment): MessageAttachmentDTO
    {
        return new MessageAttachmentDTO(
            id: $this->cryptService->encryptId((string) $attachment->getId(), \App\Enum\EntityType::MESSAGE->value),
            filename: $attachment->getFilename(),
            originalFilename: $attachment->getOriginalFilename(),
            mimeType: $attachment->getMimeType(),
            attachmentType: $attachment->getAttachmentType(),
            fileSize: $attachment->getFileSize(),
            formattedFileSize: $attachment->getFormattedFileSize(),
            uploadedAt: $attachment->getUploadedAt()->format('Y-m-d H:i:s'),
            downloadUrl: '/messages/attachments/' . $this->cryptService->encryptId((string) $attachment->getId(), \App\Enum\EntityType::MESSAGE->value)
        );
    }

    private function createMessageDTOFromArray(array $message): MessageDTO
    {
        return new MessageDTO(
            encryptedId: $this->cryptService->encryptId((string) $message['id'], \App\Enum\EntityType::MESSAGE->value),
            order_id: $this->cryptService->encryptId((string) $message['order']->getId(), \App\Enum\EntityType::SERVICE_ORDER->value),
            sender_id: $this->cryptService->encryptId((string) $message['sender']->getId(), \App\Enum\EntityType::USER->value),
            receiver_id: $this->cryptService->encryptId((string) $message['receiver']->getId(), \App\Enum\EntityType::USER->value),
            content: $message['content'],
            sent_at: $message['sent_at']->format('Y-m-d H:i:s')
        );
    }

    /**
     * Récupère tous les messages entre deux utilisateurs, dans les deux sens, paginés.
     */
    public function getConversationMessages($senderId, $receiverId, $page = 1, $limit = 20): array
    {
        $repo = $this->em->getRepository(Messages::class);

        // Requête principale sans pagination
        $qb = $repo->createQueryBuilder('m');
        $qb->where(
            $qb->expr()->orX(
                $qb->expr()->andX(
                    $qb->expr()->eq('m.sender', ':senderId'),
                    $qb->expr()->eq('m.receiver', ':receiverId')
                ),
                $qb->expr()->andX(
                    $qb->expr()->eq('m.sender', ':receiverId'),
                    $qb->expr()->eq('m.receiver', ':senderId')
                )
            )
        )
            ->setParameter('senderId', $senderId)
            ->setParameter('receiverId', $receiverId)
            ->orderBy('m.sentAt', 'ASC');

        $messages = $qb->getQuery()->getResult();

        // Formatage : CRYPTER LES IDs ICI
        $messageData = array_map(function (Messages $m) {
            $attachments = [];
            foreach ($m->getAttachments() as $attachment) {
                $attachments[] = [
                    'id' => $this->cryptService->encryptId((string) $attachment->getId(), \App\Enum\EntityType::MESSAGE->value),
                    'filename' => $attachment->getFilename(),
                    'originalFilename' => $attachment->getOriginalFilename(),
                    'mimeType' => $attachment->getMimeType(),
                    'attachmentType' => $attachment->getAttachmentType(),
                    'fileSize' => $attachment->getFileSize(),
                    'formattedFileSize' => $attachment->getFormattedFileSize(),
                    'uploadedAt' => $attachment->getUploadedAt()->format('Y-m-d H:i:s'),
                    'downloadUrl' => '/messages/attachments/' . $this->cryptService->encryptId((string) $attachment->getId(), \App\Enum\EntityType::MESSAGE->value)
                ];
            }

            return [
                'id' => $this->cryptService->encryptId((string) $m->getId(), \App\Enum\EntityType::MESSAGE->value),
                'order_id' => $this->cryptService->encryptId((string) ($m->getOrder()?->getId()), \App\Enum\EntityType::SERVICE_ORDER->value),
                'sender_id' => $this->cryptService->encryptId((string) ($m->getSender()?->getId()), \App\Enum\EntityType::USER->value),
                'receiver_id' => $this->cryptService->encryptId((string) ($m->getReceiver()?->getId()), \App\Enum\EntityType::USER->value),
                'content' => $m->getContent(),
                'sent_at' => $m->getSentAt()?->format('Y-m-d H:i:s'),
                'attachments' => $attachments,
            ];
        }, $messages);

        // Trier en décroissant (plus récent en premier)
        usort($messageData, function($a, $b) {
            return strtotime($b['sent_at']) <=> strtotime($a['sent_at']);
        });

        // Pagination
        $total = count($messageData);
        $offset = ($page - 1) * $limit;
        $pagedMessages = array_slice($messageData, $offset, $limit);

        // Inverser la page pour que le plus récent soit à la fin
        $pagedMessages = array_reverse($pagedMessages);

        return [
            'messages' => $pagedMessages,
            'total' => $total
        ];
    }
}