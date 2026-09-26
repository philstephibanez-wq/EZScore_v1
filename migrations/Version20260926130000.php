<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'R32: canonical song timeline events and ChordsLab editorial settings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE songs ADD COLUMN key_signature VARCHAR(16) DEFAULT NULL");
        $this->addSql("ALTER TABLE songs ADD COLUMN chord_analysis_level VARCHAR(16) NOT NULL DEFAULT 'intermediate'");

        $this->addSql(
            "CREATE TABLE song_timeline_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                song_id INTEGER NOT NULL,
                event_type VARCHAR(24) NOT NULL,
                start_ms INTEGER NOT NULL,
                end_ms INTEGER DEFAULT NULL,
                measure_index INTEGER DEFAULT NULL,
                beat_index INTEGER DEFAULT NULL,
                subdivision_index INTEGER DEFAULT NULL,
                original_value VARCHAR(64) DEFAULT NULL,
                override_value VARCHAR(64) DEFAULT NULL,
                payload CLOB DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                CONSTRAINT FK_TIMELINE_SONG FOREIGN KEY (song_id) REFERENCES songs (id) ON DELETE CASCADE
            )"
        );
        $this->addSql("CREATE INDEX IDX_TIMELINE_SONG_TYPE_START ON song_timeline_events (song_id, event_type, start_ms)");
    }

    public function down(Schema $schema): void
    {
        throw new \RuntimeException(
            'R32 introduces the canonical musical timeline; rollback is intentionally not automated.',
        );
    }
}
