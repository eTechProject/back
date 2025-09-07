<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\PaymentHistory;
use App\Repository\PaymentHistoryRepository;
use App\Service\CryptService;
use App\Enum\EntityType;
use Stripe\Stripe;
use Stripe\Charge;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ClientPaymentHistoryService
{
    public function __construct(
        private PaymentHistoryRepository $paymentHistoryRepository,
        private CryptService $cryptService,
        private ParameterBagInterface $parameterBag
    ) {
        // Configuration Stripe avec la clé secrète
        Stripe::setApiKey($this->parameterBag->get('stripe.secret_key'));
    }

    /**
     * Récupère l'historique des paiements généraux pour un client
     */
    public function getClientPaymentHistory(
        User $user, 
        int $page = 1, 
        int $limit = 10, 
        ?string $statusFilter = null,
        ?string $providerFilter = null,
        ?string $startDateFilter = null,
        ?string $endDateFilter = null
    ): array {
        $offset = ($page - 1) * $limit;

        $queryBuilder = $this->paymentHistoryRepository->createQueryBuilder('ph')
            ->leftJoin('ph.payment', 'p')
            ->leftJoin('p.client', 'c')
            ->where('c.id = :userId')
            ->setParameter('userId', $user->getId())
            ->orderBy('ph.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        // Filtre par statut
        if ($statusFilter) {
            $queryBuilder->andWhere('ph.status = :status')
                ->setParameter('status', $statusFilter);
        }

        // Filtre par provider
        if ($providerFilter) {
            $queryBuilder->andWhere('ph.provider = :provider')
                ->setParameter('provider', $providerFilter);
        }

        // Filtre par date de début
        if ($startDateFilter) {
            $startDate = new \DateTime($startDateFilter);
            $queryBuilder->andWhere('ph.createdAt >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        // Filtre par date de fin
        if ($endDateFilter) {
            $endDate = new \DateTime($endDateFilter);
            $endDate->setTime(23, 59, 59); // Fin de journée
            $queryBuilder->andWhere('ph.createdAt <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        $paymentHistories = $queryBuilder->getQuery()->getResult();

        // Récupérer le total pour la pagination
        $totalQueryBuilder = $this->paymentHistoryRepository->createQueryBuilder('ph')
            ->select('COUNT(ph.id)')
            ->leftJoin('ph.payment', 'p')
            ->leftJoin('p.client', 'c')
            ->where('c.id = :userId')
            ->setParameter('userId', $user->getId());

        if ($statusFilter) {
            $totalQueryBuilder->andWhere('ph.status = :status')
                ->setParameter('status', $statusFilter);
        }

        if ($providerFilter) {
            $totalQueryBuilder->andWhere('ph.provider = :provider')
                ->setParameter('provider', $providerFilter);
        }

        if ($startDateFilter) {
            $startDate = new \DateTime($startDateFilter);
            $totalQueryBuilder->andWhere('ph.createdAt >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endDateFilter) {
            $endDate = new \DateTime($endDateFilter);
            $endDate->setTime(23, 59, 59);
            $totalQueryBuilder->andWhere('ph.createdAt <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        $total = $totalQueryBuilder->getQuery()->getSingleScalarResult();

        // Formater les données
        $formattedHistory = [];
        foreach ($paymentHistories as $paymentHistory) {
            $formattedHistory[] = $this->formatPaymentHistoryData($paymentHistory);
        }

        return [
            'history' => $formattedHistory,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int)$total,
                'total_pages' => ceil($total / $limit),
                'has_next' => ($page * $limit) < $total,
                'has_prev' => $page > 1
            ],
            'filters' => [
                'status' => $statusFilter,
                'provider' => $providerFilter,
                'start_date' => $startDateFilter,
                'end_date' => $endDateFilter
            ]
        ];
    }

    /**
     * Récupère un paiement spécifique de l'historique pour un client
     */
    public function getClientPaymentHistoryById(User $user, int $paymentHistoryId): ?array
    {
        $paymentHistory = $this->paymentHistoryRepository->createQueryBuilder('ph')
            ->leftJoin('ph.payment', 'p')
            ->leftJoin('p.client', 'c')
            ->where('ph.id = :id')
            ->andWhere('c.id = :userId')
            ->setParameter('id', $paymentHistoryId)
            ->setParameter('userId', $user->getId())
            ->getQuery()
            ->getOneOrNullResult();

        if (!$paymentHistory) {
            return null;
        }

        return $this->formatPaymentHistoryData($paymentHistory);
    }

    /**
     * Récupère un paiement spécifique de l'historique pour un client par ID de paiement
     */
    public function getClientPaymentHistoryByPaymentId(User $user, int $paymentId): ?array
    {
        $paymentHistories = $this->paymentHistoryRepository->createQueryBuilder('ph')
            ->leftJoin('ph.payment', 'p')
            ->leftJoin('p.client', 'c')
            ->where('p.id = :paymentId')
            ->andWhere('c.id = :userId')
            ->setParameter('paymentId', $paymentId)
            ->setParameter('userId', $user->getId())
            ->orderBy('ph.createdAt', 'DESC') // Le plus récent en premier
            ->getQuery()
            ->getResult();

        if (empty($paymentHistories)) {
            return null;
        }

        // Retourner le premier (le plus récent) ou chercher celui avec status 'success'
        $successPayment = null;
        foreach ($paymentHistories as $paymentHistory) {
            if ($paymentHistory->getStatus()->value === 'success') {
                $successPayment = $paymentHistory;
                break;
            }
        }

        // Si on trouve un paiement réussi, on le retourne, sinon le plus récent
        $selectedPayment = $successPayment ?? $paymentHistories[0];

        return $this->formatPaymentHistoryData($selectedPayment);
    }

    /**
     * Récupère tout l'historique des paiements pour un ID de paiement spécifique
     */
    public function getAllClientPaymentHistoryByPaymentId(User $user, int $paymentId): array
    {
        $paymentHistories = $this->paymentHistoryRepository->createQueryBuilder('ph')
            ->leftJoin('ph.payment', 'p')
            ->leftJoin('p.client', 'c')
            ->where('p.id = :paymentId')
            ->andWhere('c.id = :userId')
            ->setParameter('paymentId', $paymentId)
            ->setParameter('userId', $user->getId())
            ->orderBy('ph.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($paymentHistories as $paymentHistory) {
            $result[] = $this->formatPaymentHistoryData($paymentHistory);
        }

        return $result;
    }

    /**
     * Formate les données d'un PaymentHistory
     */
    private function formatPaymentHistoryData(PaymentHistory $paymentHistory): array
    {
        $payment = $paymentHistory->getPayment();
        $pack = $payment->getPack();
        
        $data = [
            'id' => $this->cryptService->encryptId($paymentHistory->getId(), EntityType::PAYMENT_HISTORY->value),
            'payment_id' => $this->cryptService->encryptId($payment->getId(), EntityType::PAYMENT->value),
            'amount' => (float)$paymentHistory->getAmount(),
            'currency' => 'EUR', // Valeur par défaut, vous pouvez l'ajuster selon vos besoins
            'status' => $paymentHistory->getStatus()->value,
            'provider' => $paymentHistory->getProvider(),
            'description' => $pack ? $pack->getDescription() : 'Paiement',
            'date' => $paymentHistory->getDate()->format('Y-m-d H:i:s'),
            'created_at' => $paymentHistory->getCreatedAt()->format('Y-m-d H:i:s'),
            'stripe_payment_id' => $paymentHistory->getStripePaymentId(),
            'provider_response' => $paymentHistory->getProviderResponse(),
            'pack_info' => $pack ? [
                'id' => $this->cryptService->encryptId($pack->getId(), EntityType::PACK->value),
                'name' => $pack->getDescription(), // Utiliser description comme nom
                'price' => (float)$pack->getPrix(),
                'nb_agents' => $pack->getNbAgents()
            ] : null
        ];

        // Ajouter la facture Stripe si c'est un paiement Stripe et qu'on a l'ID
        if ($paymentHistory->getProvider() === 'stripe' && $paymentHistory->getStripePaymentId()) {
            try {
                $stripeInvoice = $this->generateStripeInvoiceFromPaymentHistory($paymentHistory);
                $data['invoice'] = $stripeInvoice;
            } catch (\Exception $e) {
                // En cas d'erreur, on continue sans la facture
                $data['invoice'] = null;
            }
        } else {
            $data['invoice'] = null;
        }

        return $data;
    }

    /**
     * Génère une facture à partir des données de PaymentHistory pour un paiement Stripe
     */
    private function generateStripeInvoiceFromPaymentHistory(PaymentHistory $paymentHistory): array
    {
        $payment = $paymentHistory->getPayment();
        $pack = $payment->getPack();
        $amount = (float)$paymentHistory->getAmount();
        $receiptUrl = null;

        // Récupérer l'URL de reçu depuis Stripe
        try {
            $charge = Charge::retrieve($paymentHistory->getStripePaymentId());
            $receiptUrl = $charge->receipt_url;
        } catch (ApiErrorException $e) {
            // En cas d'erreur, on continue sans l'URL de reçu
            $receiptUrl = null;
        }

        return [
            'invoice_number' => 'STRIPE-' . strtoupper(substr($paymentHistory->getStripePaymentId(), -8)),
            'invoice_date' => $paymentHistory->getDate()->format('Y-m-d'),
            'due_date' => $paymentHistory->getDate()->format('Y-m-d'), // Paiement immédiat
            'amount' => $amount,
            'currency' => 'EUR',
            'status' => $paymentHistory->getStatus()->value,
            'customer' => [
                'email' => $payment->getClient()->getEmail(),
                'name' => $payment->getClient()->getName(),
            ],
            'items' => [[
                'description' => $pack ? $pack->getDescription() : 'Paiement Stripe',
                'quantity' => 1,
                'unit_price' => $amount,
                'total_price' => $amount
            ]],
            'subtotal' => $amount,
            'tax_rate' => 0.20, // 20% TVA
            'tax_amount' => $amount * 0.20,
            'total' => $amount * 1.20,
            'payment_method' => 'Stripe',
            'transaction_id' => $paymentHistory->getStripePaymentId(),
            'receipt_url' => $receiptUrl // URL de reçu Stripe
        ];
    }
}
