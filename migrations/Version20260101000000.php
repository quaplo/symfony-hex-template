<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260101000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial skeleton: event_store and snapshots tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE event_store (
                aggregate_id UUID NOT NULL,
                event_type VARCHAR(255) NOT NULL,
                event_data JSON NOT NULL,
                version INT NOT NULL,
                occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                aggregate_type VARCHAR(255) NOT NULL,
                PRIMARY KEY (aggregate_id, version)
            )
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE snapshots (
                aggregate_id UUID NOT NULL,
                aggregate_type VARCHAR(255) NOT NULL,
                snapshot_data JSON NOT NULL,
                version INT NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (aggregate_id)
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE snapshots');
        $this->addSql('DROP TABLE event_store');
    }
}
