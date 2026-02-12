<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260211123255 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE admin_webhook (id INT AUTO_INCREMENT NOT NULL, event_type VARCHAR(50) NOT NULL, label VARCHAR(100) NOT NULL, category VARCHAR(50) NOT NULL, webhook_url VARCHAR(500) DEFAULT NULL, is_enabled TINYINT NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_4E4895DF93151B82 (event_type), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE admin_webhook');
    }
}
