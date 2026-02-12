<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260211033509 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE daily_random_boost (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, created_at DATETIME NOT NULL, server_id INT NOT NULL, INDEX IDX_EFB7CAD41844E6B7 (server_id), UNIQUE INDEX uniq_daily_boost_date (date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE vote_reward (id INT AUTO_INCREMENT NOT NULL, tokens_earned NUMERIC(8, 2) NOT NULL, multiplier NUMERIC(4, 2) NOT NULL, reason VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, vote_id INT NOT NULL, server_id INT NOT NULL, INDEX IDX_42360009A76ED395 (user_id), INDEX IDX_4236000972DCDAFC (vote_id), INDEX IDX_423600091844E6B7 (server_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE daily_random_boost ADD CONSTRAINT FK_EFB7CAD41844E6B7 FOREIGN KEY (server_id) REFERENCES server (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vote_reward ADD CONSTRAINT FK_42360009A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vote_reward ADD CONSTRAINT FK_4236000972DCDAFC FOREIGN KEY (vote_id) REFERENCES vote (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vote_reward ADD CONSTRAINT FK_423600091844E6B7 FOREIGN KEY (server_id) REFERENCES server (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX uniq_server_date ON featured_booking');
        $this->addSql('ALTER TABLE featured_booking ADD starts_at DATETIME NOT NULL, ADD ends_at DATETIME NOT NULL, DROP date');
        $this->addSql('CREATE INDEX idx_featured_starts_at ON featured_booking (starts_at)');
        $this->addSql('CREATE INDEX idx_featured_ends_at ON featured_booking (ends_at)');
        $this->addSql('ALTER TABLE user ADD pending_vote_tokens NUMERIC(8, 2) DEFAULT \'0.00\' NOT NULL');
        $this->addSql('ALTER TABLE vote ADD browser_fingerprint VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_vote_fingerprint ON vote (browser_fingerprint)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE daily_random_boost DROP FOREIGN KEY FK_EFB7CAD41844E6B7');
        $this->addSql('ALTER TABLE vote_reward DROP FOREIGN KEY FK_42360009A76ED395');
        $this->addSql('ALTER TABLE vote_reward DROP FOREIGN KEY FK_4236000972DCDAFC');
        $this->addSql('ALTER TABLE vote_reward DROP FOREIGN KEY FK_423600091844E6B7');
        $this->addSql('DROP TABLE daily_random_boost');
        $this->addSql('DROP TABLE vote_reward');
        $this->addSql('DROP INDEX idx_featured_starts_at ON featured_booking');
        $this->addSql('DROP INDEX idx_featured_ends_at ON featured_booking');
        $this->addSql('ALTER TABLE featured_booking ADD date DATE NOT NULL, DROP starts_at, DROP ends_at');
        $this->addSql('CREATE UNIQUE INDEX uniq_server_date ON featured_booking (server_id, date)');
        $this->addSql('ALTER TABLE `user` DROP pending_vote_tokens');
        $this->addSql('DROP INDEX idx_vote_fingerprint ON vote');
        $this->addSql('ALTER TABLE vote DROP browser_fingerprint');
    }
}
