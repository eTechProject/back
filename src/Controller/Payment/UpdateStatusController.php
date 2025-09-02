<?php

namespace App\Controller\Payment;

use App\Repository\PaymentRepository;
use App\Service\CryptService;
use App\Enum\EntityType;
use App\Enum\PaymentStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/api/admin/payment/{encryptedId}/status', name: 'api_admin_payment_update_status', methods: ['PUT'])]
class UpdateStatusController extends AbstractController
{
    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        private readonly EntityManagerInterface $em,
        private readonly ?CryptService $cryptService = null
    ) {}

    public function __invoke(Request $request, string $encryptedId): JsonResponse
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

        $data = json_decode($request->getContent() ?: '{}', true);
        $newStatus = $data['status'] ?? null;
        if (!is_string($newStatus)) {
            return $this->json(['success' => false, 'message' => 'Missing or invalid status'], 400);
        }

        // Accept either enum name (e.g. ACTIF) or enum value (e.g. 'actif'), case-insensitive
        $statusEnum = null;
        foreach (PaymentStatus::cases() as $case) {
            if (strcasecmp($case->name, $newStatus) === 0 || strcasecmp($case->value, $newStatus) === 0) {
                $statusEnum = $case;
                break;
            }
        }

        if ($statusEnum === null) {
            return $this->json(['success' => false, 'message' => 'Unknown status'], 400);
        }

        $payment->setStatus($statusEnum);
        $this->em->persist($payment);
        $this->em->flush();

        $response = [
            'id' => $this->cryptService->encryptId((string)$payment->getId(), EntityType::PAYMENT->value),
            'status' => $payment->getStatus()->value,
            'updatedAt' => $payment->getUpdatedAt()?->format(DATE_ATOM),
        ];

        return $this->json(['success' => true, 'data' => $response]);
    }
}
