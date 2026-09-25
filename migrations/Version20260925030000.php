<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'R10: song import metadata, workflow status, author/composer, private MP3 reference and cover';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE songs ADD COLUMN author VARCHAR(180) DEFAULT NULL");
        $this->addSql("ALTER TABLE songs ADD COLUMN composer VARCHAR(180) DEFAULT NULL");
        $this->addSql("ALTER TABLE songs ADD COLUMN status VARCHAR(16) NOT NULL DEFAULT 'editing'");
        $this->addSql("ALTER TABLE songs ADD COLUMN cover_path VARCHAR(500) DEFAULT NULL");
        $this->addSql("ALTER TABLE songs ADD COLUMN audio_original_name VARCHAR(255) DEFAULT NULL");
        $this->addSql("ALTER TABLE songs ADD COLUMN audio_storage_path VARCHAR(500) DEFAULT NULL");
        $this->addSql("ALTER TABLE songs ADD COLUMN audio_mime_type VARCHAR(120) DEFAULT NULL");
        $this->addSql("ALTER TABLE songs ADD COLUMN audio_size INTEGER DEFAULT NULL");
        $this->addSql("ALTER TABLE songs ADD COLUMN audio_sha256 VARCHAR(64) DEFAULT NULL");
        $this->addSql("ALTER TABLE songs ADD COLUMN imported_at DATETIME DEFAULT NULL");

        $this->addSql("UPDATE songs SET status = 'published' WHERE published_at IS NOT NULL");
        $this->addSql("UPDATE songs SET status = 'editing' WHERE published_at IS NULL");
    }

    public function down(Schema $schema): void
    {
        throw new \RuntimeException('R10 adds import/workflow metadata to songs; rollback is intentionally not automated on SQLite.');
    }
}
