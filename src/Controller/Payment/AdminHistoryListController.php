<?php

namespace App\Controller\Payment;

use App\Repository\PaymentHistoryRepository;
use App\Service\CryptService;
use App\Enum\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/api/admin/payment-history', name: 'api_admin_payment_history_list', methods: ['GET'])]
class AdminHistoryListController extends AbstractController
{
    public function __construct(
        private readonly PaymentHistoryRepository $paymentHistoryRepository,
        private readonly ?CryptService $cryptService = null
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        if ($this->cryptService === null) {
            return $this->json(['success' => false, 'message' => 'Crypt service not configured'], 500);
        }

        try {
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = max(1, min(100, (int) $request->query->get('limit', 20)));

            // Get paginated results
            $qb = $this->paymentHistoryRepository->createQueryBuilder('h')
                ->setFirstResult(($page - 1) * $limit)
                ->setMaxResults($limit)
                ->orderBy('h.date', 'DESC');

            $histories = $qb->getQuery()->getResult();

            // Get total count
            $countQb = $this->paymentHistoryRepository->createQueryBuilder('h')
                ->select('COUNT(h.id)');
            $total = (int) $countQb->getQuery()->getSingleScalarResult();

            $data = array_map(function ($h) {
                return [
                    'id' => $this->cryptService->encryptId((string)$h->getId(), EntityType::PAYMENT_HISTORY->value),
                    'paymentId' => $this->cryptService->encryptId((string)$h->getPayment()->getId(), EntityType::PAYMENT->value),
                    'clientId' => $this->cryptService->encryptId((string)$h->getPayment()->getClient()->getId(), EntityType::USER->value),
                    'packId' => $this->cryptService->encryptId((string)$h->getPayment()->getPack()->getId(), EntityType::PACK->value),
                    'amount' => $h->getAmountAsFloat(),
                    'status' => $h->getStatus()->value,
                    'provider' => $h->getProvider(),
                    'providerResponse' => $h->getProviderResponse(),
                    'date' => $h->getDate()->format(DATE_ATOM),
                    'createdAt' => $h->getCreatedAt()->format(DATE_ATOM),
                ];
            }, $histories);

            $pages = (int) ceil($total / $limit);

            return $this->json([
                'success' => true,
                'data' => $data,
                'total' => $total,
                'page' => $page,
                'pages' => $pages,
                'limit' => $limit,
            ]);

        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
