<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260225013749 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add crypto_payment_id and crypto_status columns to transaction table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE transaction ADD crypto_payment_id VARCHAR(255) DEFAULT NULL, ADD crypto_status VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE transaction DROP COLUMN crypto_payment_id, DROP COLUMN crypto_status');
    }
}
