<?php

declare(strict_types=1);

namespace App\Shared\Event;

use App\Shared\Domain\Event\DomainEvent;
use App\Shared\ValueObject\Uuid;
use DateTimeImmutable;

interface EventStore
{
    /**
     * @param DomainEvent[] $events
     */
    public function append(Uuid $uuid, array $events, int $expectedVersion): void;

    /**
     * @return DomainEvent[]
     */
    public function getEvents(Uuid $uuid): array;

    /**
     * @return DomainEvent[]
     */
    public function getEventsFromVersion(Uuid $uuid, int $fromVersion): array;

    /**
     * Get events for specific aggregate type and time range (optimized with aggregate_type index).
     *
     * @return DomainEvent[]
     */
    public function getEventsByAggregateType(
        string $aggregateType,
        ?DateTimeImmutable $from = null,
        ?DateTimeImmutable $to = null
    ): array;

    /**
     * Get events for specific aggregate type and aggregate ID (optimized with aggregate_type index).
     *
     * @return DomainEvent[]
     */
    public function getEventsByAggregateTypeAndId(string $aggregateType, Uuid $uuid): array;

    /**
     * Get all aggregate IDs for specific aggregate type.
     *
     * @return Uuid[]
     */
    public function getAggregateIdsByType(string $aggregateType): array;
}
