<?php

namespace App\Service;

use App\DTO\Client\Response\ClientWithOrdersDTO;
use App\DTO\User\Internal\UserDTO;
use App\Entity\User;
use App\Repository\ServiceOrdersRepository;
use App\Enum\EntityType;

class ClientOrderService
{
    public function __construct(
        private ServiceOrdersRepository $serviceOrdersRepository,
        private ServiceOrderService $serviceOrderService,
        private CryptService $cryptService
    ) {}

    /**
     * Récupérer un client avec ses commandes associées
     */
    public function getClientWithOrders(User $client): ClientWithOrdersDTO
    {
        // Récupérer les commandes du client
        $serviceOrders = $this->serviceOrdersRepository->findBy(
            ['client' => $client], 
            ['createdAt' => 'DESC']
        );

        // Convertir les commandes en DTOs
        $serviceOrderDTOs = [];
        foreach ($serviceOrders as $serviceOrder) {
            $serviceOrderDTOs[] = $this->serviceOrderService->toDTO($serviceOrder);
        }

        // Créer le UserDTO de base
        $userDTO = new UserDTO(
            userId: $this->cryptService->encryptId((string)$client->getId(), EntityType::USER->value),
            email: $client->getEmail(),
            name: $client->getName(),
            role: $client->getRole(),
            phone: $client->getPhone()
        );

        // Retourner le DTO combiné
        return new ClientWithOrdersDTO(
            client: $userDTO,
            serviceOrders: $serviceOrderDTOs
        );
    }
}
