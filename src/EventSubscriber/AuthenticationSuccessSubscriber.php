<?php

namespace App\EventSubscriber;

use App\Service\MercureTokenGenerator;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class AuthenticationSuccessSubscriber implements EventSubscriberInterface
{
    public function __construct(private MercureTokenGenerator $mercureTokenGenerator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'lexik_jwt_authentication.on_authentication_success' => 'onAuthenticationSuccess',
        ];
    }

    /**
     * Ajoute un token Mercure à la réponse d'authentification JWT
     *
     * @param AuthenticationSuccessEvent $event L'événement d'authentification réussie
     */
    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();
        
        if (!$user instanceof UserInterface) {
            return;
        }
        
        $data = $event->getData();
        
        try {
            $mercureData = $this->mercureTokenGenerator->generateTokenForUser($user);
            
            // Ajout des informations Mercure à la réponse JWT
            $data['mercure'] = [
                'token' => $mercureData['token'],
                'topics' => $mercureData['topics'],
                'expires_in' => $mercureData['expires_in']
            ];
            
            $event->setData($data);
        } catch (\Exception $e) {
            // En cas d'erreur, ne pas bloquer l'authentification,
            // simplement ne pas ajouter les informations Mercure
            error_log('Mercure token generation failed during login: ' . $e->getMessage());
            // Continue without Mercure data
        }
    }
}
