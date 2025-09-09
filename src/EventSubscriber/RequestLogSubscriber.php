<?php
namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

#[AsEventListener(event: 'kernel.request', priority: 512)]
class RequestLogSubscriber
{
    public function __construct(private LoggerInterface $logger) {}

    public function __invoke(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->logger->info('kernel.request start', [
                'uri' => $event->getRequest()->getRequestUri(),
                'method' => $event->getRequest()->getMethod()
            ]);
        }
    }
}
