<?php

namespace App\Service;

use App\DTO\Payment\CreatePaymentDTO;
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

        // 2. Create Payment entity
        $payment = new Payment();
        $payment->setClient($client);
        $payment->setPack($pack);
        $payment->setStatus(PaymentStatus::NON_PAYE);
        $this->em->persist($payment);

        // 3. Create PaymentHistory initial entry
        $history = new PaymentHistory();
        $history->setPayment($payment);
        $history->setAmount($dto->amount ?? 0.0);
        $history->setStatus(PaymentHistoryStatus::PENDING);
        $history->setProvider('cybersource');
        $this->em->persist($history);

        $this->em->flush();

        // 4. Call Cybersource to create a payment session (client-token / redirect url)
        $cybersourcePayload = [
            'amount' => $history->getAmount(),
            'currency' => $dto->currency ?? 'EUR',
            'reference' => 'payment_'.$payment->getId(),
            'customer' => [
                'id' => $client->getId(),
                'email' => $client->getEmail()
            ]
        ];

        $csResponse = $this->cybersourceClient->createPaymentSession($cybersourcePayload);

        // 5. Update history with provider response token/url if any
        $history->setProviderResponse($csResponse['providerResponse'] ?? null);
        $this->em->flush();

        return [
            'paymentId' => $this->cryptService->encryptId((string)$payment->getId(), EntityType::PAYMENT->value),
            'historyId' => $this->cryptService->encryptId((string)$history->getId(), EntityType::PAYMENT_HISTORY->value),
            'provider' => 'cybersource',
            'session' => $csResponse
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
}
