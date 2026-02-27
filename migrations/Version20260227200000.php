<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260227200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove device verification columns from user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP COLUMN trusted_ips');
        $this->addSql('ALTER TABLE `user` DROP COLUMN device_verification_token');
        $this->addSql('ALTER TABLE `user` DROP COLUMN device_verification_token_expiry');
        $this->addSql('ALTER TABLE `user` DROP COLUMN pending_device_ip');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD trusted_ips JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD device_verification_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD device_verification_token_expiry DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE `user` ADD pending_device_ip VARCHAR(45) DEFAULT NULL');
    }
}
