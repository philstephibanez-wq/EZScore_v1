<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925223000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'R20.1: align SQLite events.type with Doctrine mapping by removing the database default';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('PRAGMA foreign_keys = OFF');

        $this->addSql(
            'CREATE TEMPORARY TABLE __temp__events_r20_1 AS
             SELECT id, created_by, group_id, playlist_id, title, description,
                    starts_at, ends_at, mode, location, remote_url, status,
                    created_at, updated_at, type
             FROM events'
        );

        $this->addSql('DROP TABLE events');

        $this->addSql(
            'CREATE TABLE events (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                created_by INTEGER NOT NULL,
                group_id INTEGER DEFAULT NULL,
                playlist_id INTEGER DEFAULT NULL,
                title VARCHAR(180) NOT NULL,
                description VARCHAR(1000) DEFAULT NULL,
                starts_at DATETIME NOT NULL,
                ends_at DATETIME DEFAULT NULL,
                mode VARCHAR(16) NOT NULL,
                location VARCHAR(255) DEFAULT NULL,
                remote_url VARCHAR(800) DEFAULT NULL,
                status VARCHAR(16) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                type VARCHAR(32) NOT NULL,
                CONSTRAINT FK_EVENT_CREATED_BY
                    FOREIGN KEY (created_by) REFERENCES users (id)
                    ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_EVENT_GROUP
                    FOREIGN KEY (group_id) REFERENCES user_groups (id)
                    ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_EVENT_PLAYLIST
                    FOREIGN KEY (playlist_id) REFERENCES playlists (id)
                    ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
            )'
        );

        $this->addSql(
            'INSERT INTO events (
                id, created_by, group_id, playlist_id, title, description,
                starts_at, ends_at, mode, location, remote_url, status,
                created_at, updated_at, type
             )
             SELECT
                id, created_by, group_id, playlist_id, title, description,
                starts_at, ends_at, mode, location, remote_url, status,
                created_at, updated_at, type
             FROM __temp__events_r20_1'
        );

        $this->addSql('DROP TABLE __temp__events_r20_1');

        $this->addSql('CREATE INDEX IDX_EVENT_PLAYLIST ON events (playlist_id)');
        $this->addSql('CREATE INDEX IDX_EVENT_GROUP ON events (group_id)');
        $this->addSql('CREATE INDEX IDX_EVENT_CREATED_BY ON events (created_by)');
        $this->addSql('CREATE INDEX IDX_EVENT_STARTS_AT ON events (starts_at)');

        $this->addSql('PRAGMA foreign_keys = ON');
    }

    public function down(Schema $schema): void
    {
        throw new \RuntimeException(
            'R20.1 removes an unintended SQLite default from events.type; rollback is intentionally not automated.',
        );
    }
}
