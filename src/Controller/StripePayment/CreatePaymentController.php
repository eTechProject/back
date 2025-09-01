<?php

namespace App\Controller\StripePayment;

use App\DTO\Payment\StripePayment\Request\CreateStripePaymentDTO;
use App\DTO\Payment\StripePayment\Response\StripePaymentResponseDTO;
use App\DTO\Payment\StripePayment\Internal\StripePaymentMethodDTO;
use App\Repository\StripePaymentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/client/stripe-payment/create', name: 'api_stripe_payment_create', methods: ['POST'])]
class CreatePaymentController extends AbstractController
{
    public function __construct(
        private StripePaymentRepository $stripePaymentRepository,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $user = $this->getUser();
            $dto = $this->serializer->deserialize(
                $request->getContent(),
                CreateStripePaymentDTO::class,
                'json'
            );

            $violations = $this->validator->validate($dto);
            if (count($violations) > 0) {
                $errors = [];
                foreach ($violations as $violation) {
                    $errors[$violation->getPropertyPath()] = $violation->getMessage();
                }

                return new JsonResponse([
                    'success' => false,
                    'errors' => $errors
                ], Response::HTTP_BAD_REQUEST);
            }

            $paymentData = [
                'amount' => $dto->amount,
                'currency' => $dto->currency,
                'cardNumber' => $dto->cardNumber,
                'expiryMonth' => sprintf('%02d', $dto->expiryMonth),
                'expiryYear' => $dto->expiryYear,
                'cvv' => $dto->cvv,
                'customerEmail' => $dto->customerEmail ?? 'test@example.com',
                'description' => $dto->description ?? 'Test payment via Stripe'
            ];

            $responseDto = $this->stripePaymentRepository->createPayment($user, $dto->packId, $paymentData);

            return new JsonResponse([
                'success' => true,
                'data' => $responseDto,
                'timestamp' => time()
            ], Response::HTTP_CREATED);

        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => time()
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => time()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
