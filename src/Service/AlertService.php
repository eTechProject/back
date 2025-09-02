<?php

namespace App\Service;

use App\DTO\Client\Request\AlertRequestDTO;
use App\Entity\Alert;
use App\Entity\User;
use App\Entity\ServiceOrders;
use App\Enum\AlertType;
use App\Repository\AlertRepository;
use App\Repository\UserRepository;
use App\Repository\ServiceOrdersRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Enum\NotificationType;
use App\Service\ServiceOrderService;

class AlertService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepo,
        private ServiceOrdersRepository $orderRepo,
        private AlertRepository $alertRepo,
        private CryptService $cryptService,
        private ServiceOrderService $serviceOrdersService
    ) {}

    /**
     * @throws \InvalidArgumentException
     */
    public function create(AlertRequestDTO $dto): Alert
    {
        try {
            $userId = $this->cryptService->decryptId($dto->userId, 'user');
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException("Impossible de déchiffrer l'ID: ID chiffré invalide");
        }
        $user = $this->userRepo->find($userId);
        if (!$user) {
            throw new \InvalidArgumentException('Utilisateur introuvable');
        }
        $order = $this->orderRepo->findLastByUser($user);
        if (!$order) {
            throw new \InvalidArgumentException('Aucune commande trouvée pour cet utilisateur');
        }
        $alert = new Alert();
        $alert->setUser($user);
        $alert->setOrder($order);
        $alert->setType(AlertType::from($dto->type));
        $alert->setTimestamp(new \DateTimeImmutable());
        $this->em->persist($alert);
        $this->em->flush();
        $this->serviceOrdersService->notifyRelatedAgents(
            $order,
            "ALERT!!!!",
            "Une nouvelle alerte a été créée par {$user->getName()}",
            NotificationType::ALERT_START
        );
        return $alert;
    }

}