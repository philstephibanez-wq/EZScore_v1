<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'R18: human-owned playlists plus explicit many-group playlist assignments';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('PRAGMA foreign_keys = OFF');

        $this->addSql(
            "CREATE TEMPORARY TABLE __legacy_playlist_groups AS
             SELECT id AS playlist_id, owner_id AS group_id, created_by AS added_by, created_at
             FROM playlists
             WHERE owner_type = 'group'"
        );

        $this->addSql(
            'CREATE TEMPORARY TABLE __temp__playlists AS
             SELECT id, created_by, name, description, owner_type, owner_id, public, created_at
             FROM playlists'
        );

        $this->addSql('DROP TABLE playlists');

        $this->addSql(
            'CREATE TABLE playlists (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                owner_user_id INTEGER NOT NULL,
                created_by INTEGER NOT NULL,
                name VARCHAR(140) NOT NULL,
                description VARCHAR(500) DEFAULT NULL,
                public BOOLEAN NOT NULL,
                created_at DATETIME NOT NULL,
                CONSTRAINT FK_PLAYLIST_OWNER_USER
                    FOREIGN KEY (owner_user_id) REFERENCES users (id)
                    ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_PLAYLIST_CREATED_BY
                    FOREIGN KEY (created_by) REFERENCES users (id)
                    ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )'
        );

        $this->addSql(
            "INSERT INTO playlists (id, owner_user_id, created_by, name, description, public, created_at)
             SELECT
                p.id,
                CASE
                    WHEN p.owner_type = 'user'
                         AND EXISTS (SELECT 1 FROM users u WHERE u.id = p.owner_id)
                    THEN p.owner_id
                    ELSE p.created_by
                END,
                p.created_by,
                p.name,
                p.description,
                p.public,
                p.created_at
             FROM __temp__playlists p"
        );

        $this->addSql('DROP TABLE __temp__playlists');
        $this->addSql('CREATE INDEX IDX_5E06116F2B18554A ON playlists (owner_user_id)');
        $this->addSql('CREATE INDEX IDX_5E06116FDE12AB56 ON playlists (created_by)');

        $this->addSql(
            'CREATE TABLE playlist_groups (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                playlist_id INTEGER NOT NULL,
                group_id INTEGER NOT NULL,
                added_by INTEGER DEFAULT NULL,
                created_at DATETIME NOT NULL,
                CONSTRAINT FK_PLAYLIST_GROUP_PLAYLIST
                    FOREIGN KEY (playlist_id) REFERENCES playlists (id)
                    ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_PLAYLIST_GROUP_GROUP
                    FOREIGN KEY (group_id) REFERENCES user_groups (id)
                    ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_PLAYLIST_GROUP_ADDED_BY
                    FOREIGN KEY (added_by) REFERENCES users (id)
                    ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
            )'
        );

        $this->addSql('CREATE UNIQUE INDEX uniq_playlist_group ON playlist_groups (playlist_id, group_id)');
        $this->addSql('CREATE INDEX IDX_PLAYLIST_GROUP_PLAYLIST ON playlist_groups (playlist_id)');
        $this->addSql('CREATE INDEX IDX_PLAYLIST_GROUP_GROUP ON playlist_groups (group_id)');
        $this->addSql('CREATE INDEX IDX_PLAYLIST_GROUP_ADDED_BY ON playlist_groups (added_by)');

        $this->addSql(
            'INSERT INTO playlist_groups (playlist_id, group_id, added_by, created_at)
             SELECT l.playlist_id, l.group_id, l.added_by, l.created_at
             FROM __legacy_playlist_groups l
             WHERE EXISTS (SELECT 1 FROM playlists p WHERE p.id = l.playlist_id)
               AND EXISTS (SELECT 1 FROM user_groups g WHERE g.id = l.group_id)'
        );

        $this->addSql('DROP TABLE __legacy_playlist_groups');
        $this->addSql('PRAGMA foreign_keys = ON');
    }

    public function down(Schema $schema): void
    {
        throw new \RuntimeException(
            'R18 separates human ownership from group assignment and is intentionally not auto-reversible.',
        );
    }
}
