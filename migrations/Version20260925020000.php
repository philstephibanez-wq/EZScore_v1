<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'R9: one 1-to-5 star rating per registered user and song';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE song_ratings (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                song_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                rating INTEGER NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                CONSTRAINT FK_SONG_RATING_SONG
                    FOREIGN KEY (song_id) REFERENCES songs (id)
                    ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_SONG_RATING_USER
                    FOREIGN KEY (user_id) REFERENCES users (id)
                    ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT CHK_SONG_RATING_VALUE CHECK (rating >= 1 AND rating <= 5)
            )'
        );
        $this->addSql('CREATE UNIQUE INDEX uniq_song_rating_user ON song_ratings (song_id, user_id)');
        $this->addSql('CREATE INDEX IDX_SONG_RATING_SONG ON song_ratings (song_id)');
        $this->addSql('CREATE INDEX IDX_SONG_RATING_USER ON song_ratings (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE song_ratings');
    }
}
