<?php

namespace App\Controller\Payment;

use App\Repository\PaymentRepository;
use App\Repository\PaymentHistoryRepository;
use App\Service\CryptService;
use App\Enum\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/api/admin/payments/{encryptedId}', name: 'api_admin_payment_get', methods: ['GET'])]
class GetByIdController extends AbstractController
{
    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        private readonly PaymentHistoryRepository $paymentHistoryRepository,
        private readonly ?CryptService $cryptService = null
    ) {}

    public function __invoke(string $encryptedId): JsonResponse
    {
        if ($this->cryptService === null) {
            return $this->json(['success' => false, 'message' => 'Crypt service not configured'], 500);
        }

        try {
            $id = $this->cryptService->decryptId($encryptedId, EntityType::PAYMENT->value);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Invalid ID'], 400);
        }

        $payment = $this->paymentRepository->find($id);
        if (!$payment) {
            return $this->json(['success' => false, 'message' => 'Payment not found'], 404);
        }

        $histories = $this->paymentHistoryRepository->findByPayment($payment->getId());

        $historyData = array_map(fn($h) => [
            'id' => $this->cryptService->encryptId((string)$h->getId(), EntityType::PAYMENT_HISTORY->value),
            'amount' => $h->getAmountAsFloat(),
            'status' => $h->getStatus()->value,
            'provider' => $h->getProvider(),
            'providerResponse' => $h->getProviderResponse(),
            'date' => $h->getDate()->format(DATE_ATOM),
            'createdAt' => $h->getCreatedAt()->format(DATE_ATOM),
        ], $histories);

        $data = [
            'id' => $this->cryptService->encryptId((string)$payment->getId(), EntityType::PAYMENT->value),
            'client' => $this->cryptService->encryptId((string)$payment->getClient()->getId(), EntityType::USER->value),
            'pack' => $this->cryptService->encryptId((string)$payment->getPack()->getId(), EntityType::PACK->value),
            'status' => $payment->getStatus()->value,
            'startDate' => $payment->getStartDate()->format(DATE_ATOM),
            'endDate' => $payment->getEndDate()?->format(DATE_ATOM),
            'createdAt' => $payment->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $payment->getUpdatedAt()->format(DATE_ATOM),
            'histories' => $historyData,
        ];

        return $this->json(['success' => true, 'data' => $data]);
    }
}
