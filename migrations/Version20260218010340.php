<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260218010340 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE server_daily_stat (id INT AUTO_INCREMENT NOT NULL, stat_date DATE NOT NULL, page_views INT DEFAULT 0 NOT NULL, server_id INT NOT NULL, INDEX IDX_B67170131844E6B7 (server_id), UNIQUE INDEX uniq_server_date (server_id, stat_date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE server_daily_stat ADD CONSTRAINT FK_B67170131844E6B7 FOREIGN KEY (server_id) REFERENCES server (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE server_daily_stat DROP FOREIGN KEY FK_B67170131844E6B7');
        $this->addSql('DROP TABLE server_daily_stat');
    }
}
