<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'EZScore_v1 auth, groups and playlists foundation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                email VARCHAR(190) NOT NULL,
                display_name VARCHAR(120) NOT NULL,
                password CLOB DEFAULT NULL,
                roles CLOB NOT NULL,
                active BOOLEAN NOT NULL,
                google_sub VARCHAR(255) DEFAULT NULL,
                avatar_url VARCHAR(600) DEFAULT NULL,
                created_at DATETIME NOT NULL
            )'
        );
        $this->addSql('CREATE UNIQUE INDEX uniq_users_email ON users (email)');

        $this->addSql(
            'CREATE TABLE user_groups (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                name VARCHAR(120) NOT NULL,
                description VARCHAR(500) DEFAULT NULL,
                created_at DATETIME NOT NULL
            )'
        );

        $this->addSql(
            'CREATE TABLE group_members (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                group_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                role VARCHAR(20) NOT NULL,
                CONSTRAINT FK_GROUP_MEMBER_GROUP
                    FOREIGN KEY (group_id) REFERENCES user_groups (id)
                    ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_GROUP_MEMBER_USER
                    FOREIGN KEY (user_id) REFERENCES users (id)
                    ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )'
        );
        $this->addSql('CREATE UNIQUE INDEX uniq_group_member ON group_members (group_id, user_id)');
        $this->addSql('CREATE INDEX IDX_GROUP_MEMBER_GROUP ON group_members (group_id)');
        $this->addSql('CREATE INDEX IDX_GROUP_MEMBER_USER ON group_members (user_id)');

        $this->addSql(
            'CREATE TABLE playlists (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                created_by INTEGER NOT NULL,
                name VARCHAR(140) NOT NULL,
                description VARCHAR(500) DEFAULT NULL,
                owner_type VARCHAR(16) NOT NULL,
                owner_id INTEGER NOT NULL,
                public BOOLEAN NOT NULL,
                created_at DATETIME NOT NULL,
                CONSTRAINT FK_PLAYLIST_CREATED_BY
                    FOREIGN KEY (created_by) REFERENCES users (id)
                    ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )'
        );
        $this->addSql('CREATE INDEX IDX_PLAYLIST_CREATED_BY ON playlists (created_by)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE playlists');
        $this->addSql('DROP TABLE group_members');
        $this->addSql('DROP TABLE user_groups');
        $this->addSql('DROP TABLE users');
    }
}
