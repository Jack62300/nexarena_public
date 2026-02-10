<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260209203926 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE server (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, short_description VARCHAR(255) NOT NULL, full_description LONGTEXT DEFAULT NULL, presentation_image VARCHAR(255) DEFAULT NULL, ip VARCHAR(255) DEFAULT NULL, port INT DEFAULT NULL, connect_url VARCHAR(255) DEFAULT NULL, website VARCHAR(255) DEFAULT NULL, discord_url VARCHAR(255) DEFAULT NULL, twitter_url VARCHAR(255) DEFAULT NULL, twitch_channel VARCHAR(255) DEFAULT NULL, youtube_url VARCHAR(255) DEFAULT NULL, instagram_url VARCHAR(255) DEFAULT NULL, slots INT NOT NULL, is_private TINYINT NOT NULL, is_active TINYINT NOT NULL, is_approved TINYINT NOT NULL, banner VARCHAR(255) DEFAULT NULL, api_token VARCHAR(64) NOT NULL, webhook_enabled TINYINT NOT NULL, webhook_url VARCHAR(255) DEFAULT NULL, total_votes INT NOT NULL, monthly_votes INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, category_id INT NOT NULL, game_category_id INT DEFAULT NULL, server_type_id INT DEFAULT NULL, owner_id INT NOT NULL, UNIQUE INDEX UNIQ_5A6DD5F6989D9B62 (slug), UNIQUE INDEX UNIQ_5A6DD5F67BA2F5EB (api_token), INDEX IDX_5A6DD5F612469DE2 (category_id), INDEX IDX_5A6DD5F6CC13DFE0 (game_category_id), INDEX IDX_5A6DD5F6B732972F (server_type_id), INDEX IDX_5A6DD5F67E3C61F9 (owner_id), INDEX idx_server_monthly_votes (monthly_votes), INDEX idx_server_total_votes (total_votes), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE server_type (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, is_active TINYINT NOT NULL, position INT NOT NULL, category_id INT NOT NULL, UNIQUE INDEX UNIQ_75E26C9989D9B62 (slug), INDEX IDX_75E26C912469DE2 (category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE vote (id INT AUTO_INCREMENT NOT NULL, voter_ip VARCHAR(45) NOT NULL, voter_username VARCHAR(100) DEFAULT NULL, voted_at DATETIME NOT NULL, server_id INT NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_5A1085641844E6B7 (server_id), INDEX IDX_5A108564A76ED395 (user_id), INDEX idx_vote_server_ip (server_id, voter_ip), INDEX idx_vote_server_user (server_id, user_id), INDEX idx_vote_voted_at (voted_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE server ADD CONSTRAINT FK_5A6DD5F612469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE server ADD CONSTRAINT FK_5A6DD5F6CC13DFE0 FOREIGN KEY (game_category_id) REFERENCES game_category (id)');
        $this->addSql('ALTER TABLE server ADD CONSTRAINT FK_5A6DD5F6B732972F FOREIGN KEY (server_type_id) REFERENCES server_type (id)');
        $this->addSql('ALTER TABLE server ADD CONSTRAINT FK_5A6DD5F67E3C61F9 FOREIGN KEY (owner_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE server_type ADD CONSTRAINT FK_75E26C912469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE vote ADD CONSTRAINT FK_5A1085641844E6B7 FOREIGN KEY (server_id) REFERENCES server (id)');
        $this->addSql('ALTER TABLE vote ADD CONSTRAINT FK_5A108564A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE server DROP FOREIGN KEY FK_5A6DD5F612469DE2');
        $this->addSql('ALTER TABLE server DROP FOREIGN KEY FK_5A6DD5F6CC13DFE0');
        $this->addSql('ALTER TABLE server DROP FOREIGN KEY FK_5A6DD5F6B732972F');
        $this->addSql('ALTER TABLE server DROP FOREIGN KEY FK_5A6DD5F67E3C61F9');
        $this->addSql('ALTER TABLE server_type DROP FOREIGN KEY FK_75E26C912469DE2');
        $this->addSql('ALTER TABLE vote DROP FOREIGN KEY FK_5A1085641844E6B7');
        $this->addSql('ALTER TABLE vote DROP FOREIGN KEY FK_5A108564A76ED395');
        $this->addSql('DROP TABLE server');
        $this->addSql('DROP TABLE server_type');
        $this->addSql('DROP TABLE vote');
    }
}
