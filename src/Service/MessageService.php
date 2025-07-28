<?php

namespace App\Service;

use App\Entity\Messages;
use App\Repository\ServiceOrdersRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class MessageService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ServiceOrdersRepository $ordersRepo,
        private UserRepository $userRepo,
        private HubInterface $mercureHub,
        private LoggerInterface $logger,
    ) {}

    /**
     * Crée un message, le persiste et le publie via Mercure.
     *
     * @throws \InvalidArgumentException si validation échoue
     * @throws \RuntimeException si publication Mercure échoue
     */
    public function createMessage(array $data): Messages
    {
        // Validation des données d'entrée
        $orderId = $data['order_id'] ?? null;
        $senderId = $data['sender_id'] ?? null;
        $receiverId = $data['receiver_id'] ?? null;
        $content = trim($data['content'] ?? '');

        if (!$orderId || !$senderId || !$receiverId || empty($content)) {
            $this->logger->error('Paramètres manquants ou invalides', $data);
            throw new \InvalidArgumentException('Paramètres manquants ou invalides');
        }

        // Récupération des entités
        $order = $this->ordersRepo->find($orderId);
        $sender = $this->userRepo->find($senderId);
        $receiver = $this->userRepo->find($receiverId);

        if (!$order || !$sender || !$receiver) {
            $this->logger->error('Entités non trouvées', [
                'order_exists' => (bool)$order,
                'sender_exists' => (bool)$sender,
                'receiver_exists' => (bool)$receiver
            ]);
            throw new \InvalidArgumentException('Commande ou utilisateurs non trouvés');
        }

        // Validation métier
        if ($order->getClient()->getId() !== $sender->getId() && $order->getClient()->getId() !== $receiver->getId()) {
            $this->logger->error('Utilisateurs non liés à la commande', [
                'client_id' => $order->getClient()->getId(),
                'sender_id' => $sender->getId(),
                'receiver_id' => $receiver->getId()
            ]);
            throw new \InvalidArgumentException('Sender ou receiver non liés à cette commande');
        }

        // Création et persistance du message
        $message = new Messages();
        $message->setOrder($order);
        $message->setSender($sender);
        $message->setReceiver($receiver);
        $message->setContent($content);
        $message->setSentAt(new \DateTimeImmutable());

        $this->em->persist($message);
        $this->em->flush();

        // Publication Mercure
        $this->publishMercureUpdate($message, $order, $sender, $receiver, $content);

        return $message;
    }

    /**
     * Publie les mises à jour Mercure pour un nouveau message
     */
    private function publishMercureUpdate(
        Messages $message,
        $order,
        $sender,
        $receiver,
        string $content
    ): void {
        try {
               if (!$this->mercureHub->getUrl()) {
            $this->logger->warning('Mercure Hub non configuré');
            return;
        }
            $payload = [
                'id' => $message->getId(),
                'order_id' => $order->getId(),
                'sender_id' => $sender->getId(),
                'receiver_id' => $receiver->getId(),
                'content' => $content,
                'sent_at' => $message->getSentAt()->format(\DateTimeInterface::ATOM),
            ];

            $this->logger->debug('Préparation de la publication Mercure', ['payload' => $payload]);

            $topics = [
                sprintf('/agents/%d', $sender->getId()),
                sprintf('/clients/%d', $receiver->getId())
            ];

            foreach ($topics as $topic) {
                $update = new Update($topic, json_encode($payload), true);
                $this->mercureHub->publish($update);
                $this->logger->info('Publication Mercure réussie', ['topic' => $topic]);
            }

        } catch (\Throwable $e) {
            $this->logger->error('Échec de la publication Mercure', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \RuntimeException('Échec de la publication en temps réel', 0, $e);
        }
    }

    public function getMessagesForOrder(int $orderId, array $criteria = []): array
    {
        $order = $this->ordersRepo->find($orderId);

        if (!$order) {
            $this->logger->error('Commande non trouvée', ['order_id' => $orderId]);
            throw new \InvalidArgumentException('Commande non trouvée');
        }

        // Récupérer les messages
        $messages = $order->getMessages()->toArray();
        
        // Appliquer le filtrage
        if (isset($criteria['sender_id'])) {
            $messages = array_filter($messages, function(Messages $message) use ($criteria) {
                return $message->getSender()->getId() == $criteria['sender_id'];
            });
        }
        
        if (isset($criteria['from_date'])) {
            $fromDate = new \DateTime($criteria['from_date']);
            $messages = array_filter($messages, function(Messages $message) use ($fromDate) {
                return $message->getSentAt() >= $fromDate;
            });
        }
        
        // Appliquer le tri
        $sortBy = $criteria['sort'] ?? 'sent_at';
        $sortOrder = strtoupper($criteria['order'] ?? 'DESC') === 'ASC' ? 1 : -1;
        
        usort($messages, function(Messages $a, Messages $b) use ($sortBy, $sortOrder) {
            $valueA = $this->getPropertyValue($a, $sortBy);
            $valueB = $this->getPropertyValue($b, $sortBy);
            
            if ($valueA == $valueB) {
                return 0;
            }
            
            return ($valueA < $valueB ? -1 : 1) * $sortOrder;
        });
        
        // Calculer le total
        $totalMessages = count($messages);
        
        // Appliquer la pagination
        $page = max(1, $criteria['page'] ?? 1);
        $limit = max(1, $criteria['limit'] ?? 20);
        $offset = ($page - 1) * $limit;
        
        $messages = array_slice($messages, $offset, $limit);
        
        // Transformer les messages en tableau
        $messageData = array_map(function (Messages $message) {
            return [
                'id' => $message->getId(),
                'order' => $message->getOrder(),
                'sender' => $message->getSender(),
                'receiver' => $message->getReceiver(),
                'content' => $message->getContent(),
                'sent_at' => $message->getSentAt(),
            ];
        }, $messages);
        
        return [
            'messages' => $messageData,
            'total' => $totalMessages
        ];
    }

    /**
     * Helper to get a property value from a message for sorting
     */
    private function getPropertyValue(Messages $message, string $property)
    {
        switch ($property) {
            case 'sent_at':
                return $message->getSentAt()->getTimestamp();
            case 'sender_id':
                return $message->getSender()->getId();
            case 'receiver_id':
                return $message->getReceiver()->getId();
            case 'content':
                return $message->getContent();
            default:
                return $message->getId();
        }
    }
}