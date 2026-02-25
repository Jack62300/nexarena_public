<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260225222120 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_twitch_subscription (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(20) NOT NULL, expires_at DATETIME NOT NULL, auto_renew TINYINT NOT NULL, payment_method VARCHAR(10) DEFAULT \'nexbits\' NOT NULL, created_at DATETIME NOT NULL, renewed_at DATETIME DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX uniq_user_twitch_sub (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE user_twitch_subscription ADD CONSTRAINT FK_4A0146D5A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_twitch_subscription DROP FOREIGN KEY FK_4A0146D5A76ED395');
        $this->addSql('DROP TABLE user_twitch_subscription');
    }
}
