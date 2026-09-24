<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'R6: user locale, persistent songs and asynchronous analysis job state';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD COLUMN locale VARCHAR(2) NOT NULL DEFAULT 'fr'");

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
                CONSTRAINT FK_SONG_EDITOR
                    FOREIGN KEY (editor_id) REFERENCES users (id)
                    ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
            )'
        );
        $this->addSql('CREATE INDEX IDX_E5A2B5B86995AC4C ON songs (editor_id)');

        $this->addSql(
            'CREATE TABLE analysis_jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                song_id INTEGER NOT NULL,
                created_by INTEGER NOT NULL,
                kind VARCHAR(40) NOT NULL,
                status VARCHAR(16) NOT NULL,
                progress INTEGER NOT NULL,
                request_data CLOB NOT NULL,
                result_data CLOB DEFAULT NULL,
                error_code VARCHAR(80) DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                CONSTRAINT FK_ANALYSIS_JOB_SONG
                    FOREIGN KEY (song_id) REFERENCES songs (id)
                    ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_ANALYSIS_JOB_CREATED_BY
                    FOREIGN KEY (created_by) REFERENCES users (id)
                    ON UPDATE NO ACTION ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE
            )'
        );
        $this->addSql('CREATE INDEX IDX_63C85A5DF675F31B ON analysis_jobs (song_id)');
        $this->addSql('CREATE INDEX IDX_63C85A5DDE12AB56 ON analysis_jobs (created_by)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE analysis_jobs');
        $this->addSql('DROP TABLE songs');

        $this->addSql(
            'CREATE TEMPORARY TABLE __temp__users AS
             SELECT id, email, display_name, password, roles, active, google_sub, avatar_url, created_at
             FROM users'
        );
        $this->addSql('DROP TABLE users');
        $this->addSql(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                email VARCHAR(190) NOT NULL,
                display_name VARCHAR(120) NOT NULL,
                password VARCHAR(255) DEFAULT NULL,
                roles CLOB NOT NULL,
                active BOOLEAN NOT NULL,
                google_sub VARCHAR(255) DEFAULT NULL,
                avatar_url VARCHAR(600) DEFAULT NULL,
                created_at DATETIME NOT NULL
            )'
        );
        $this->addSql(
            'INSERT INTO users (id, email, display_name, password, roles, active, google_sub, avatar_url, created_at)
             SELECT id, email, display_name, password, roles, active, google_sub, avatar_url, created_at
             FROM __temp__users'
        );
        $this->addSql('DROP TABLE __temp__users');
        $this->addSql('CREATE UNIQUE INDEX uniq_users_email ON users (email)');
    }
}
