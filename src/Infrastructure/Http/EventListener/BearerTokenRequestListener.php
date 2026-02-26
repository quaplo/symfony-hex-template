<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\EventListener;

use App\Authorization\Domain\TokenValidator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class BearerTokenRequestListener implements EventSubscriberInterface
{
    public function __construct(private TokenValidator $validator) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 100]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $authorization = $event->getRequest()->headers->get('Authorization', '');
        $token = str_starts_with($authorization, 'Bearer ') ? substr($authorization, 7) : null;

        if ($token === null || !$this->validator->isValid($token)) {
            $event->setResponse(new JsonResponse(['error' => 'Unauthorized'], 401));
        }
    }
}
