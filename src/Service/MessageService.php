<?php

namespace App\Service;

use App\Entity\Messages;
use App\Repository\ServiceOrdersRepository;
use App\Repository\UserRepository;
use App\Repository\TasksRepository;
use App\Enum\UserRole;
use App\Enum\EntityType;
use App\Service\CryptService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class MessageService
{
    private ?MercureQueueService $mercureQueueService;

    public function __construct(
        private EntityManagerInterface $em,
        private ServiceOrdersRepository $ordersRepo,
        private UserRepository $userRepo,
        private TasksRepository $tasksRepo,
        private HubInterface $mercureHub,
        private LoggerInterface $logger,
        private CryptService $cryptService,
        MercureQueueService $mercureQueueService = null
    ) {
        $this->mercureQueueService = $mercureQueueService;
    }

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

        // Validation métier - vérifier que les utilisateurs ont les bons rôles
        if (!in_array($sender->getRole(), [UserRole::CLIENT, UserRole::AGENT])) {
            $this->logger->error('Sender n\'est ni client ni agent', [
                'sender_id' => $sender->getId(),
                'sender_role' => $sender->getRole()->value
            ]);
            throw new \InvalidArgumentException('L\'expéditeur doit être un client ou un agent');
        }

        if (!in_array($receiver->getRole(), [UserRole::CLIENT, UserRole::AGENT])) {
            $this->logger->error('Receiver n\'est ni client ni agent', [
                'receiver_id' => $receiver->getId(),
                'receiver_role' => $receiver->getRole()->value
            ]);
            throw new \InvalidArgumentException('Le destinataire doit être un client ou un agent');
        }

        // Validation métier - vérifier que les utilisateurs sont liés à cette commande
        $this->validateUsersAccessToOrder($order, $sender, $receiver);

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
     * Inclut des tentatives de réessai et une gestion d'erreurs améliorée
     */
    private function publishMercureUpdate(
        Messages $message,
        $order,
        $sender,
        $receiver,
        string $content
    ): void {
        // Configuration des tentatives
        $maxRetries = 3;
        $retryDelay = 500; // Délai entre les tentatives en millisecondes
        $success = false;
        $lastException = null;

        if (!$this->mercureHub->getUrl()) {
            $this->logger->warning('Mercure Hub non configuré', [
                'message_id' => $message->getId(),
                'order_id' => $order->getId()
            ]);
            return;
        }

        // Préparation du payload une seule fois avec IDs cryptés
        $payload = [
            'id' => $this->cryptService->encryptId((string) $message->getId(), EntityType::MESSAGE->value),
            'order_id' => $this->cryptService->encryptId((string) $order->getId(), EntityType::SERVICE_ORDER->value),
            'sender_id' => $this->cryptService->encryptId((string) $sender->getId(), EntityType::USER->value),
            'receiver_id' => $this->cryptService->encryptId((string) $receiver->getId(), EntityType::USER->value),
            'content' => $content,
            'sent_at' => $message->getSentAt()->format(\DateTimeInterface::ATOM),
        ];

        $this->logger->debug('Préparation de la publication Mercure', [
            'message_id' => $message->getId(),
            'order_id' => $order->getId(),
            'payload' => $payload
        ]);

        // Créer le topic de conversation avec IDs cryptés
        $senderEncryptedId = $this->cryptService->encryptId((string) $sender->getId(), EntityType::USER->value);
        $receiverEncryptedId = $this->cryptService->encryptId((string) $receiver->getId(), EntityType::USER->value);
        
        // Déterminer l'ordre pour garantir la cohérence (min-max) avec un tri approprié
        $encryptedIds = [$senderEncryptedId, $receiverEncryptedId];
        sort($encryptedIds);
        
        $conversationTopic = "chat/conversation/{$encryptedIds[0]}-{$encryptedIds[1]}";

        $topics = [$conversationTopic];

        foreach ($topics as $topicIndex => $topic) {
            $attempt = 0;
            $success = false;
            
            while (!$success && $attempt < $maxRetries) {
                $attempt++;
                try {
                    $this->logger->debug('Tentative de publication Mercure', [
                        'topic' => $topic,
                        'attempt' => $attempt,
                        'message_id' => $message->getId()
                    ]);
                    
                    $update = new Update($topic, json_encode($payload), true);
                    $this->mercureHub->publish($update);
                    
                    $this->logger->info('Publication Mercure réussie', [
                        'topic' => $topic,
                        'attempt' => $attempt,
                        'message_id' => $message->getId()
                    ]);
                    
                    $success = true;
                } catch (\Throwable $e) {
                    $lastException = $e;
                    
                    $this->logger->warning('Échec de la tentative de publication Mercure', [
                        'topic' => $topic,
                        'attempt' => $attempt,
                        'message_id' => $message->getId(),
                        'error' => $e->getMessage(),
                        'retry_remaining' => ($maxRetries - $attempt)
                    ]);
                    
                    // Si ce n'est pas la dernière tentative, attendre avant de réessayer
                    if ($attempt < $maxRetries) {
                        usleep($retryDelay * 1000); // Conversion en microsecondes
                        
                        // Augmenter progressivement le délai pour les tentatives suivantes (backoff exponentiel)
                        $retryDelay *= 2;
                    }
                }
            }
            
            // Si toutes les tentatives ont échoué pour ce topic
            if (!$success) {
                $this->logger->error('Échec définitif de la publication Mercure', [
                    'topic' => $topic,
                    'message_id' => $message->getId(),
                    'attempts' => $attempt,
                    'error' => $lastException ? $lastException->getMessage() : 'Erreur inconnue',
                    'trace' => $lastException ? $lastException->getTraceAsString() : ''
                ]);
                
                // Ne pas interrompre la boucle, essayer le prochain topic
            }
        }
        
        // Si au moins un topic a échoué et qu'on a un service de file d'attente
        if ($lastException !== null && !$success && $this->mercureQueueService !== null) {
            // Ajouter le message à la file d'attente pour réessai asynchrone
            $this->logger->notice('Échec de publication Mercure - ajout à la file d\'attente pour réessai', [
                'message_id' => $message->getId(),
                'order_id' => $order->getId()
            ]);
            
            $this->mercureQueueService->queueMessageForRetry($message);
            
            // Ne pas lancer d'exception, car le message sera réessayé plus tard
            return;
        }
        
        // Si au moins un topic a échoué et qu'on n'a pas de service de file d'attente, lancer une exception
        if ($lastException !== null && !$success) {
            throw new \RuntimeException('Échec de la publication en temps réel après plusieurs tentatives', 0, $lastException);
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
    
    /**
     * Vérifie si un utilisateur a le droit d'accéder à une commande
     *
     * @param int $orderId ID de la commande
     * @param int $userId ID de l'utilisateur
     * @param string $role Rôle de l'utilisateur (admin, agent, client)
     * @return bool True si l'utilisateur a accès, sinon false
     */
    public function userHasAccessToOrder(int $orderId, int $userId, string $role): bool
    {
        return $this->ordersRepo->userHasAccessToOrder($orderId, $userId, $role);
    }

    /**
     * Valide que les utilisateurs (sender et receiver) ont accès à cette commande
     */
    private function validateUsersAccessToOrder($order, $sender, $receiver): void
    {
        $orderId = $order->getId();
        $clientId = $order->getClient()->getId();

        // Le client de la commande doit être soit sender soit receiver
        if ($clientId !== $sender->getId() && $clientId !== $receiver->getId()) {
            $this->logger->error('Aucun des utilisateurs n\'est le client de cette commande', [
                'order_id' => $orderId,
                'client_id' => $clientId,
                'sender_id' => $sender->getId(),
                'receiver_id' => $receiver->getId()
            ]);
            throw new \InvalidArgumentException('Aucun des utilisateurs n\'est le client de cette commande');
        }

        // Récupérer les agents assignés à cette commande via les tâches
        $tasks = $this->tasksRepo->findBy(['order' => $order]);
        $assignedAgentUserIds = [];
        foreach ($tasks as $task) {
            $assignedAgentUserIds[] = $task->getAgent()->getUser()->getId();
        }

        // Vérifier que si un utilisateur est un agent, il doit être assigné à cette commande
        if ($sender->getRole() === UserRole::AGENT && !in_array($sender->getId(), $assignedAgentUserIds)) {
            $this->logger->error('L\'agent expéditeur n\'est pas assigné à cette commande', [
                'order_id' => $orderId,
                'sender_id' => $sender->getId(),
                'assigned_agents' => $assignedAgentUserIds
            ]);
            throw new \InvalidArgumentException('L\'agent expéditeur n\'est pas assigné à cette commande');
        }

        if ($receiver->getRole() === UserRole::AGENT && !in_array($receiver->getId(), $assignedAgentUserIds)) {
            $this->logger->error('L\'agent destinataire n\'est pas assigné à cette commande', [
                'order_id' => $orderId,
                'receiver_id' => $receiver->getId(),
                'assigned_agents' => $assignedAgentUserIds
            ]);
            throw new \InvalidArgumentException('L\'agent destinataire n\'est pas assigné à cette commande');
        }
    }
}