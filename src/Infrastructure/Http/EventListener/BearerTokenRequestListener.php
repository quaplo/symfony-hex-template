<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class BearerTokenRequestListener implements EventSubscriberInterface
{
    public function __construct(
        private string $authToken,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $header = $event->getRequest()->headers->get('Authorization', '');
        $token = str_starts_with($header, 'Bearer ') ? substr($header, 7) : null;

        if ($token !== $this->authToken) {
            $event->setResponse(new JsonResponse(['error' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED));
        }
    }
}
