<?php

namespace App\Service;

use App\Entity\Messages;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Service de gestion de la file d'attente pour les publications Mercure
 */
class MercureQueueService
{
    private const MAX_RETRIES = 5;
    private const RETRY_DELAY_BASE = 500; // en millisecondes

    public function __construct(
        private EntityManagerInterface $entityManager,
        private HubInterface $mercureHub,
        private LoggerInterface $logger
    ) {}

    /**
     * Tente de republier un message qui a échoué précédemment
     * 
     * @param Messages $message Le message à republier
     * @param int $attempt Numéro de la tentative actuelle
     * @return bool True si la publication a réussi, False sinon
     */
    public function retryFailedMessage(Messages $message, int $attempt = 1): bool
    {
        if ($attempt > self::MAX_RETRIES) {
            $this->logger->error('Nombre maximum de tentatives atteint pour la publication Mercure', [
                'message_id' => $message->getId(),
                'order_id' => $message->getOrder()->getId(),
                'attempts' => $attempt
            ]);
            return false;
        }

        try {
            if (!$this->mercureHub->getUrl()) {
                $this->logger->warning('Mercure Hub non configuré pour la tentative de republication', [
                    'message_id' => $message->getId(),
                    'attempt' => $attempt
                ]);
                return false;
            }

            $sender = $message->getSender();
            $receiver = $message->getReceiver();
            $order = $message->getOrder();
            
            $payload = [
                'id' => $message->getId(),
                'order_id' => $order->getId(),
                'sender_id' => $sender->getId(),
                'receiver_id' => $receiver->getId(),
                'content' => $message->getContent(),
                'sent_at' => $message->getSentAt()->format(\DateTimeInterface::ATOM),
                'retried' => true,
                'retry_count' => $attempt
            ];

            $this->logger->info('Tentative de republication Mercure', [
                'message_id' => $message->getId(),
                'attempt' => $attempt
            ]);

            $topics = [
                sprintf('/agents/%d', $sender->getId()),
                sprintf('/clients/%d', $receiver->getId())
            ];

            $allSuccess = true;
            foreach ($topics as $topic) {
                $update = new Update($topic, json_encode($payload), true);
                $this->mercureHub->publish($update);
                $this->logger->info('Republication Mercure réussie', [
                    'topic' => $topic,
                    'message_id' => $message->getId(),
                    'attempt' => $attempt
                ]);
            }

            return true;
        } catch (\Throwable $e) {
            // Calculer un délai exponentiel basé sur le numéro de tentative
            $retryDelay = self::RETRY_DELAY_BASE * pow(2, $attempt - 1);
            
            $this->logger->warning('Échec de la tentative de republication Mercure', [
                'message_id' => $message->getId(),
                'attempt' => $attempt,
                'next_retry_in' => $retryDelay . 'ms',
                'error' => $e->getMessage()
            ]);

            // On pourrait implémenter ici une logique pour planifier une nouvelle tentative
            // via un message de queue asynchrone ou une tâche planifiée
            
            return false;
        }
    }

    /**
     * Programme la republication d'un message dans la file d'attente
     * Note: Cette méthode simule l'ajout à une file d'attente - dans une implémentation
     * réelle, on utiliserait Messenger ou un système de queue comme RabbitMQ
     * 
     * @param Messages $message Le message à republier
     * @param int $attempt Numéro de la tentative actuelle
     */
    public function queueMessageForRetry(Messages $message, int $attempt = 1): void
    {
        // Calculer le délai avant la prochaine tentative (backoff exponentiel)
        $retryDelay = self::RETRY_DELAY_BASE * pow(2, $attempt - 1);
        
        $this->logger->info('Message mis en file d\'attente pour republication', [
            'message_id' => $message->getId(),
            'attempt' => $attempt,
            'next_retry_in' => $retryDelay . 'ms'
        ]);
        
        // Ici, on pourrait :
        // 1. Enregistrer le message dans une table de la base de données avec un statut "en attente"
        // 2. Envoyer un message à Symfony Messenger ou à un système de file d'attente externe
        // 3. Utiliser une tâche CRON ou un worker pour traiter les messages en attente
        
        // Pour l'instant, simulons une republication immédiate après le délai (à des fins de démo)
        // Dans un environnement de production, cette logique serait dans un Worker ou un Consumer
        usleep($retryDelay * 1000); // Conversion en microsecondes
        $this->retryFailedMessage($message, $attempt + 1);
    }

    /**
     * Récupère les messages dont la publication a échoué et tente de les republier
     * Cette méthode pourrait être appelée par une commande CRON ou un worker
     * 
     * @param int $limit Nombre maximum de messages à traiter
     * @return int Nombre de messages traités avec succès
     */
    public function processFailedMessages(int $limit = 50): int
    {
        // Dans une implémentation réelle, on récupérerait les messages en échec depuis une table dédiée
        // Pour l'instant, cette méthode est un exemple d'implémentation
        
        $this->logger->info('Début du traitement des messages Mercure en échec', [
            'limit' => $limit
        ]);
        
        $successCount = 0;
        
        // Exemple fictif de traitement
        /*
        $failedMessages = $this->failedMessageRepository->findPendingMessages($limit);
        
        foreach ($failedMessages as $failedMessage) {
            $message = $failedMessage->getMessage();
            $attempt = $failedMessage->getAttemptCount() + 1;
            
            $success = $this->retryFailedMessage($message, $attempt);
            
            if ($success) {
                $failedMessage->setStatus('success');
                $failedMessage->setProcessedAt(new \DateTime());
                $successCount++;
            } else {
                $failedMessage->setAttemptCount($attempt);
                
                if ($attempt >= self::MAX_RETRIES) {
                    $failedMessage->setStatus('failed');
                }
            }
            
            $this->entityManager->persist($failedMessage);
        }
        
        $this->entityManager->flush();
        */
        
        $this->logger->info('Fin du traitement des messages Mercure en échec', [
            'processed' => $successCount
        ]);
        
        return $successCount;
    }
}
