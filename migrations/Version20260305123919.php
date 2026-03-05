<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260305123919 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE server ADD webhook_embed_config JSON DEFAULT NULL');
        $this->addSql('CREATE TABLE webhook_subscription (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(20) NOT NULL, expires_at DATETIME NOT NULL, auto_renew TINYINT NOT NULL, created_at DATETIME NOT NULL, renewed_at DATETIME DEFAULT NULL, server_id INT NOT NULL, subscribed_by_id INT NOT NULL, INDEX IDX_97AE521C95490B17 (subscribed_by_id), UNIQUE INDEX uniq_webhook_sub_server (server_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE webhook_subscription ADD CONSTRAINT FK_97AE521C1844E6B7 FOREIGN KEY (server_id) REFERENCES server (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE webhook_subscription ADD CONSTRAINT FK_97AE521C95490B17 FOREIGN KEY (subscribed_by_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE webhook_subscription DROP FOREIGN KEY FK_97AE521C1844E6B7');
        $this->addSql('ALTER TABLE webhook_subscription DROP FOREIGN KEY FK_97AE521C95490B17');
        $this->addSql('DROP TABLE webhook_subscription');
        $this->addSql('ALTER TABLE server DROP webhook_embed_config');
    }
}
