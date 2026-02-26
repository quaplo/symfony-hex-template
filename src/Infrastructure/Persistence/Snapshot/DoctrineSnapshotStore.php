<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Snapshot;

use App\Shared\Domain\Model\AggregateSnapshot;
use App\Shared\Event\SnapshotStore;
use App\Shared\ValueObject\Uuid;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use JsonException;
use RuntimeException;

final readonly class DoctrineSnapshotStore implements SnapshotStore
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @throws Exception
     */
    public function save(AggregateSnapshot $aggregateSnapshot): void
    {
        $sql = '
            INSERT INTO aggregate_snapshots (
                aggregate_id,
                aggregate_type,
                version,
                data,
                created_at
            ) VALUES (?, ?, ?, ?, ?)
            ON CONFLICT (aggregate_id, aggregate_type)
            DO UPDATE SET
                version = EXCLUDED.version,
                data = EXCLUDED.data,
                created_at = EXCLUDED.created_at
        ';

        $this->connection->executeStatement($sql, [
            $aggregateSnapshot->getAggregateId()->toString(),
            $this->getAggregateType($aggregateSnapshot),
            $aggregateSnapshot->getVersion(),
            json_encode($aggregateSnapshot->getData(), \JSON_THROW_ON_ERROR),
            date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @throws Exception
     */
    public function loadLatest(Uuid $uuid, string $aggregateType): ?AggregateSnapshot
    {
        $sql = '
            SELECT aggregate_id, aggregate_type, version, data, created_at
            FROM aggregate_snapshots
            WHERE aggregate_id = ? AND aggregate_type = ?
            ORDER BY version DESC
            LIMIT 1
        ';

        $row = $this->connection->fetchAssociative($sql, [
            $uuid->toString(),
            $aggregateType,
        ]);

        if (!$row) {
            return null;
        }

        return $this->createSnapshotFromRow($row);
    }

    /**
     * @throws Exception
     */
    public function loadByVersion(Uuid $uuid, string $aggregateType, int $version): ?AggregateSnapshot
    {
        $sql = '
            SELECT aggregate_id, aggregate_type, version, data, created_at
            FROM aggregate_snapshots
            WHERE aggregate_id = ? AND aggregate_type = ? AND version = ?
        ';

        $row = $this->connection->fetchAssociative($sql, [
            $uuid->toString(),
            $aggregateType,
            $version,
        ]);

        if (!$row) {
            return null;
        }

        return $this->createSnapshotFromRow($row);
    }

    /**
     * @throws Exception
     */
    public function deleteOlderThan(Uuid $uuid, string $aggregateType, int $version): void
    {
        $sql = '
            DELETE FROM aggregate_snapshots
            WHERE aggregate_id = ? AND aggregate_type = ? AND version < ?
        ';

        $this->connection->executeStatement($sql, [
            $uuid->toString(),
            $aggregateType,
            $version,
        ]);
    }

    /**
     * @throws Exception
     */
    public function exists(Uuid $uuid, string $aggregateType): bool
    {
        $sql = '
            SELECT COUNT(*) as count
            FROM aggregate_snapshots
            WHERE aggregate_id = ? AND aggregate_type = ?
        ';

        $result = $this->connection->fetchAssociative($sql, [
            $uuid->toString(),
            $aggregateType,
        ]);

        return (int) $result['count'] > 0;
    }

    /**
     * @throws Exception
     */
    public function removeAll(Uuid $uuid, string $aggregateType): void
    {
        $sql = '
            DELETE FROM aggregate_snapshots
            WHERE aggregate_id = ? AND aggregate_type = ?
        ';

        $this->connection->executeStatement($sql, [
            $uuid->toString(),
            $aggregateType,
        ]);
    }

    /**
     * @throws Exception
     */
    public function getLatestVersion(Uuid $uuid, string $aggregateType): ?int
    {
        $sql = '
            SELECT MAX(version) as latest_version
            FROM aggregate_snapshots
            WHERE aggregate_id = ? AND aggregate_type = ?
        ';

        $result = $this->connection->fetchAssociative($sql, [
            $uuid->toString(),
            $aggregateType,
        ]);

        return $result['latest_version'] !== null ? (int) $result['latest_version'] : null;
    }

    /**
     * Create specific snapshot instance from database row.
     *
     * @param array<string, mixed> $row
     *
     * @throws JsonException
     */
    private function createSnapshotFromRow(array $row): AggregateSnapshot
    {
        $uuid = Uuid::create($row['aggregate_id']);
        $version = (int) $row['version'];
        $data = json_decode((string) $row['data'], true, 512, \JSON_THROW_ON_ERROR);

        throw new RuntimeException(
            'No snapshot factory registered for aggregate type: ' . $row['aggregate_type'] .
            '. Implement snapshot creation in your bounded context.'
        );
    }

    /**
     * Get aggregate type from snapshot class name.
     */
    private function getAggregateType(AggregateSnapshot $aggregateSnapshot): string
    {
        $className = $aggregateSnapshot::class;

        // Extract type from class name (e.g., ProjectSnapshot -> Project)
        if (preg_match('/([A-Z][a-z]+)Snapshot$/', $className, $matches)) {
            return $matches[1];
        }

        throw new RuntimeException('Cannot determine aggregate type from snapshot class: ' . $className);
    }
}
