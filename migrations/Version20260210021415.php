<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260210021415 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE monthly_battle (id INT AUTO_INCREMENT NOT NULL, month INT NOT NULL, year INT NOT NULL, servers_data JSON NOT NULL, premium_awarded TINYINT NOT NULL, created_at DATETIME NOT NULL, winner_id INT DEFAULT NULL, INDEX IDX_F03984FF5DFCD4B8 (winner_id), UNIQUE INDEX uniq_monthly_battle_month_year (month, year), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE monthly_battle ADD CONSTRAINT FK_F03984FF5DFCD4B8 FOREIGN KEY (winner_id) REFERENCES server (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE vote ADD discord_id VARCHAR(100) DEFAULT NULL, ADD steam_id VARCHAR(100) DEFAULT NULL, ADD vote_provider VARCHAR(20) DEFAULT NULL, ADD vpn_checked TINYINT NOT NULL');
        $this->addSql('CREATE INDEX idx_vote_discord_id ON vote (discord_id)');
        $this->addSql('CREATE INDEX idx_vote_steam_id ON vote (steam_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE monthly_battle DROP FOREIGN KEY FK_F03984FF5DFCD4B8');
        $this->addSql('DROP TABLE monthly_battle');
        $this->addSql('DROP INDEX idx_vote_discord_id ON vote');
        $this->addSql('DROP INDEX idx_vote_steam_id ON vote');
        $this->addSql('ALTER TABLE vote DROP discord_id, DROP steam_id, DROP vote_provider, DROP vpn_checked');
    }
}
