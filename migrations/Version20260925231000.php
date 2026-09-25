<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925231000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'R22.1: asynchronous publication-mail job queue';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE song_publication_mail_jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                song_id INTEGER NOT NULL,
                created_at DATETIME NOT NULL,
                claimed_at DATETIME DEFAULT NULL,
                claim_token VARCHAR(64) DEFAULT NULL,
                completed_at DATETIME DEFAULT NULL,
                next_attempt_at DATETIME DEFAULT NULL,
                attempts INTEGER DEFAULT 0 NOT NULL,
                last_error VARCHAR(500) DEFAULT NULL,
                CONSTRAINT FK_SONG_PUBLICATION_MAIL_JOB_SONG
                    FOREIGN KEY (song_id) REFERENCES songs (id)
                    ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )'
        );

        $this->addSql(
            'CREATE UNIQUE INDEX uniq_song_publication_mail_job_song
             ON song_publication_mail_jobs (song_id)'
        );
        $this->addSql(
            'CREATE INDEX IDX_SONG_PUBLICATION_MAIL_JOB_COMPLETED
             ON song_publication_mail_jobs (completed_at)'
        );
        $this->addSql(
            'CREATE INDEX IDX_SONG_PUBLICATION_MAIL_JOB_CLAIM
             ON song_publication_mail_jobs (claimed_at)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE song_publication_mail_jobs');
    }
}
