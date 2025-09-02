<?php

namespace App\Controller\Payment;

use App\Service\PaymentService;
use App\Service\CryptService;
use App\Enum\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/api/admin/payments', name: 'api_payments_list', methods: ['GET'])]
class ListController extends AbstractController
{
    public function __construct(private PaymentService $paymentService, private CryptService $cryptService) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = max(1, min(100, (int) $request->query->get('limit', 20)));

            // Admin endpoint: get ALL payments, not just user's payments
            [$payments, $total] = $this->paymentService->getPaymentsPaginated($page, $limit);

            // Debug: check if we have payments
            if (empty($payments)) {
                return $this->json([
                    'success' => true,
                    'data' => [],
                    'total' => $total,
                    'page' => $page,
                    'pages' => 0,
                    'limit' => $limit,
                    'debug' => 'No payments found'
                ]);
            }

            $data = array_map(fn($p) => [
                'id' => $this->cryptService->encryptId($p->getId(), EntityType::PAYMENT->value),
                'client' => $this->cryptService->encryptId($p->getClient()->getId(), EntityType::USER->value),
                'pack' => $this->cryptService->encryptId($p->getPack()->getId(), EntityType::PACK->value),
                'status' => $p->getStatus()->value,
                'startDate' => $p->getStartDate()->format(DATE_ATOM),
                'endDate' => $p->getEndDate()?->format(DATE_ATOM),
            ], $payments);

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
