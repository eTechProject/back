<?php
namespace App\Controller;

use App\DTO\Message\MessageDTO;
use App\Service\MessageService;
use App\Service\CryptService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use App\Entity\User;
use App\Service\MercureTokenGenerator;
use Psr\Log\LoggerInterface;

class MessageController extends AbstractController
{
    public function __construct(
        private MessageService $messageService,
        private CryptService $cryptService,
        private MercureTokenGenerator $mercureTokenGenerator,
        private LoggerInterface $logger
    ) {}

    #[Route('/api/messages', name: 'api_messages_post', methods: ['POST'])]
    /**
     * Crée un nouveau message
     *
     * @param Request $request La requête HTTP contenant les données du message
     * @return JsonResponse Réponse JSON avec le message créé
     */
    public function postMessage(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json(['error' => 'JSON invalide'], 400);
        }

        try {
            // Vérifier si l'utilisateur a le droit d'accéder à cette commande
            /** @var User $user */
            $user = $this->getUser();
            if (!$user) {
                return $this->json([
                    'error' => 'Utilisateur non authentifié',
                    'status' => 'error'
                ], 401);
            }
            
            $orderId = $data['order_id'] ?? null;
            
            if ($orderId) {
                // Vérifier si l'utilisateur a accès à cette commande
                if (!$this->messageService->userHasAccessToOrder(
                    $orderId, 
                    $user->getId(), 
                    $user->getRole()->value
                )) {
                    $this->logger->warning('Tentative d\'envoi de message non autorisé', [
                        'user_id' => $user->getId(),
                        'role' => $user->getRole()->value,
                        'order_id' => $orderId
                    ]);
                    
                    return $this->json([
                        'error' => 'Vous n\'êtes pas autorisé à envoyer des messages pour cette commande',
                        'status' => 'error'
                    ], 403);
                }
            }
            
            $message = $this->messageService->createMessage($data);

            // Créer le DTO avec tous les IDs chiffrés
            $messageDTO = new MessageDTO(
                encryptedId: $this->cryptService->encryptId((string)$message->getId()),
                order_id: $this->cryptService->encryptId((string)$message->getOrder()->getId()),
                sender_id: $this->cryptService->encryptId((string)$message->getSender()->getId()),
                receiver_id: $this->cryptService->encryptId((string)$message->getReceiver()->getId()),
                content: $message->getContent(),
                sent_at: $message->getSentAt()->format('Y-m-d H:i:s')
            );

            return $this->json([
                'data' => $messageDTO,
                'status' => 'success',
                'message' => 'Message envoyé avec succès'
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
    
    #[Route('/api/messages/mercure-token', name: 'api_messages_mercure_token', methods: ['GET'], priority: 10)]
    /**
     * Génère un JWT Mercure sécurisé pour l'utilisateur courant
     * Contient uniquement les topics autorisés pour les commandes de l'utilisateur
     *
     * @return JsonResponse Réponse contenant le JWT Mercure
     */
    public function generateMercureToken(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        if (!$user) {
            return $this->json([
                'error' => 'Utilisateur non authentifié',
                'status' => 'error'
            ], 401);
        }
        
        try {
            // Utiliser le service pour générer le token
            $result = $this->mercureTokenGenerator->generateTokenForUser($user);
            
            return $this->json([
                'mercureToken' => $result['token'],
                'topics' => $result['topics'],
                'expires_in' => $result['expires_in'],
                'status' => 'success'
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la génération du token Mercure: ' . $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    #[Route('/api/messages/{encryptedOrderId}', name: 'api_messages_get', methods: ['GET'], priority: -10)]
    /**
     * Récupère les messages associés à une commande avec pagination, tri et filtrage
     * 
     * L'identifiant de commande doit être chiffré avec CryptService.encryptId()
     * Exemple: /api/messages/AbC123XyZ au lieu de /api/messages/1
     *
     * @param string $encryptedOrderId Identifiant chiffré de la commande
     * @param Request $request La requête HTTP contenant les paramètres de filtrage
     * @return JsonResponse Réponse JSON avec les messages paginés
     */
    public function getMessages(string $encryptedOrderId, Request $request): JsonResponse
    {
        try {
            // Déchiffrer l'ID et conversion en entier
            try {
                $orderIdInt = (int) $this->cryptService->decryptId($encryptedOrderId);
            } catch (\InvalidArgumentException $e) {
                return $this->json([
                    'error' => 'Identifiant de commande invalide',
                    'status' => 'error'
                ], 400);
            }
            
            // Vérification des droits d'accès
            /** @var User $user */
            $user = $this->getUser();
            if (!$user) {
                return $this->json([
                    'error' => 'Utilisateur non authentifié',
                    'status' => 'error'
                ], 401);
            }
            
            // Vérifier si l'utilisateur a accès à cette commande
            if (!$this->messageService->userHasAccessToOrder(
                $orderIdInt, 
                $user->getId(), 
                $user->getRole()->value
            )) {
                $this->logger->warning('Tentative d\'accès non autorisé', [
                    'user_id' => $user->getId(),
                    'role' => $user->getRole()->value,
                    'order_id' => $orderIdInt
                ]);
                
                return $this->json([
                    'error' => 'Vous n\'êtes pas autorisé à accéder à cette commande',
                    'status' => 'error'
                ], 403);
            }
            
            // Paramètres optionnels pour filtrage/pagination
            $page = max(1, $request->query->getInt('page', 1));
            $limit = max(1, min(100, $request->query->getInt('limit', 20))); // Limite entre 1 et 100
            
            // Validation des paramètres de tri
            $allowedSortFields = ['sent_at', 'content', 'sender_id', 'receiver_id'];
            $sortBy = in_array($request->query->get('sort'), $allowedSortFields) 
                ? $request->query->get('sort') 
                : 'sent_at';
            
            $sortOrder = strtoupper($request->query->get('order', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
            $senderId = $request->query->get('sender_id');
            $fromDate = $request->query->get('from_date');
            
            // Construire les critères de filtrage
            $criteria = [
                'page' => $page,
                'limit' => $limit,
                'sort' => $sortBy,
                'order' => $sortOrder
            ];
            
            if ($senderId) {
                $criteria['sender_id'] = $senderId;
            }
            
            if ($fromDate) {
                try {
                    new \DateTime($fromDate); // Valider le format de date
                    $criteria['from_date'] = $fromDate;
                } catch (\Exception $e) {
                    // Ignorer la date si le format est invalide
                }
            }
            
            // Récupérer les messages avec pagination et filtrage
            $result = $this->messageService->getMessagesForOrder($orderIdInt, $criteria);
            $messages = $result['messages'] ?? [];
            $totalItems = $result['total'] ?? count($messages);
            $totalPages = ceil($totalItems / $limit);
            
            // Transformer en DTOs
            $messageDTOs = [];
            foreach ($messages as $message) {
                $messageDTOs[] = new MessageDTO(
                    encryptedId: $this->cryptService->encryptId((string)$message['id']),
                    order_id: $this->cryptService->encryptId((string)$message['order']->getId()),
                    sender_id: $this->cryptService->encryptId((string)$message['sender']->getId()),
                    receiver_id: $this->cryptService->encryptId((string)$message['receiver']->getId()),
                    content: $message['content'],
                    sent_at: $message['sent_at']->format('Y-m-d H:i:s')
                );
            }
            
            // Inclure les métadonnées de pagination dans la réponse avec la structure demandée
            return $this->json([
                'data' => $messageDTOs,
                'total' => $totalItems,
                'page' => $page,
                'pages' => $totalPages,
                'status' => 'success'
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 404);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Une erreur inattendue s\'est produite',
                'status' => 'error'
            ], 500);
        }
    }
}
