<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\EventStore;

use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Event\EventSerializer;
use App\Shared\Event\EventStore;
use App\Shared\ValueObject\Uuid;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;
use RuntimeException;

final readonly class DoctrineEventStore implements EventStore
{
    public function __construct(
        private Connection $connection,
        private EventSerializer $eventSerializer,
        private AggregateTypeResolver $aggregateTypeResolver,
    ) {
    }

    public function append(Uuid $uuid, array $events, int $expectedVersion): void
    {
        $this->connection->beginTransaction();

        try {
            // Check optimistic concurrency
            $currentVersion = $this->getCurrentVersion($uuid);

            if ($currentVersion !== $expectedVersion) {
                throw new RuntimeException(
                    \sprintf('Concurrency conflict: expected version %d, got %d', $expectedVersion, $currentVersion)
                );
            }

            $nextVersion = $expectedVersion + 1;

            foreach ($events as $event) {
                $this->insertEvent($uuid, $event, $nextVersion++);
            }

            $this->connection->commit();
        } catch (Exception $e) {
            $this->connection->rollBack();

            throw $e;
        }
    }

    public function getEvents(Uuid $uuid): array
    {
        $sql = 'SELECT event_data, event_type, version FROM event_store
                WHERE aggregate_id = ?
                ORDER BY version ASC';

        $statement = $this->connection->prepare($sql);
        $statement->bindValue(1, $uuid->toString(), Types::STRING);
        $result = $statement->executeQuery();

        $events = [];

        while ($row = $result->fetchAssociative()) {
            $events[] = $this->deserializeEvent($row['event_data'], $row['event_type']);
        }

        return $events;
    }

    public function getEventsFromVersion(Uuid $uuid, int $fromVersion): array
    {
        $sql = 'SELECT event_data, event_type, version FROM event_store
                WHERE aggregate_id = ? AND version >= ?
                ORDER BY version ASC';

        $statement = $this->connection->prepare($sql);
        $statement->bindValue(1, $uuid->toString(), Types::STRING);
        $statement->bindValue(2, $fromVersion, Types::INTEGER);
        $result = $statement->executeQuery();

        $events = [];

        while ($row = $result->fetchAssociative()) {
            $events[] = $this->deserializeEvent($row['event_data'], $row['event_type']);
        }

        return $events;
    }

    public function getEventsByAggregateType(
        string $aggregateType,
        ?DateTimeImmutable $from = null,
        ?DateTimeImmutable $to = null
    ): array {
        $sql = 'SELECT event_data, event_type, version FROM event_store WHERE aggregate_type = ?';
        $params = [$aggregateType];
        $types = [Types::STRING];

        if ($from instanceof DateTimeImmutable) {
            $sql .= ' AND occurred_at >= ?';
            $params[] = $from;
            $types[] = Types::DATETIME_IMMUTABLE;
        }

        if ($to instanceof DateTimeImmutable) {
            $sql .= ' AND occurred_at <= ?';
            $params[] = $to;
            $types[] = Types::DATETIME_IMMUTABLE;
        }

        $sql .= ' ORDER BY occurred_at ASC, version ASC';

        $statement = $this->connection->prepare($sql);

        foreach ($params as $index => $param) {
            $statement->bindValue($index + 1, $param, $types[$index]);
        }
        $result = $statement->executeQuery();

        $events = [];

        while ($row = $result->fetchAssociative()) {
            $events[] = $this->deserializeEvent($row['event_data'], $row['event_type']);
        }

        return $events;
    }

    public function getEventsByAggregateTypeAndId(string $aggregateType, Uuid $uuid): array
    {
        $sql = 'SELECT event_data, event_type, version FROM event_store
                WHERE aggregate_type = ? AND aggregate_id = ?
                ORDER BY version ASC';

        $statement = $this->connection->prepare($sql);
        $statement->bindValue(1, $aggregateType, Types::STRING);
        $statement->bindValue(2, $uuid->toString(), Types::STRING);
        $result = $statement->executeQuery();

        $events = [];

        while ($row = $result->fetchAssociative()) {
            $events[] = $this->deserializeEvent($row['event_data'], $row['event_type']);
        }

        return $events;
    }

    public function getAggregateIdsByType(string $aggregateType): array
    {
        $sql = 'SELECT DISTINCT aggregate_id FROM event_store WHERE aggregate_type = ?';

        $statement = $this->connection->prepare($sql);
        $statement->bindValue(1, $aggregateType, Types::STRING);
        $result = $statement->executeQuery();

        $aggregateIds = [];

        while ($row = $result->fetchAssociative()) {
            $aggregateIds[] = Uuid::create($row['aggregate_id']);
        }

        return $aggregateIds;
    }

    private function getCurrentVersion(Uuid $uuid): int
    {
        $sql = 'SELECT MAX(version) as version FROM event_store WHERE aggregate_id = ?';
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(1, $uuid->toString(), Types::STRING);
        $result = $statement->executeQuery();
        $row = $result->fetchAssociative();

        return $row['version'] ?? 0;
    }

    private function insertEvent(Uuid $uuid, DomainEvent $domainEvent, int $version): void
    {
        $aggregateType = $this->aggregateTypeResolver->resolve($domainEvent);

        $sql = 'INSERT INTO event_store (aggregate_id, aggregate_type, event_type, event_data, version, occurred_at)
                VALUES (?, ?, ?, ?, ?, ?)';

        $statement = $this->connection->prepare($sql);
        $statement->bindValue(1, $uuid->toString(), Types::STRING);
        $statement->bindValue(2, $aggregateType, Types::STRING);
        $statement->bindValue(3, $domainEvent::class, Types::STRING);
        $statement->bindValue(4, $this->serializeEvent($domainEvent), Types::TEXT);
        $statement->bindValue(5, $version, Types::INTEGER);
        $statement->bindValue(6, $domainEvent->getOccurredAt(), Types::DATETIME_IMMUTABLE);

        $statement->executeStatement();
    }

    private function serializeEvent(DomainEvent $domainEvent): string
    {
        return $this->eventSerializer->serialize($domainEvent);
    }

    private function deserializeEvent(string $eventData, string $eventType): DomainEvent
    {
        return $this->eventSerializer->deserialize($eventData, $eventType);
    }
}
