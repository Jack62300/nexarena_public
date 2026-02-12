<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260212021508 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE discord_moderation_log (id INT AUTO_INCREMENT NOT NULL, action VARCHAR(30) NOT NULL, discord_user_id VARCHAR(20) NOT NULL, discord_username VARCHAR(100) NOT NULL, channel_id VARCHAR(20) DEFAULT NULL, channel_name VARCHAR(100) DEFAULT NULL, message_content LONGTEXT DEFAULT NULL, reason VARCHAR(255) DEFAULT NULL, triggered_word VARCHAR(100) DEFAULT NULL, metadata JSON DEFAULT NULL, created_at DATETIME NOT NULL, INDEX idx_modlog_action (action), INDEX idx_modlog_discord_user (discord_user_id), INDEX idx_modlog_created_at (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE discord_moderation_log');
    }
}
