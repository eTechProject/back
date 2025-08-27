<?php

namespace App\Service;

use App\DTO\Client\Request\AlertRequestDTO;
use App\Entity\Alert;
use App\Entity\User;
use App\Entity\ServiceOrders;
use App\Repository\AlertRepository;
use App\Repository\UserRepository;
use App\Repository\ServiceOrdersRepository;
use Doctrine\ORM\EntityManagerInterface;

class AlertService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepo,
        private ServiceOrdersRepository $orderRepo,
        private AlertRepository $alertRepo
    ) {}

    /**
     * @throws \InvalidArgumentException
     */
    public function create(AlertRequestDTO $dto): Alert
    {
        $user = $this->userRepo->find($dto->userId);
        if (!$user) {
            throw new \InvalidArgumentException('Utilisateur introuvable');
        }
        $order = $this->orderRepo->find($dto->orderId);
        if (!$order) {
            throw new \InvalidArgumentException('Commande introuvable');
        }
        $alert = new Alert();
        $alert->setUser($user)
            ->setOrder($order)
            ->setType($dto->type)
            ->setMessage($dto->message)
            ->setPosition($dto->position)
            ->setTimestamp(new \DateTimeImmutable());
        $this->em->persist($alert);
        $this->em->flush();
        return $alert;
    }
}
