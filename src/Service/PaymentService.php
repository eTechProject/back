<?php

namespace App\Service;

use App\DTO\Payment\CreatePaymentDTO;
use App\DTO\Payment\PaymentResponseDTO;
use App\DTO\Payment\PackResponseDTO;
use App\Entity\Payment;
use App\Entity\PaymentHistory;
use App\Enum\PaymentStatus;
use App\Enum\PaymentHistoryStatus;
use App\Repository\PaymentRepository;
use App\Repository\PaymentHistoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\CryptService;
use App\Enum\EntityType;

class PaymentService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PaymentRepository $paymentRepository,
        private readonly PaymentHistoryRepository $paymentHistoryRepository,
        private readonly CybersourceClient $cybersourceClient,
        private readonly CryptService $cryptService
    ) {}

    /**
     * Initialise un paiement via Cybersource et enregistre la payment/payment_history initiale
     * retourne un tableau contenant les informations nécessaires côté client (ex: redirect url / token)
     */
    public function initiatePayment($user, CreatePaymentDTO $dto): array
    {
        // 1. Validate pack and client
        $client = $user;

        // Support encrypted packId (string) or numeric id
        $rawPackId = $dto->packId;
        if (is_string($rawPackId) && !ctype_digit($rawPackId)) {
            try {
                $packId = (int) $this->cryptService->decryptId($rawPackId, EntityType::PACK->value);
            } catch (\Exception) {
                throw new \InvalidArgumentException('Pack ID invalide');
            }
        } else {
            $packId = (int) $rawPackId;
        }

        $pack = $this->em->getRepository(\App\Entity\Pack::class)->find($packId);
        if (!$pack) throw new \InvalidArgumentException('Pack introuvable');

        $payment=$this->paymentRepository->findByClient($client->getId())[0] ?? null;
        if(!$payment){
            // 2. Create Payment entity
            $payment = new Payment();
            $payment->setClient($client);
            $payment->setPack($pack);
            $payment->setStatus(PaymentStatus::ACTIF);
            $this->em->persist($payment);
        }

        // 3. Create PaymentHistory initial entry
        $history = new PaymentHistory();
        $history->setPayment($payment);
        $history->setAmount($dto->amount ?? 0.0);
        $history->setStatus(PaymentHistoryStatus::SUCCESS);
        $history->setProvider('stripe');
        $history->setStripePaymentId($dto->stripePaymentId);
        $this->em->persist($history);

        $this->em->flush();

        
        return [
            'paymentId' => $this->cryptService->encryptId((string)$payment->getId(), EntityType::PAYMENT->value),
            'historyId' => $this->cryptService->encryptId((string)$history->getId(), EntityType::PAYMENT_HISTORY->value),
            'provider' => 'stripe'
        ];
    }

    public function getPaymentsPaginated(int $page, int $limit): array
    {
        $qb = $this->paymentRepository->createQueryBuilder('p')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->orderBy('p.createdAt', 'DESC');

        $payments = $qb->getQuery()->getResult();

        $countQb = $this->paymentRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)');
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        return [$payments, $total];
    }

    public function getPaymentsByClientPaginated($user, int $page, int $limit): array
    {
        $qb = $this->paymentRepository->createQueryBuilder('p')
            ->andWhere('p.client = :client')
            ->setParameter('client', $user)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->orderBy('p.createdAt', 'DESC');

        $payments = $qb->getQuery()->getResult();

        $countQb = $this->paymentRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.client = :client')
            ->setParameter('client', $user);
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        return [$payments, $total];
    }

    /**
     * Get all payments for a specific client
     */
    public function getPaymentsByClient(int $clientId): array
    {
        return $this->paymentRepository->findByClient($clientId);
    }

    /**
     * Converts a Payment entity to PaymentResponseDTO
     */
    public function convertToDTO(Payment $payment): PaymentResponseDTO
    {
        $dto = new PaymentResponseDTO();
        $dto->id = $this->cryptService->encryptId($payment->getId(), EntityType::PAYMENT->value);
        $dto->status = $payment->getStatus()->value;
        $dto->startDate = $payment->getStartDate()->format('Y-m-d H:i:s');
        $dto->endDate = $payment->getEndDate()?->format('Y-m-d H:i:s');
        $dto->createdAt = $payment->getCreatedAt()->format('Y-m-d H:i:s');
        $dto->updatedAt = $payment->getUpdatedAt()->format('Y-m-d H:i:s');

        // Convert pack to DTO
        $packDto = new PackResponseDTO();
        $packDto->id = $this->cryptService->encryptId($payment->getPack()->getId(), EntityType::PACK->value);
        $packDto->description = $payment->getPack()->getDescription();
        $packDto->nbAgents = $payment->getPack()->getNbAgents();
        $packDto->price = $payment->getPack()->getPrix();
        
        $dto->pack = $packDto;

        return $dto;
    }

    /**
     * Converts an array of Payment entities to an array of PaymentResponseDTOs
     */
    public function convertArrayToDTO(array $payments): array
    {
        return array_map(fn(Payment $payment) => $this->convertToDTO($payment), $payments);
    }
}
