<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\EventStore;

use App\Infrastructure\Persistence\Doctrine\Entity\EventStoreEntity;
use App\Shared\ValueObject\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CompoundPrimaryKeyTest extends TestCase
{
    public function testCompoundPrimaryKeyStructure(): void
    {
        $uuid = Uuid::generate();
        $version = 1;
        $aggregateType = 'App\\Project';
        $eventType = 'App\\Project\\Domain\\Event\\ProjectCreatedEvent';
        $eventData = '{"test": "data"}';
        $occurredAt = new DateTimeImmutable();

        $eventStoreEntity = new EventStoreEntity(
            $uuid->toString(),
            $version,
            $aggregateType,
            $eventType,
            $eventData,
            $occurredAt
        );

        // Compound primary key components
        $this->assertEquals($uuid->toString(), $eventStoreEntity->getAggregateId());
        $this->assertEquals($version, $eventStoreEntity->getVersion());

        // Other properties
        $this->assertEquals($aggregateType, $eventStoreEntity->getAggregateType());
        $this->assertEquals($eventType, $eventStoreEntity->getEventType());
        $this->assertEquals($eventData, $eventStoreEntity->getEventData());
        $this->assertEquals($occurredAt, $eventStoreEntity->getOccurredAt());
    }

    public function testUniqueCompoundPrimaryKey(): void
    {
        $uuid = Uuid::generate();
        $occurredAt = new DateTimeImmutable();
        $eventType = 'App\\Project\\Domain\\Event\\ProjectCreatedEvent';

        // Same aggregate, different versions should be unique
        $entity1 = new EventStoreEntity(
            $uuid->toString(),
            1,
            'App\\Project',
            $eventType,
            '{"test": "data1"}',
            $occurredAt
        );

        $entity2 = new EventStoreEntity(
            $uuid->toString(),
            2,
            'App\\Project',
            $eventType,
            '{"test": "data2"}',
            $occurredAt
        );

        // Should be different entities (different compound PK)
        $this->assertEquals($entity1->getAggregateId(), $entity2->getAggregateId());
        $this->assertNotEquals($entity1->getVersion(), $entity2->getVersion());
    }

    public function testDifferentAggregatesSameVersion(): void
    {
        $uuid = Uuid::generate();
        $aggregateId2 = Uuid::generate();
        $version = 1;
        $occurredAt = new DateTimeImmutable();

        // Different aggregates, same version should be unique
        $entity1 = new EventStoreEntity(
            $uuid->toString(),
            $version,
            'App\\Project',
            'App\\Project\\Domain\\Event\\ProjectCreatedEvent',
            '{"test": "data1"}',
            $occurredAt
        );

        $entity2 = new EventStoreEntity(
            $aggregateId2->toString(),
            $version,
            'App\\Order',
            'App\\Order\\Domain\\Event\\OrderCreatedEvent',
            '{"test": "data2"}',
            $occurredAt
        );

        // Should be different entities (different compound PK)
        $this->assertNotEquals($entity1->getAggregateId(), $entity2->getAggregateId());
        $this->assertEquals($entity1->getVersion(), $entity2->getVersion());
        $this->assertNotEquals($entity1->getAggregateType(), $entity2->getAggregateType());
    }

    public function testNoArtificialIdNeeded(): void
    {
        $eventStoreEntity = new EventStoreEntity(
            Uuid::generate()->toString(),
            1,
            'App\\Project',
            'App\\Project\\Domain\\Event\\ProjectCreatedEvent',
            '{"test": "data"}',
            new DateTimeImmutable()
        );

        // Verify no getId() method exists (removed artificial ID)
        $this->assertFalse(method_exists($eventStoreEntity, 'getId'));

        // Natural compound key provides identification
        $this->assertTrue(method_exists($eventStoreEntity, 'getAggregateId'));
        $this->assertTrue(method_exists($eventStoreEntity, 'getVersion'));
    }
}
