<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925133000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'R14: ordered song items for personal and group playlists';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE playlist_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                playlist_id INTEGER NOT NULL,
                song_id INTEGER NOT NULL,
                added_by INTEGER DEFAULT NULL,
                position INTEGER NOT NULL,
                created_at DATETIME NOT NULL,
                CONSTRAINT FK_PLAYLIST_ITEM_PLAYLIST
                    FOREIGN KEY (playlist_id) REFERENCES playlists (id)
                    ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_PLAYLIST_ITEM_SONG
                    FOREIGN KEY (song_id) REFERENCES songs (id)
                    ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_PLAYLIST_ITEM_ADDED_BY
                    FOREIGN KEY (added_by) REFERENCES users (id)
                    ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
            )'
        );
        $this->addSql('CREATE UNIQUE INDEX uniq_playlist_item_song ON playlist_items (playlist_id, song_id)');
        $this->addSql('CREATE INDEX IDX_PLAYLIST_ITEM_PLAYLIST ON playlist_items (playlist_id)');
        $this->addSql('CREATE INDEX IDX_PLAYLIST_ITEM_SONG ON playlist_items (song_id)');
        $this->addSql('CREATE INDEX IDX_PLAYLIST_ITEM_ADDED_BY ON playlist_items (added_by)');
        $this->addSql('CREATE INDEX IDX_PLAYLIST_ITEM_POSITION ON playlist_items (playlist_id, position)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE playlist_items');
    }
}
