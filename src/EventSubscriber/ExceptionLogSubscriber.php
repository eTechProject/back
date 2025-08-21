<?php
namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

#[AsEventListener(event: 'kernel.exception', priority: 10)]
class ExceptionLogSubscriber
{
    public function __construct(private LoggerInterface $logger) {}

    public function __invoke(ExceptionEvent $event): void
    {
        $e = $event->getThrowable();
        $this->logger->error('Unhandled exception', [
            'message' => $e->getMessage(),
            'class' => $e::class,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => substr($e->getTraceAsString(), 0, 4000),
            'request_uri' => $event->getRequest()->getRequestUri(),
            'method' => $event->getRequest()->getMethod(),
        ]);
    }
}
