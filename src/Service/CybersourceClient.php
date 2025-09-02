<?php

namespace App\Service;

class CybersourceClient
{
    public function __construct(
        private readonly string $apiKey = '',
        private readonly string $orgId = '',
        private readonly string $sharedSecret = ''
    ) {}

    public function createPaymentSession(array $payload): array
    {
        // Placeholder implementation. Integrate real Cybersource SDK / API here.
        // Return a fake session for now. In production, use $this->apiKey / $this->orgId / $this->sharedSecret.
        return [
            'providerResponse' => json_encode(['sessionId' => 'cs_test_' . bin2hex(random_bytes(6))]),
            'redirectUrl' => null
        ];
    }
}
