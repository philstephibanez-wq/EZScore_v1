<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925213000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'R19: events, RSVP participants and event notification tracking';
    }

    public function up(Schema $schema): void
    {
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
        $this->addSql('CREATE INDEX IDX_EVENT_STARTS_AT ON events (starts_at)');
        $this->addSql('CREATE INDEX IDX_EVENT_CREATED_BY ON events (created_by)');
        $this->addSql('CREATE INDEX IDX_EVENT_GROUP ON events (group_id)');
        $this->addSql('CREATE INDEX IDX_EVENT_PLAYLIST ON events (playlist_id)');

        $this->addSql(
            'CREATE TABLE event_participants (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                event_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                status VARCHAR(16) NOT NULL,
                created_at DATETIME NOT NULL,
                responded_at DATETIME DEFAULT NULL,
                email_notified_at DATETIME DEFAULT NULL,
                CONSTRAINT FK_EVENT_PARTICIPANT_EVENT
                    FOREIGN KEY (event_id) REFERENCES events (id)
                    ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_EVENT_PARTICIPANT_USER
                    FOREIGN KEY (user_id) REFERENCES users (id)
                    ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )'
        );
        $this->addSql('CREATE UNIQUE INDEX uniq_event_participant ON event_participants (event_id, user_id)');
        $this->addSql('CREATE INDEX IDX_EVENT_PARTICIPANT_EVENT ON event_participants (event_id)');
        $this->addSql('CREATE INDEX IDX_EVENT_PARTICIPANT_USER ON event_participants (user_id)');
        $this->addSql('CREATE INDEX IDX_EVENT_PARTICIPANT_STATUS ON event_participants (status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE event_participants');
        $this->addSql('DROP TABLE events');
    }
}
