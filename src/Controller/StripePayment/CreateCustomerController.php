<?php

namespace App\Controller\StripePayment;

use App\DTO\Payment\StripePayment\Request\CreateStripeCustomerDTO;
use App\DTO\Payment\StripePayment\Response\StripeCustomerResponseDTO;
use App\Repository\StripePaymentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/stripe-payment/customer/create', name: 'api_stripe_customer_create', methods: ['POST'])]
class CreateCustomerController extends AbstractController
{
    public function __construct(
        private StripePaymentRepository $stripePaymentRepository,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $dto = $this->serializer->deserialize(
                $request->getContent(),
                CreateStripeCustomerDTO::class,
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

            $customerData = [
                'email' => $dto->email,
                'name' => $dto->name,
                'phone' => $dto->phone
            ];

            $responseDto = $this->stripePaymentRepository->createCustomer($customerData);

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
