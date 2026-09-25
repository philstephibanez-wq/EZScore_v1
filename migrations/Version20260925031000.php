<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925031000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'R10.1: align songs.status SQLite definition with Doctrine mapping by removing the SQL default';
    }

    public function isTransactional(): bool
    {
        // SQLite cannot toggle PRAGMA foreign_keys inside a transaction.
        return false;
    }

    public function up(Schema $schema): void
    {
        /*
         * R10 created:
         *   status VARCHAR(16) NOT NULL DEFAULT 'editing'
         *
         * The Doctrine mapping declares the field NOT NULL without a database
         * default. SQLite cannot DROP DEFAULT in place, so the table must be
         * rebuilt. Foreign keys are disabled only for the duration of this
         * controlled rebuild so related analysis_jobs/song_ratings rows are
         * preserved.
         */
        $this->addSql('PRAGMA foreign_keys = OFF');

        $this->addSql(
            'CREATE TEMPORARY TABLE __temp__songs AS
             SELECT
                id,
                editor_id,
                title,
                artist,
                time_signature,
                capo,
                strumming_primary,
                strumming_alternate,
                comment,
                created_at,
                updated_at,
                published_at,
                author,
                composer,
                status,
                cover_path,
                audio_original_name,
                audio_storage_path,
                audio_mime_type,
                audio_size,
                audio_sha256,
                imported_at
             FROM songs'
        );

        $this->addSql('DROP TABLE songs');

        $this->addSql(
            'CREATE TABLE songs (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                editor_id INTEGER DEFAULT NULL,
                title VARCHAR(180) NOT NULL,
                artist VARCHAR(180) NOT NULL,
                time_signature VARCHAR(8) NOT NULL,
                capo INTEGER NOT NULL,
                strumming_primary VARCHAR(120) DEFAULT NULL,
                strumming_alternate VARCHAR(120) DEFAULT NULL,
                comment VARCHAR(1000) DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                published_at DATETIME DEFAULT NULL,
                author VARCHAR(180) DEFAULT NULL,
                composer VARCHAR(180) DEFAULT NULL,
                status VARCHAR(16) NOT NULL,
                cover_path VARCHAR(500) DEFAULT NULL,
                audio_original_name VARCHAR(255) DEFAULT NULL,
                audio_storage_path VARCHAR(500) DEFAULT NULL,
                audio_mime_type VARCHAR(120) DEFAULT NULL,
                audio_size INTEGER DEFAULT NULL,
                audio_sha256 VARCHAR(64) DEFAULT NULL,
                imported_at DATETIME DEFAULT NULL,
                CONSTRAINT FK_SONG_EDITOR
                    FOREIGN KEY (editor_id) REFERENCES users (id)
                    ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
            )'
        );

        $this->addSql(
            'INSERT INTO songs (
                id,
                editor_id,
                title,
                artist,
                time_signature,
                capo,
                strumming_primary,
                strumming_alternate,
                comment,
                created_at,
                updated_at,
                published_at,
                author,
                composer,
                status,
                cover_path,
                audio_original_name,
                audio_storage_path,
                audio_mime_type,
                audio_size,
                audio_sha256,
                imported_at
             )
             SELECT
                id,
                editor_id,
                title,
                artist,
                time_signature,
                capo,
                strumming_primary,
                strumming_alternate,
                comment,
                created_at,
                updated_at,
                published_at,
                author,
                composer,
                status,
                cover_path,
                audio_original_name,
                audio_storage_path,
                audio_mime_type,
                audio_size,
                audio_sha256,
                imported_at
             FROM __temp__songs'
        );

        $this->addSql('DROP TABLE __temp__songs');

        $this->addSql('CREATE INDEX IDX_SONGS_PUBLISHED_AT ON songs (published_at)');
        $this->addSql('CREATE INDEX IDX_BAECB19B6995AC4C ON songs (editor_id)');

        $this->addSql('PRAGMA foreign_keys = ON');
    }

    public function down(Schema $schema): void
    {
        throw new \RuntimeException(
            'R10.1 normalises the SQLite schema to Doctrine mapping; rollback is intentionally not automated.',
        );
    }
}
