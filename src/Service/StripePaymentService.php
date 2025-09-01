<?php

namespace App\Service;

use App\DTO\Payment\StripePayment\Response\StripePaymentResponseDTO;
use App\DTO\Payment\StripePayment\Response\StripeCustomerResponseDTO;
use App\DTO\Payment\StripePayment\Response\StripeAccountInfoResponseDTO;
use App\DTO\Payment\StripePayment\Internal\StripePaymentMethodDTO;
use Stripe\Stripe;
use Stripe\Charge;
use Stripe\Customer;
use Stripe\Account;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Service\PaymentService;
use App\DTO\Payment\CreatePaymentDTO;
use App\Entity\User;
class StripePaymentService
{
    public function __construct(
        private ParameterBagInterface $parameterBag,
        private PaymentService $paymentService
    ) {
        // Configuration Stripe avec la clé secrète
        Stripe::setApiKey($this->parameterBag->get('stripe.secret_key'));
    }

    public function processPayment(User $user, string $packId, array $paymentData): StripePaymentResponseDTO
    {
        try {
            // Mapping des numéros de carte vers les tokens officiels Stripe
            $cardMapping = [
                '4242424242424242' => 'tok_visa',
                '5555555555554444' => 'tok_mastercard', 
                '378282246310005' => 'tok_amex',
                '4000000000000002' => 'tok_chargeDeclined',
                '4000000000000069' => 'tok_chargeDeclined', // Carte expirée
                '4000000000000341' => 'tok_chargeDeclined', // CVC incorrect
            ];

            $cleanCardNumber = str_replace(' ', '', $paymentData['cardNumber']);
            
            // Déterminer le token à utiliser
            $sourceToken = $cardMapping[$cleanCardNumber] ?? 'tok_visa';
            
            // Convertir le montant en centimes
            $amountInCents = (int)($paymentData['amount'] * 100);

            $chargeData = [
                'amount' => $amountInCents,
                'currency' => strtolower($paymentData['currency']),
                'source' => $sourceToken,
                'description' => $paymentData['description'],
                'metadata' => [
                    'source' => 'symfony_api',
                    'processed_at' => date('Y-m-d H:i:s'),
                    'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'customer_email' => $paymentData['customerEmail'],
                    'original_card_last4' => substr($cleanCardNumber, -4)
                ]
            ];

            $charge = Charge::create($chargeData);

            $paymentMethodDto = new StripePaymentMethodDTO(
                type: 'card',
                brand: $charge->source->brand,
                last4: $charge->source->last4,
                country: $charge->source->country
            );

            $createPaymentDTO = new CreatePaymentDTO();
            $createPaymentDTO->amount = $paymentData['amount'];
            $createPaymentDTO->currency = $paymentData['currency'];
            $createPaymentDTO->packId = $packId;
            $createPaymentDTO->description = $paymentData['description'];
            $createPaymentDTO->stripePaymentId = $charge->id;

            $payment = $this->paymentService->initiatePayment($user, $createPaymentDTO);

            return new StripePaymentResponseDTO(
                id: $charge->id,
                amount: $paymentData['amount'],
                currency: $paymentData['currency'],
                status: $charge->paid ? 'succeeded' : 'failed',
                created: $charge->created,
                description: $paymentData['description'],
                receiptUrl: $charge->receipt_url,
                paymentMethod: $paymentMethodDto
            );

        } catch (ApiErrorException $e) {
            throw new \Exception('Stripe payment failed: ' . $e->getMessage());
        }
    }

    public function getPayment(string $transactionId): StripePaymentResponseDTO
    {
        try {
            $charge = Charge::retrieve($transactionId);

            $paymentMethodDto = new StripePaymentMethodDTO(
                type: 'card',
                brand: $charge->source->brand,
                last4: $charge->source->last4,
                country: $charge->source->country
            );

            return new StripePaymentResponseDTO(
                id: $charge->id,
                amount: $charge->amount / 100, // Convert from cents to euros
                currency: strtoupper($charge->currency),
                status: $charge->paid ? 'succeeded' : 'failed',
                created: $charge->created,
                description: $charge->description,
                receiptUrl: $charge->receipt_url,
                paymentMethod: $paymentMethodDto
            );

        } catch (ApiErrorException $e) {
            throw new \Exception('Failed to retrieve payment: ' . $e->getMessage());
        }
    }

    public function getAccountInfo(): StripeAccountInfoResponseDTO
    {
        try {
            $account = Account::retrieve();

            return new StripeAccountInfoResponseDTO(
                id: $account->id,
                email: $account->email,
                country: $account->country,
                defaultCurrency: $account->default_currency,
                chargesEnabled: $account->charges_enabled,
                detailsSubmitted: $account->details_submitted,
                payoutsEnabled: $account->payouts_enabled
            );

        } catch (ApiErrorException $e) {
            throw new \Exception('Failed to get account info: ' . $e->getMessage());
        }
    }

    public function createCustomer(array $customerData): StripeCustomerResponseDTO
    {
        try {
            $customer = Customer::create([
                'email' => $customerData['email'],
                'name' => $customerData['name'] ?? null,
                'phone' => $customerData['phone'] ?? null,
                'metadata' => [
                    'source' => 'symfony_api',
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ]);

            return new StripeCustomerResponseDTO(
                id: $customer->id,
                email: $customer->email,
                name: $customer->name,
                phone: $customer->phone,
                created: $customer->created
            );

        } catch (ApiErrorException $e) {
            throw new \Exception('Failed to create customer: ' . $e->getMessage());
        }
    }

    /**
     * @return StripePaymentResponseDTO[]
     */
    public function listPayments(int $limit = 10): array
    {
        try {
            $charges = Charge::all(['limit' => $limit]);

            $payments = [];
            foreach ($charges->data as $charge) {
                $paymentMethodDto = new StripePaymentMethodDTO(
                    type: 'card',
                    brand: $charge->source->brand ?? 'unknown',
                    last4: $charge->source->last4 ?? 'unknown',
                    country: $charge->source->country ?? 'unknown'
                );

                $payments[] = new StripePaymentResponseDTO(
                    id: $charge->id,
                    amount: $charge->amount / 100,
                    currency: strtoupper($charge->currency),
                    status: $charge->paid ? 'succeeded' : 'failed',
                    created: $charge->created,
                    description: $charge->description,
                    receiptUrl: $charge->receipt_url,
                    paymentMethod: $paymentMethodDto
                );
            }

            return $payments;

        } catch (ApiErrorException $e) {
            throw new \Exception('Failed to list payments: ' . $e->getMessage());
        }
    }

    /**
     * @return StripeCustomerResponseDTO[]
     */
    public function listCustomers(int $limit = 10): array
    {
        try {
            $customers = Customer::all(['limit' => $limit]);

            $customerList = [];
            foreach ($customers->data as $customer) {
                $customerList[] = new StripeCustomerResponseDTO(
                    id: $customer->id,
                    email: $customer->email,
                    name: $customer->name,
                    phone: $customer->phone,
                    created: $customer->created
                );
            }

            return $customerList;

        } catch (ApiErrorException $e) {
            throw new \Exception('Failed to list customers: ' . $e->getMessage());
        }
    }
}
