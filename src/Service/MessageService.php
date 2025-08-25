<?php

namespace App\Service;

use App\Entity\Messages;
use App\Repository\ServiceOrdersRepository;
use App\Repository\UserRepository;
use App\Repository\TasksRepository;
use App\Enum\UserRole;
use App\Enum\EntityType;
use App\Service\CryptService;
use App\Service\TimeService;
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
        private TimeService $timeService,
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
        // 1. Validation des données d'entrée
        $validatedData = $this->validateMessageData($data);
        
        // 2. Récupération des entités
        $entities = $this->retrieveEntities($validatedData);
        
        // 3. Validation métier
        $this->validateBusinessRules($entities['order'], $entities['sender'], $entities['receiver']);
        
        // 4. Création et persistance du message
        $message = $this->createAndPersistMessage($entities, $validatedData['content']);
        
        // 5. Publication Mercure
        $this->publishMercureUpdate($message, $entities['order'], $entities['sender'], $entities['receiver'], $validatedData['content']);

        return $message;
    }

    /**
     * Valide les données d'entrée du message
     */
    private function validateMessageData(array $data): array
    {
        $orderId = $data['order_id'] ?? null;
        $senderId = $data['sender_id'] ?? null;
        $receiverId = $data['receiver_id'] ?? null;
        $content = trim($data['content'] ?? '');

        if (!$orderId || !$senderId || !$receiverId || empty($content)) {
            $this->logger->error('Paramètres manquants ou invalides', $data);
            throw new \InvalidArgumentException('Paramètres manquants ou invalides');
        }

        return [
            'order_id' => $orderId,
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'content' => $content
        ];
    }

    /**
     * Récupère les entités depuis la base de données
     */
    private function retrieveEntities(array $validatedData): array
    {
        $order = $this->ordersRepo->find($validatedData['order_id']);
        $sender = $this->userRepo->find($validatedData['sender_id']);
        $receiver = $this->userRepo->find($validatedData['receiver_id']);

        if (!$order || !$sender || !$receiver) {
            $this->logger->error('Entités non trouvées', [
                'order_exists' => (bool)$order,
                'sender_exists' => (bool)$sender,
                'receiver_exists' => (bool)$receiver
            ]);
            throw new \InvalidArgumentException('Commande ou utilisateurs non trouvés');
        }

        return [
            'order' => $order,
            'sender' => $sender,
            'receiver' => $receiver
        ];
    }

    /**
     * Valide les règles métier
     */
    private function validateBusinessRules($order, $sender, $receiver): void
    {
        $this->validateUserRoles($sender, $receiver);
        $this->validateUsersAccessToOrder($order, $sender, $receiver);
    }

    /**
     * Valide les rôles des utilisateurs
     */
    private function validateUserRoles($sender, $receiver): void
    {
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
    }

    /**
     * Crée et persiste le message en base de données
     */
    private function createAndPersistMessage(array $entities, string $content): Messages
    {
        $message = new Messages();
        $message->setOrder($entities['order']);
        $message->setSender($entities['sender']);
        $message->setReceiver($entities['receiver']);
        $message->setContent($content);
        // Utiliser le TimeService pour avoir la bonne heure locale
        $message->setSentAt($this->timeService->now());

        $this->em->persist($message);
        $this->em->flush();

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
        // Vérification de la configuration Mercure
        if (!$this->isMercureConfigured()) {
            $this->logger->warning('Mercure Hub non configuré', [
                'message_id' => $message->getId(),
                'order_id' => $order->getId()
            ]);
            return;
        }

        // Préparation du payload avec IDs cryptés
        $payload = $this->buildEncryptedPayload($message, $order, $sender, $receiver, $content);
        
        // Génération du topic de conversation avec IDs cryptés
        $conversationTopic = $this->generateConversationTopic($sender, $receiver);
        
        // Publication du message
        $this->publishToMercure($conversationTopic, $payload, $message);
    }

    /**
     * Vérifie si Mercure est configuré
     */
    private function isMercureConfigured(): bool
    {
        return !empty($this->mercureHub->getUrl());
    }

    /**
     * Construit le payload avec les IDs cryptés
     */
    private function buildEncryptedPayload(
        Messages $message,
        $order,
        $sender,
        $receiver,
        string $content
    ): array {
        $payload = [
            'id' => $this->cryptService->encryptId((string) $message->getId(), EntityType::MESSAGE->value),
            'order_id' => $this->cryptService->encryptId((string) $order->getId(), EntityType::SERVICE_ORDER->value),
            'sender_id' => $this->cryptService->encryptId((string) $sender->getId(), EntityType::USER->value),
            'receiver_id' => $this->cryptService->encryptId((string) $receiver->getId(), EntityType::USER->value),
            'content' => $content,
            'sent_at' => $this->timeService->formatForApi($message->getSentAt()),
        ];

        $this->logger->debug('Payload Mercure préparé avec IDs cryptés', [
            'message_id' => $message->getId(),
            'order_id' => $order->getId(),
            'encrypted_payload' => array_map(function($value) {
                return is_string($value) && strlen($value) > 50 ? substr($value, 0, 20) . '...' : $value;
            }, $payload)
        ]);

        return $payload;
    }

    /**
     * Génère le topic de conversation avec IDs cryptés et tri
     */
    private function generateConversationTopic($sender, $receiver): string
    {
        $senderEncryptedId = $this->cryptService->encryptId((string) $sender->getId(), EntityType::USER->value);
        $receiverEncryptedId = $this->cryptService->encryptId((string) $receiver->getId(), EntityType::USER->value);
        
        // Tri pour garantir la cohérence du topic
        $encryptedIds = [$senderEncryptedId, $receiverEncryptedId];
        sort($encryptedIds);
        
        $conversationTopic = "chat/conversation/{$encryptedIds[0]}-{$encryptedIds[1]}";
        
        $this->logger->debug('Topic de conversation généré', [
            'sender_id' => $sender->getId(),
            'receiver_id' => $receiver->getId(),
            'topic' => $conversationTopic
        ]);

        return $conversationTopic;
    }

    /**
     * Publie le message vers Mercure avec retry logic
     */
    private function publishToMercure(string $topic, array $payload, Messages $message): void
    {
        $success = $this->attemptMercurePublication($topic, $payload, $message);
        
        if (!$success) {
            $this->handlePublicationFailure($message);
        }
    }

    /**
     * Tente la publication Mercure avec retry automatique
     */
    private function attemptMercurePublication(string $topic, array $payload, Messages $message): bool
    {
        $maxRetries = 3;
        $retryDelay = 500; // Délai entre les tentatives en millisecondes
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $this->logger->debug('Tentative de publication Mercure', [
                    'topic' => $topic,
                    'attempt' => $attempt,
                    'message_id' => $message->getId()
                ]);
                
                // Publication PUBLIC (pas de 3ème paramètre = public)
                $update = new Update($topic, json_encode($payload));
                $this->mercureHub->publish($update);
                
                $this->logger->info('Publication Mercure réussie', [
                    'topic' => $topic,
                    'attempt' => $attempt,
                    'message_id' => $message->getId(),
                    'payload_size' => strlen(json_encode($payload))
                ]);
                
                return true;
                
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
                    
                    // Augmenter progressivement le délai (backoff exponentiel)
                    $retryDelay *= 2;
                }
            }
        }

        // Toutes les tentatives ont échoué
        $this->logger->error('Échec définitif de la publication Mercure', [
            'topic' => $topic,
            'message_id' => $message->getId(),
            'attempts' => $maxRetries,
            'final_error' => $lastException ? $lastException->getMessage() : 'Erreur inconnue'
        ]);

        return false;
    }

    /**
     * Gère l'échec de publication Mercure
     */
    private function handlePublicationFailure(Messages $message): void
    {
        // Si on a un service de file d'attente, ajouter le message pour retry
        if ($this->mercureQueueService !== null) {
            $this->logger->notice('Ajout à la file d\'attente pour réessai asynchrone', [
                'message_id' => $message->getId()
            ]);
            
            $this->mercureQueueService->queueMessageForRetry($message);
            return;
        }
        
        // Sinon, lancer une exception
        throw new \RuntimeException('Échec de la publication Mercure en temps réel après plusieurs tentatives');
    }

    public function getMessagesForOrder(int $orderId, array $criteria = []): array
    {
        $order = $this->findOrderById($orderId);
        $messages = $this->filterMessages($order->getMessages()->toArray(), $criteria);
        $messages = $this->sortMessages($messages, $criteria);
        
        $totalMessages = count($messages);
        $paginatedMessages = $this->paginateMessages($messages, $criteria);
        
        return [
            'messages' => $this->transformMessagesToArray($paginatedMessages),
            'total' => $totalMessages
        ];
    }

    /**
     * Trouve une commande par son ID
     */
    private function findOrderById(int $orderId)
    {
        $order = $this->ordersRepo->find($orderId);

        if (!$order) {
            $this->logger->error('Commande non trouvée', ['order_id' => $orderId]);
            throw new \InvalidArgumentException('Commande non trouvée');
        }

        return $order;
    }

    /**
     * Filtre les messages selon les critères
     */
    private function filterMessages(array $messages, array $criteria): array
    {
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

        return $messages;
    }

    /**
     * Trie les messages selon les critères
     */
    private function sortMessages(array $messages, array $criteria): array
    {
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

        return $messages;
    }

    /**
     * Applique la pagination aux messages
     */
    private function paginateMessages(array $messages, array $criteria): array
    {
        $page = max(1, $criteria['page'] ?? 1);
        $limit = max(1, $criteria['limit'] ?? 20);
        $offset = ($page - 1) * $limit;
        
        return array_slice($messages, $offset, $limit);
    }

    /**
     * Transforme les messages en tableau pour l'API
     */
    private function transformMessagesToArray(array $messages): array
    {
        return array_map(function (Messages $message) {
            return [
                'id' => $this->cryptService->encryptId((string) $message->getId(), EntityType::MESSAGE->value),
                'order_id' => $this->cryptService->encryptId((string) $message->getOrder()->getId(), EntityType::SERVICE_ORDER->value),
                'sender_id' => $this->cryptService->encryptId((string) $message->getSender()->getId(), EntityType::USER->value),
                'receiver_id' => $this->cryptService->encryptId((string) $message->getReceiver()->getId(), EntityType::USER->value),
                'content' => $message->getContent(),
                'sent_at' => $this->timeService->formatForApi($message->getSentAt()),
            ];
        }, $messages);
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

    /**
     * Crée plusieurs messages vers différents destinataires en même temps.
     * Chaque destinataire reçoit le même message comme une conversation distincte.
     *
     * @param array $data Données contenant sender_id, receiver_ids[], order_id, content
     * @return array Résultat avec compteurs et détails des succès/échecs
     * @throws \InvalidArgumentException si validation échoue
     */
    public function createMultipleMessages(array $data): array
    {
        $this->logger->info('Début de création de messages multiples', [
            'sender_id' => $data['sender_id'] ?? null,
            'receiver_count' => count($data['receiver_ids'] ?? []),
            'order_id' => $data['order_id'] ?? null
        ]);

        // 1. Validation des données d'entrée pour multi-message
        $validatedData = $this->validateMultiMessageData($data);
        
        // 2. Récupération des entités communes (sender et order)
        $commonEntities = $this->retrieveCommonEntities($validatedData);
        
        // 3. Validation des règles métier communes
        $this->validateCommonBusinessRules($commonEntities['order'], $commonEntities['sender']);
        
        // 4. Traitement de chaque destinataire
        $results = $this->processMultipleReceivers($validatedData, $commonEntities);
        
        $this->logger->info('Fin de création de messages multiples', [
            'total_sent' => $results['total_sent'],
            'total_failed' => $results['total_failed']
        ]);
        
        return $results;
    }

    /**
     * Valide les données d'entrée pour un multi-message
     */
    private function validateMultiMessageData(array $data): array
    {
        $orderId = $data['order_id'] ?? null;
        $senderId = $data['sender_id'] ?? null;
        $receiverIds = $data['receiver_ids'] ?? [];
        $content = trim($data['content'] ?? '');

        if (!$orderId || !$senderId || empty($receiverIds) || empty($content)) {
            $this->logger->error('Paramètres manquants ou invalides pour multi-message', $data);
            throw new \InvalidArgumentException('Paramètres manquants ou invalides');
        }

        if (!is_array($receiverIds)) {
            throw new \InvalidArgumentException('receiver_ids doit être un tableau');
        }

        if (count($receiverIds) === 0) {
            throw new \InvalidArgumentException('Au moins un destinataire est requis');
        }

        // Suppression des doublons dans receiver_ids
        $receiverIds = array_unique($receiverIds);

        return [
            'order_id' => $orderId,
            'sender_id' => $senderId,
            'receiver_ids' => $receiverIds,
            'content' => $content
        ];
    }

    /**
     * Récupère les entités communes (sender et order)
     */
    private function retrieveCommonEntities(array $validatedData): array
    {
        $order = $this->ordersRepo->find($validatedData['order_id']);
        $sender = $this->userRepo->find($validatedData['sender_id']);

        if (!$order || !$sender) {
            $this->logger->error('Entités communes non trouvées', [
                'order_exists' => (bool)$order,
                'sender_exists' => (bool)$sender
            ]);
            throw new \InvalidArgumentException('Commande ou expéditeur non trouvé');
        }

        return [
            'order' => $order,
            'sender' => $sender
        ];
    }

    /**
     * Valide les règles métier communes
     */
    private function validateCommonBusinessRules($order, $sender): void
    {
        // Valider le rôle de l'expéditeur
        if (!in_array($sender->getRole(), [UserRole::CLIENT, UserRole::AGENT])) {
            $this->logger->error('Sender n\'est ni client ni agent pour multi-message', [
                'sender_id' => $sender->getId(),
                'sender_role' => $sender->getRole()->value
            ]);
            throw new \InvalidArgumentException('L\'expéditeur doit être un client ou un agent');
        }

        // Vérifier que l'expéditeur a accès à cette commande
        $this->validateSenderAccessToOrder($order, $sender);
    }

    /**
     * Traite chaque destinataire individuellement
     */
    private function processMultipleReceivers(array $validatedData, array $commonEntities): array
    {
        $successfulConversations = [];
        $failedConversations = [];
        $totalSent = 0;
        $totalFailed = 0;

        foreach ($validatedData['receiver_ids'] as $receiverId) {
            try {
                // Récupérer le destinataire
                $receiver = $this->userRepo->find($receiverId);
                if (!$receiver) {
                    $failedConversations[] = [
                        'receiver_id' => $this->cryptService->encryptId((string)$receiverId, EntityType::USER->value),
                        'error' => 'Destinataire non trouvé'
                    ];
                    $totalFailed++;
                    continue;
                }

                // Éviter l'auto-envoi
                if ($receiver->getId() === $commonEntities['sender']->getId()) {
                    $failedConversations[] = [
                        'receiver_id' => $this->cryptService->encryptId((string)$receiverId, EntityType::USER->value),
                        'error' => 'Impossible d\'envoyer un message à soi-même'
                    ];
                    $totalFailed++;
                    continue;
                }

                // Valider le destinataire pour cette commande
                $this->validateReceiverForOrder($commonEntities['order'], $commonEntities['sender'], $receiver);

                // Créer et persister le message
                $message = $this->createAndPersistMessage([
                    'order' => $commonEntities['order'],
                    'sender' => $commonEntities['sender'],
                    'receiver' => $receiver
                ], $validatedData['content']);

                // Publication Mercure
                $this->publishMercureUpdate(
                    $message,
                    $commonEntities['order'],
                    $commonEntities['sender'],
                    $receiver,
                    $validatedData['content']
                );

                $successfulConversations[] = [
                    'receiver_id' => $this->cryptService->encryptId((string)$receiver->getId(), EntityType::USER->value),
                    'message_id' => $this->cryptService->encryptId((string)$message->getId(), EntityType::MESSAGE->value)
                ];
                $totalSent++;

                $this->logger->info('Message multi envoyé avec succès', [
                    'message_id' => $message->getId(),
                    'receiver_id' => $receiver->getId()
                ]);

            } catch (\Exception $e) {
                $this->logger->error('Échec envoi message multi', [
                    'receiver_id' => $receiverId,
                    'error' => $e->getMessage()
                ]);

                $failedConversations[] = [
                    'receiver_id' => $this->cryptService->encryptId((string)$receiverId, EntityType::USER->value),
                    'error' => $e->getMessage()
                ];
                $totalFailed++;
            }
        }

        return [
            'total_sent' => $totalSent,
            'total_failed' => $totalFailed,
            'successful_conversations' => $successfulConversations,
            'failed_conversations' => $failedConversations
        ];
    }

    /**
     * Valide que l'expéditeur a accès à la commande
     */
    private function validateSenderAccessToOrder($order, $sender): void
    {
        $orderId = $order->getId();
        $clientId = $order->getClient()->getId();

        // Si l'expéditeur est le client de la commande, il a accès
        if ($sender->getId() === $clientId) {
            return;
        }

        // Si l'expéditeur est un agent, vérifier qu'il est assigné à cette commande
        if ($sender->getRole() === UserRole::AGENT) {
            $tasks = $this->tasksRepo->findBy(['order' => $order]);
            $assignedAgentUserIds = [];
            
            foreach ($tasks as $task) {
                $assignedAgentUserIds[] = $task->getAgent()->getUser()->getId();
            }

            if (!in_array($sender->getId(), $assignedAgentUserIds)) {
                throw new \InvalidArgumentException('L\'agent expéditeur n\'est pas assigné à cette commande');
            }
        }
    }

    /**
     * Valide qu'un destinataire est valide pour cette commande
     */
    private function validateReceiverForOrder($order, $sender, $receiver): void
    {
        // Valider le rôle du destinataire
        if (!in_array($receiver->getRole(), [UserRole::CLIENT, UserRole::AGENT])) {
            throw new \InvalidArgumentException('Le destinataire doit être un client ou un agent');
        }

        $orderId = $order->getId();
        $clientId = $order->getClient()->getId();

        // Le destinataire doit être soit le client de la commande, soit un agent assigné
        if ($receiver->getId() === $clientId) {
            return; // Le client a toujours accès à ses commandes
        }

        // Si le destinataire est un agent, vérifier qu'il est assigné à cette commande
        if ($receiver->getRole() === UserRole::AGENT) {
            $tasks = $this->tasksRepo->findBy(['order' => $order]);
            $assignedAgentUserIds = [];
            
            foreach ($tasks as $task) {
                $assignedAgentUserIds[] = $task->getAgent()->getUser()->getId();
            }

            if (!in_array($receiver->getId(), $assignedAgentUserIds)) {
                throw new \InvalidArgumentException('L\'agent destinataire n\'est pas assigné à cette commande');
            }
        }
    }
}