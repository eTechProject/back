<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\ServiceOrdersRepository;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Psr\Log\LoggerInterface;

class MercureTokenGenerator
{
    private string $mercureJwtSecret;
    
    public function __construct(
        private ServiceOrdersRepository $serviceOrdersRepository,
        private LoggerInterface $logger,
        string $mercureJwtSecret
    ) {
        $this->mercureJwtSecret = $mercureJwtSecret;
    }
    
    /**
     * Génère un JWT Mercure pour un utilisateur
     *
     * @param User $user L'utilisateur pour lequel générer le token
     * @param int $expiresInMinutes Durée de validité du token en minutes
     * @return array Tableau contenant le token et les topics autorisés
     */
    public function generateTokenForUser(User $user, int $expiresInMinutes = 60): array
    {
        try {
            // Récupérer les commandes liées à l'utilisateur selon son rôle
            $userId = $user->getId();
            $userRole = $user->getRole();
            
            $this->logger->info('Génération de token pour utilisateur', [
                'user_id' => $userId,
                'role' => $userRole instanceof \App\Enum\UserRole ? $userRole->value : (string)$userRole
            ]);
            
            // Convertir l'enum en chaîne si nécessaire
            $roleString = $userRole;
            if ($userRole instanceof \App\Enum\UserRole) {
                $roleString = $userRole->value;
            }
            
            // Récupérer les commandes en fonction du rôle
            try {
                $roleLower = strtolower($roleString);
                
                if ($roleLower === 'agent') {
                    $this->logger->info('Recherche des commandes pour agent', ['agent_id' => $userId]);
                    $orders = $this->serviceOrdersRepository->findByAgentId($userId);
                } elseif ($roleLower === 'client') {
                    $this->logger->info('Recherche des commandes pour client', ['client_id' => $userId]);
                    $orders = $this->serviceOrdersRepository->findByClientId($userId);
                } else {
                    $this->logger->info('Rôle non reconnu, aucune commande récupérée', ['role' => $roleString]);
                    $orders = [];
                }
                $this->logger->info('Commandes récupérées', ['count' => count($orders)]);
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de la récupération des commandes', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
            
            // Préparer les topics auxquels l'utilisateur peut souscrire
            $topics = [];
            foreach ($orders as $order) {
                $orderId = $order->getId();
                
                // Topic général pour la commande
                $topics[] = sprintf('/discussions/%d', $orderId);
                
                // Topics spécifiques selon le rôle
                $roleLower = strtolower($roleString);
                if ($roleLower === 'client') {
                    $topics[] = sprintf('/discussions/%d/client/%d', $orderId, $userId);
                } elseif ($roleLower === 'agent') {
                    $topics[] = sprintf('/discussions/%d/agent/%d', $orderId, $userId);
                }
            }
            
            if (empty($topics)) {
                $roleLower = strtolower($roleString);
                $topics[] = sprintf('/%s/%d', $roleLower, $userId); // Topic fallback
            }
            
            // Générer le JWT pour Mercure
            $token = $this->generateJwtToken($topics, $expiresInMinutes);
            
            return [
                'token' => $token,
                'topics' => $topics,
                'expires_in' => $expiresInMinutes * 60 // en secondes
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la génération du token Mercure', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new \RuntimeException('Erreur lors de la génération du token Mercure: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Génère un JWT token pour Mercure avec les topics spécifiés
     *
     * @param array $topics Les topics auxquels l'utilisateur peut souscrire
     * @param int $expiresInMinutes Durée de validité du token en minutes
     * @return string Le token JWT généré
     */
    private function generateJwtToken(array $topics, int $expiresInMinutes): string
    {
        $config = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($this->mercureJwtSecret)
        );
        
        $now = new \DateTimeImmutable();
        $token = $config->builder()
            ->issuedAt($now)
            ->expiresAt($now->modify(sprintf('+%d minutes', $expiresInMinutes)))
            ->withClaim('mercure', [
                'subscribe' => $topics,
                // Ne pas autoriser la publication pour les utilisateurs normaux
            ])
            ->getToken($config->signer(), $config->signingKey());
            
        return $token->toString();
    }
}
