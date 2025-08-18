<?php

namespace App\Controller\Payment;

use App\DTO\Payment\CreatePaymentDTO;
use App\Service\PaymentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_CLIENT')]
#[Route('/api/payments', name: 'api_payments_create', methods: ['POST'])]
class CreateController extends AbstractController
{
    public function __construct(
        private PaymentService $paymentService,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $dto = $this->serializer->deserialize(
                $request->getContent(),
                CreatePaymentDTO::class,
                'json'
            );

            $violations = $this->validator->validate($dto);
            if (count($violations) > 0) {
                $errors = [];
                foreach ($violations as $v) {
                    $errors[$v->getPropertyPath()] = $v->getMessage();
                }

                return new JsonResponse(['success' => false, 'errors' => $errors], Response::HTTP_BAD_REQUEST);
            }

            $result = $this->paymentService->initiatePayment($this->getUser(), $dto);

            return new JsonResponse(['success' => true, 'data' => $result], Response::HTTP_CREATED);

        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Erreur lors de l\'initiation du paiement'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
