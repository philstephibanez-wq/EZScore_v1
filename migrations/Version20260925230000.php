<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'R22: new-song publication mailing preferences and per-recipient delivery journal';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD COLUMN notify_new_songs BOOLEAN NOT NULL DEFAULT 1");

        $this->addSql(
            'CREATE TABLE song_publication_notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                song_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                created_at DATETIME NOT NULL,
                sent_at DATETIME DEFAULT NULL,
                failed_at DATETIME DEFAULT NULL,
                last_error VARCHAR(500) DEFAULT NULL,
                CONSTRAINT FK_SONG_PUBLICATION_NOTIFICATION_SONG
                    FOREIGN KEY (song_id) REFERENCES songs (id)
                    ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_SONG_PUBLICATION_NOTIFICATION_USER
                    FOREIGN KEY (user_id) REFERENCES users (id)
                    ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )'
        );
        $this->addSql(
            'CREATE UNIQUE INDEX uniq_song_publication_notification
             ON song_publication_notifications (song_id, user_id)'
        );
        $this->addSql(
            'CREATE INDEX IDX_SONG_PUBLICATION_NOTIFICATION_SONG
             ON song_publication_notifications (song_id)'
        );
        $this->addSql(
            'CREATE INDEX IDX_SONG_PUBLICATION_NOTIFICATION_USER
             ON song_publication_notifications (user_id)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE song_publication_notifications');

        throw new \RuntimeException(
            'R22 adds a non-null user notification preference. Automatic SQLite rollback is intentionally not provided.',
        );
    }
}
