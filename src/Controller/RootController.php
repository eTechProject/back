<?php
namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class RootController
{
    #[Route('/', name: 'app_root', methods: ['GET'])]
    public function __invoke(LoggerInterface $logger): JsonResponse
    {
        $logger->info('Root endpoint hit');
        return new JsonResponse([
            'status' => 'ok',
            'message' => 'Guard API root',
            'time' => gmdate('c')
        ]);
    }
}
