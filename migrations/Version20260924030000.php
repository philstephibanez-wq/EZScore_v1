<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'R7: verified email and activation token lifecycle for public registrations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD COLUMN email_verified_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD COLUMN activation_token_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD COLUMN activation_expires_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD COLUMN activation_requested_at DATETIME DEFAULT NULL');
        $this->addSql('UPDATE users SET email_verified_at = created_at WHERE email_verified_at IS NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_users_activation_token_hash ON users (activation_token_hash)');
    }

    public function down(Schema $schema): void
    {
        throw new \RuntimeException(
            'R7 adds email verification state to users; rollback is intentionally not automated on SQLite.',
        );
    }
}
