<?php

namespace App\Service;

use App\DTO\ServiceOrder\CreateServiceOrderDTO;
use App\DTO\ServiceOrder\ServiceOrderDTO;
use App\DTO\SecuredZone\CreateSecuredZoneDTO;
use App\Entity\ServiceOrders;
use App\Entity\User;
use App\Enum\Status;
use App\Repository\ServiceOrdersRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class ServiceOrderService
{
    public function __construct(
        private ServiceOrdersRepository $serviceOrdersRepository,
        private UserRepository $userRepository,
        private SecuredZoneService $securedZoneService,
        private CryptService $cryptService,
        private EntityManagerInterface $entityManager
    ) {}

    public function createServiceOrderFromRequest(CreateServiceOrderDTO $request): ServiceOrders
    {
        // Decrypt and find the client
        $clientId = $this->cryptService->decryptId($request->clientId);
        $client = $this->userRepository->find($clientId);
        
        if (!$client) {
            throw new \InvalidArgumentException('Client non trouvé');
        }

        // Create the secured zone using the existing DTO directly
        $securedZone = $this->securedZoneService->createSecuredZoneFromRequest($request->securedZone);

        // Create the service order
        $serviceOrder = new ServiceOrders();
        $serviceOrder->setDescription($request->description);
        
        // Set status to IN_PROGRESS automatically
        $serviceOrder->setStatus(Status::IN_PROGRESS);
        
        $serviceOrder->setClient($client);
        $serviceOrder->setSecuredZone($securedZone);

        return $serviceOrder;
    }

    public function createServiceOrderWithTransaction(CreateServiceOrderDTO $request): ServiceOrders
    {
        $this->entityManager->beginTransaction();

        try {
            $serviceOrder = $this->createServiceOrderFromRequest($request);
            
            // Persist the secured zone first
            $this->entityManager->persist($serviceOrder->getSecuredZone());
            $this->entityManager->flush(); // Flush to get the secured zone ID

            // Then persist the service order
            $this->entityManager->persist($serviceOrder);
            $this->entityManager->flush();

            $this->entityManager->commit();

            return $serviceOrder;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    public function toDTO(ServiceOrders $serviceOrder): ServiceOrderDTO
    {
        $securedZoneDTO = $this->securedZoneService->toDTO($serviceOrder->getSecuredZone());
        
        return new ServiceOrderDTO(
            encryptedId: $this->cryptService->encryptId($serviceOrder->getId()),
            description: $serviceOrder->getDescription(),
            status: $serviceOrder->getStatus(),
            createdAt: $serviceOrder->getCreatedAt(),
            securedZone: $securedZoneDTO,
            clientId: $this->cryptService->encryptId($serviceOrder->getClient()->getId()),
            clientName: $serviceOrder->getClient()->getName()
        );
    }

    public function findById(int $id): ?ServiceOrders
    {
        return $this->serviceOrdersRepository->find($id);
    }

    public function findAll(): array
    {
        return $this->serviceOrdersRepository->findAll();
    }

    public function findByClientId(int $clientId): array
    {
        return $this->serviceOrdersRepository->findBy(['client' => $clientId]);
    }
}
