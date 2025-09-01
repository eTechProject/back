<?php

namespace App\Controller\Notification;

use App\Service\Notification\MercureTokenGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/mercure/token', methods: ['GET'])]
class GetMercureTokenController extends AbstractController
{
    public function __construct(
        private readonly MercureTokenGenerator $tokenGenerator
    ) {}

    public function __invoke(): JsonResponse
    {
        $user = $this->getUser();
        $currentUserId = method_exists($user, 'getId') ? $user->getId() : $user?->getUserIdentifier();
        $token = $this->tokenGenerator->generateUserToken($currentUserId);

        return new JsonResponse([
            'token' => $token,
            'expires_at' => (new \DateTime('+1 hour'))->format(\DateTime::ATOM)
        ]);
    }
}
