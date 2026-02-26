<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\EventStore;

use App\Shared\Domain\Event\DomainEvent;
use InvalidArgumentException;

final readonly class AggregateTypeResolver
{
    /**
     * Derives aggregate_type from domain event class namespace.
     *
     * Examples:
     * - App\Project\Domain\Event\ProjectCreatedEvent → App\Project
     * - App\User\Domain\Event\UserDeletedEvent → App\User
     * - App\Order\Domain\Event\OrderCreatedEvent → App\Order
     * - App\Shared\Domain\Event\UserDeletedIntegrationEvent → App\Shared
     */
    public function resolve(DomainEvent $domainEvent): string
    {
        return $this->resolveFromClassName($domainEvent::class);
    }

    public function resolveFromClassName(string $eventClassName): string
    {
        // Split namespace by backslashes
        $namespaceParts = explode('\\', $eventClassName);

        // For structure like App\Domain\Event\EventName → extract App\Domain
        // For structure like App\Project\Domain\Event\EventName → extract App\Project
        if (\count($namespaceParts) < 3) {
            throw new InvalidArgumentException(
                \sprintf('Invalid event class namespace structure: %s', $eventClassName)
            );
        }

        // Default fallback - take first 2 namespace parts
        return $namespaceParts[0] . '\\' . $namespaceParts[1];
    }
}
