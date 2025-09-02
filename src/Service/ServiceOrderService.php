<?php

namespace App\Service;

use App\DTO\ServiceOrder\Request\CreateServiceOrderDTO;
use App\DTO\ServiceOrder\Response\ServiceOrderDTO;
use App\DTO\SecuredZone\CreateSecuredZoneDTO;
use App\Entity\ServiceOrders;
use App\Entity\User;
use App\Enum\Status;
use App\Enum\EntityType;
use App\Repository\ServiceOrdersRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Agents;
use App\Entity\Tasks;
use App\Enum\NotificationTarget;
use App\Enum\NotificationType;
use App\Service\Notification\NotificationService;

class ServiceOrderService
{
    public function __construct(
        private ServiceOrdersRepository $serviceOrdersRepository,
        private UserRepository $userRepository,
        private SecuredZoneService $securedZoneService,
        private CryptService $cryptService,
        private EntityManagerInterface $entityManager,
        private NotificationService $notificationService
    ) {}

    public function createServiceOrderFromRequest(CreateServiceOrderDTO $request): ServiceOrders
    {
        // Decrypt and find the client
        $clientId = $this->cryptService->decryptId($request->clientId, EntityType::USER->value);
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
            serviceOrderId: $this->cryptService->encryptId($serviceOrder->getId(), EntityType::SERVICE_ORDER->value),
            description: $serviceOrder->getDescription(),
            status: $serviceOrder->getStatus(),
            createdAt: $serviceOrder->getCreatedAt(),
            securedZone: $securedZoneDTO,
            clientId: $this->cryptService->encryptId($serviceOrder->getClient()->getId(), EntityType::USER->value),
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

    public function findLastInProgressByClientId(int $clientId): ?ServiceOrders
    {
        return $this->serviceOrdersRepository->findOneBy(
            ['client' => $clientId, 'status' => Status::IN_PROGRESS],
            ['createdAt' => 'DESC']
        );
    }
    /**
     * Get all agents related to an order with tasks in pending or in_progress status
     *
     * @param ServiceOrders $order
     * @return array<Agents>
     */
    public function getRelatedAgentsWithActiveTasks(ServiceOrders $order): array
    {
        $qb = $this->entityManager->createQueryBuilder();

        return $qb->select('a')
            ->from(Agents::class, 'a')
            ->innerJoin(Tasks::class, 't', 'WITH', 't.agent = a')
            ->where('t.order = :order')
            ->andWhere('t.status IN (:statuses)')
            ->setParameter('order', $order)
            ->setParameter('statuses', [Status::PENDING, Status::IN_PROGRESS])
            ->getQuery()
            ->getResult();
    }
    /**
     * Send notification to all agents related to an order with active tasks
     *
     * @param ServiceOrders $order
     * @param string $title
     * @param string $message
     * @param NotificationType $type
     */
    public function notifyRelatedAgents(ServiceOrders $order, string $title, string $message, NotificationType $type): void
    {
        $agents = $this->getRelatedAgentsWithActiveTasks($order);
        
        foreach ($agents as $agent) {
            $this->notificationService->createNotification(
                $title,
                $message,
                $type,
                NotificationTarget::AGENT,
                $agent->getUser()
            );
        }
    }
}
