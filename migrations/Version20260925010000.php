<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'R8: add explicit song publication date for public catalog visibility';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE songs ADD COLUMN published_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_SONGS_PUBLISHED_AT ON songs (published_at)');
    }

    public function down(Schema $schema): void
    {
        throw new \RuntimeException(
            'R8 adds song publication state; rollback is intentionally not automated on SQLite.',
        );
    }
}
