<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'R16: registered-user playlist invitations used by Symfony native ACL voters';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE playlist_invitations (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                playlist_id INTEGER NOT NULL,
                invited_user_id INTEGER NOT NULL,
                invited_by_user_id INTEGER DEFAULT NULL,
                status VARCHAR(16) NOT NULL,
                created_at DATETIME NOT NULL,
                responded_at DATETIME DEFAULT NULL,
                CONSTRAINT FK_PLAYLIST_INVITATION_PLAYLIST
                    FOREIGN KEY (playlist_id) REFERENCES playlists (id)
                    ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_PLAYLIST_INVITATION_USER
                    FOREIGN KEY (invited_user_id) REFERENCES users (id)
                    ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_PLAYLIST_INVITATION_BY
                    FOREIGN KEY (invited_by_user_id) REFERENCES users (id)
                    ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
            )'
        );
        $this->addSql('CREATE UNIQUE INDEX uniq_playlist_invited_user ON playlist_invitations (playlist_id, invited_user_id)');
        $this->addSql('CREATE INDEX IDX_PLAYLIST_INVITATION_PLAYLIST ON playlist_invitations (playlist_id)');
        $this->addSql('CREATE INDEX IDX_PLAYLIST_INVITATION_USER ON playlist_invitations (invited_user_id)');
        $this->addSql('CREATE INDEX IDX_PLAYLIST_INVITATION_STATUS ON playlist_invitations (status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE playlist_invitations');
    }
}
