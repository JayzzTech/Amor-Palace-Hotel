<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261112183000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add persistent guest login failure counters and lockout expiry.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD failed_login_attempts INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE app_user ADD login_locked_until DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP failed_login_attempts');
        $this->addSql('ALTER TABLE app_user DROP login_locked_until');
    }
}
