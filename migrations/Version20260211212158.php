<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260211212158 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE discord_announcement (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(256) NOT NULL, content LONGTEXT NOT NULL, embed_color VARCHAR(7) DEFAULT NULL, image_url VARCHAR(500) DEFAULT NULL, channel_id VARCHAR(20) NOT NULL, type VARCHAR(20) NOT NULL, scheduled_at DATETIME DEFAULT NULL, sent_at DATETIME DEFAULT NULL, discord_message_id VARCHAR(20) DEFAULT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, created_by_id INT NOT NULL, INDEX IDX_4CF4BE0DB03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE discord_command (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(32) NOT NULL, description VARCHAR(100) NOT NULL, response LONGTEXT DEFAULT NULL, embed_title VARCHAR(256) DEFAULT NULL, embed_description LONGTEXT DEFAULT NULL, embed_color VARCHAR(7) DEFAULT NULL, embed_image VARCHAR(500) DEFAULT NULL, required_role VARCHAR(100) DEFAULT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_CA57CE8F5E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE discord_invite (id INT AUTO_INCREMENT NOT NULL, inviter_discord_id VARCHAR(20) NOT NULL, inviter_username VARCHAR(100) NOT NULL, invited_discord_id VARCHAR(20) NOT NULL, invited_username VARCHAR(100) NOT NULL, invite_code VARCHAR(20) DEFAULT NULL, joined_at DATETIME NOT NULL, INDEX idx_invite_inviter (inviter_discord_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE discord_reaction_role (id INT AUTO_INCREMENT NOT NULL, message_id VARCHAR(20) NOT NULL, channel_id VARCHAR(20) NOT NULL, emoji VARCHAR(100) NOT NULL, role_id VARCHAR(20) NOT NULL, label VARCHAR(100) DEFAULT NULL, created_at DATETIME NOT NULL, INDEX idx_reaction_role_message (message_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE discord_sanction (id INT AUTO_INCREMENT NOT NULL, discord_user_id VARCHAR(20) NOT NULL, discord_username VARCHAR(100) NOT NULL, type VARCHAR(20) NOT NULL, reason LONGTEXT DEFAULT NULL, issued_by VARCHAR(100) NOT NULL, issued_by_discord_id VARCHAR(20) NOT NULL, expires_at DATETIME DEFAULT NULL, is_revoked TINYINT NOT NULL, revoked_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, site_user_id INT DEFAULT NULL, revoked_by_id INT DEFAULT NULL, INDEX IDX_919984F75CCA315E (site_user_id), INDEX IDX_919984F7FB8FE773 (revoked_by_id), INDEX idx_sanction_discord_user (discord_user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE live_promotion (id INT AUTO_INCREMENT NOT NULL, platform VARCHAR(20) NOT NULL, channel_url VARCHAR(500) NOT NULL, channel_name VARCHAR(100) NOT NULL, start_date DATETIME NOT NULL, end_date DATETIME NOT NULL, cost INT NOT NULL, is_active TINYINT NOT NULL, last_notified_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, server_id INT DEFAULT NULL, INDEX IDX_7B0A1DA1A76ED395 (user_id), INDEX IDX_7B0A1DA11844E6B7 (server_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE discord_announcement ADD CONSTRAINT FK_4CF4BE0DB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE discord_sanction ADD CONSTRAINT FK_919984F75CCA315E FOREIGN KEY (site_user_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE discord_sanction ADD CONSTRAINT FK_919984F7FB8FE773 FOREIGN KEY (revoked_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE live_promotion ADD CONSTRAINT FK_7B0A1DA1A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE live_promotion ADD CONSTRAINT FK_7B0A1DA11844E6B7 FOREIGN KEY (server_id) REFERENCES server (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE discord_announcement DROP FOREIGN KEY FK_4CF4BE0DB03A8386');
        $this->addSql('ALTER TABLE discord_sanction DROP FOREIGN KEY FK_919984F75CCA315E');
        $this->addSql('ALTER TABLE discord_sanction DROP FOREIGN KEY FK_919984F7FB8FE773');
        $this->addSql('ALTER TABLE live_promotion DROP FOREIGN KEY FK_7B0A1DA1A76ED395');
        $this->addSql('ALTER TABLE live_promotion DROP FOREIGN KEY FK_7B0A1DA11844E6B7');
        $this->addSql('DROP TABLE discord_announcement');
        $this->addSql('DROP TABLE discord_command');
        $this->addSql('DROP TABLE discord_invite');
        $this->addSql('DROP TABLE discord_reaction_role');
        $this->addSql('DROP TABLE discord_sanction');
        $this->addSql('DROP TABLE live_promotion');
    }
}
