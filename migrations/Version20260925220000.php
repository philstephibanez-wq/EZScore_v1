<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'R19.1: add extensible event type; all existing events become sessions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE events ADD COLUMN type VARCHAR(32) NOT NULL DEFAULT 'session'");
    }

    public function down(Schema $schema): void
    {
        throw new \RuntimeException(
            'R19.1 adds the event type discriminator for future event kinds; rollback is intentionally not automated.',
        );
    }
}
