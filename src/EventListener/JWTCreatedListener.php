<?php
namespace App\EventListener;

use App\Entity\User;
use App\Service\CryptService;
use App\Enum\EntityType;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;

class JWTCreatedListener
{
    public function __construct(
        private CryptService $cryptService
    ) {}

    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        
        if (!$user instanceof User) {
            return;
        }
        
        $payload = $event->getData();
        $payload['id'] = $this->cryptService->encryptId($user->getId(), EntityType::USER->value);
        $payload['roleType'] = $user->getRole()->value;
        $event->setData($payload);
    }
}