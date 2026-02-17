<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260216234111 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE badge (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, slug VARCHAR(120) NOT NULL, description LONGTEXT DEFAULT NULL, icon_file_name VARCHAR(255) DEFAULT NULL, color VARCHAR(7) DEFAULT NULL, criteria JSON DEFAULT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_FEF0481D989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_badge (id INT AUTO_INCREMENT NOT NULL, awarded_at DATETIME NOT NULL, user_id INT NOT NULL, badge_id INT NOT NULL, INDEX IDX_1C32B345A76ED395 (user_id), INDEX IDX_1C32B345F7A2C2FC (badge_id), UNIQUE INDEX unique_user_badge (user_id, badge_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE user_badge ADD CONSTRAINT FK_1C32B345A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_badge ADD CONSTRAINT FK_1C32B345F7A2C2FC FOREIGN KEY (badge_id) REFERENCES badge (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE game_category DROP form_type');
        $this->addSql('ALTER TABLE server DROP password');
        $this->addSql('ALTER TABLE user ADD bio LONGTEXT DEFAULT NULL, ADD discord_username VARCHAR(100) DEFAULT NULL, ADD steam_username VARCHAR(100) DEFAULT NULL, ADD twitch_username VARCHAR(100) DEFAULT NULL, ADD game_usernames JSON DEFAULT NULL, ADD profile_visibility JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_badge DROP FOREIGN KEY FK_1C32B345A76ED395');
        $this->addSql('ALTER TABLE user_badge DROP FOREIGN KEY FK_1C32B345F7A2C2FC');
        $this->addSql('DROP TABLE badge');
        $this->addSql('DROP TABLE user_badge');
        $this->addSql('ALTER TABLE game_category ADD form_type VARCHAR(30) DEFAULT \'game_server\'');
        $this->addSql('ALTER TABLE server ADD password VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` DROP bio, DROP discord_username, DROP steam_username, DROP twitch_username, DROP game_usernames, DROP profile_visibility');
    }
}
