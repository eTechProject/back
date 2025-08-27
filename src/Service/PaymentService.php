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

    /**
     * Retourne la réponse complète pour le contrôleur client, avec mapping et historique, selon SOLID
     */
    public function getClientPaymentsResponse($user, int $clientId, int $page, int $limit, $statusFilter, $startDateFilter, $endDateFilter, CryptService $cryptService): array
    {
        [$payments, $total] = $this->getPaymentsByClientPaginated($user, $page, $limit, $statusFilter, $startDateFilter, $endDateFilter);

        $activePayments = [];
        $expiredPayments = [];
        $otherPayments = [];
        foreach ($payments as $payment) {
            $pack = $payment->getPack();
            $mapped = [
                'id' => $cryptService->encryptId((string)$payment->getId(), EntityType::PAYMENT->value),
                'pack_id' => (null !== $pack) ? $cryptService->encryptId((string)$pack->getId(), EntityType::PACK->value) : null,
                'status' => $payment->getStatus()->value,
                'createdAt' => $payment->getCreatedAt(),
                'startDate' => $payment->getStartDate(),
                'endDate' => $payment->getEndDate(),
                'amount' => method_exists($payment, 'getAmount') ? $payment->getAmount() : null,
                'pack' => [
                    'id' => (null !== $pack) ? $cryptService->encryptId((string)$pack->getId(), EntityType::PACK->value) : null,
                    'name' => method_exists($pack, 'getName') ? $pack->getName() : null,
                    'nb_agents' => method_exists($pack, 'getNbAgents') ? $pack->getNbAgents() : null,
                    'price' => method_exists($pack, 'getPrice') ? $pack->getPrice() : null,
                    'description' => method_exists($pack, 'getDescription') ? $pack->getDescription() : null,
                ],
                'subscription_status' => $payment->getStatus()->value,
                'subscription_start' => $payment->getStartDate(),
                'subscription_end' => $payment->getEndDate(),
            ];
            if ($payment->getStatus() === \App\Enum\PaymentStatus::ACTIF) {
                $activePayments[] = $mapped;
            } elseif ($payment->getStatus() === \App\Enum\PaymentStatus::EXPIRE) {
                $expiredPayments[] = $mapped;
            } else {
                $otherPayments[] = $mapped;
            }
        }

        // Historique complet
        $result = $this->getClientPaymentsWithHistory($clientId);
        $history = array_map(function ($h) use ($cryptService) {
            return [
                'id' => $cryptService->encryptId((string)$h->getId(), EntityType::PAYMENT_HISTORY->value),
                'payment_id' => $cryptService->encryptId((string)$h->getPayment()->getId(), EntityType::PAYMENT->value),
                'amount' => method_exists($h, 'getAmount') ? $h->getAmount() : null,
                'status' => method_exists($h, 'getStatus') ? $h->getStatus() : null,
                'createdAt' => method_exists($h, 'getCreatedAt') ? $h->getCreatedAt() : null,
                'provider' => method_exists($h, 'getProvider') ? $h->getProvider() : null,
                'provider_response' => method_exists($h, 'getProviderResponse') ? $h->getProviderResponse() : null,
            ];
        }, $result['history']);

        return [
            'history' => $history,
            'active_payments' => $activePayments,
            'expired_payments' => $expiredPayments,
            'other_payments' => $otherPayments,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ];
    }

    /**
     * Retourne les paiements et l'historique d'abonnement d'un client
     */
    public function getClientPaymentsWithHistory(int $clientId): array
    {
        // Récupère les paiements du client
        $payments = $this->paymentRepository->createQueryBuilder('p')
            ->andWhere('p.client = :clientId')
            ->setParameter('clientId', $clientId)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()->getResult();

        // Récupère l'historique des paiements du client
        $history = $this->paymentHistoryRepository->createQueryBuilder('h')
            ->join('h.payment', 'p')
            ->andWhere('p.client = :clientId')
            ->setParameter('clientId', $clientId)
            ->orderBy('h.createdAt', 'DESC')
            ->getQuery()->getResult();

        return [
            'payments' => $payments,
            'history' => $history
        ];
    }
}
