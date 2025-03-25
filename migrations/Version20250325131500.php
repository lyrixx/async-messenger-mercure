<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250325131500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create processed_messages table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE processed_messages (id UUID NOT NULL, run_id INT NOT NULL, attempt SMALLINT NOT NULL, message_type VARCHAR(255) NOT NULL, description VARCHAR(255) DEFAULT NULL, dispatched_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, received_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, finished_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, wait_time BIGINT NOT NULL, handle_time BIGINT NOT NULL, memory_usage INT NOT NULL, transport VARCHAR(255) NOT NULL, tags VARCHAR(255) DEFAULT NULL, failure_type VARCHAR(255) DEFAULT NULL, failure_message TEXT DEFAULT NULL, results JSON DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN processed_messages.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN processed_messages.dispatched_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN processed_messages.received_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN processed_messages.finished_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE processed_messages');
    }
}
