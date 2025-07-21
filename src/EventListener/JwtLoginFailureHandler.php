<?php
namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class JwtLoginFailureHandler
{
    public function onAuthenticationFailure(AuthenticationFailureEvent $event): void
    {
        $response = new JsonResponse([
            'status' => 401,
            'message' => 'Identifiants invalides',
            'data' => null
        ], Response::HTTP_UNAUTHORIZED);

        $event->setResponse($response);
    }
}
