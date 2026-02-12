<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260211194909 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE banned_word (id INT AUTO_INCREMENT NOT NULL, word VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL, added_by_id INT DEFAULT NULL, INDEX IDX_1EC7C5FD55B127A4 (added_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE discord_ticket (id INT AUTO_INCREMENT NOT NULL, discord_user_id VARCHAR(20) NOT NULL, discord_username VARCHAR(100) NOT NULL, category VARCHAR(50) NOT NULL, subject VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, discord_channel_id VARCHAR(20) DEFAULT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, closed_at DATETIME DEFAULT NULL, closed_by VARCHAR(100) DEFAULT NULL, site_user_id INT DEFAULT NULL, INDEX IDX_EA985CF5CCA315E (site_user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE discord_ticket_message (id INT AUTO_INCREMENT NOT NULL, author_discord_id VARCHAR(20) NOT NULL, author_username VARCHAR(100) NOT NULL, author_is_staff TINYINT NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, ticket_id INT NOT NULL, INDEX IDX_3A7EBF8B700047D2 (ticket_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE banned_word ADD CONSTRAINT FK_1EC7C5FD55B127A4 FOREIGN KEY (added_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE discord_ticket ADD CONSTRAINT FK_EA985CF5CCA315E FOREIGN KEY (site_user_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE discord_ticket_message ADD CONSTRAINT FK_3A7EBF8B700047D2 FOREIGN KEY (ticket_id) REFERENCES discord_ticket (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user ADD is_discord_guild_member TINYINT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE banned_word DROP FOREIGN KEY FK_1EC7C5FD55B127A4');
        $this->addSql('ALTER TABLE discord_ticket DROP FOREIGN KEY FK_EA985CF5CCA315E');
        $this->addSql('ALTER TABLE discord_ticket_message DROP FOREIGN KEY FK_3A7EBF8B700047D2');
        $this->addSql('DROP TABLE banned_word');
        $this->addSql('DROP TABLE discord_ticket');
        $this->addSql('DROP TABLE discord_ticket_message');
        $this->addSql('ALTER TABLE `user` DROP is_discord_guild_member');
    }
}
