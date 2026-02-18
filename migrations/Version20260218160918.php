<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260218160918 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user ADD is_email_verified TINYINT(1) NOT NULL DEFAULT 1, ADD email_verification_token VARCHAR(64) DEFAULT NULL, ADD trusted_ips JSON DEFAULT NULL, ADD device_verification_token VARCHAR(64) DEFAULT NULL, ADD device_verification_token_expiry DATETIME DEFAULT NULL, ADD pending_device_ip VARCHAR(45) DEFAULT NULL');
        $this->addSql('UPDATE `user` SET is_email_verified = 1 WHERE is_email_verified = 0');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `user` DROP is_email_verified, DROP email_verification_token, DROP trusted_ips, DROP device_verification_token, DROP device_verification_token_expiry, DROP pending_device_ip');
    }
}
