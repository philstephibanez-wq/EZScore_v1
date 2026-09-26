<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926021000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'R25: persisted per-user realtime STEM mixer settings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE user_song_stem_mixes (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                user_id INTEGER NOT NULL,
                song_id INTEGER NOT NULL,
                settings CLOB NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                CONSTRAINT FK_USER_SONG_STEM_MIX_USER
                    FOREIGN KEY (user_id) REFERENCES users (id)
                    ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_USER_SONG_STEM_MIX_SONG
                    FOREIGN KEY (song_id) REFERENCES songs (id)
                    ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )'
        );
        $this->addSql('CREATE UNIQUE INDEX uniq_user_song_stem_mix ON user_song_stem_mixes (user_id, song_id)');
        $this->addSql('CREATE INDEX IDX_USER_SONG_STEM_MIX_USER ON user_song_stem_mixes (user_id)');
        $this->addSql('CREATE INDEX IDX_USER_SONG_STEM_MIX_SONG ON user_song_stem_mixes (song_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_song_stem_mixes');
    }
}
