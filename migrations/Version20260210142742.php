<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260210142742 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE plugin (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, slug VARCHAR(120) NOT NULL, description LONGTEXT NOT NULL, long_description LONGTEXT DEFAULT NULL, platform VARCHAR(50) NOT NULL, category VARCHAR(30) NOT NULL, version VARCHAR(20) NOT NULL, file_name VARCHAR(255) DEFAULT NULL, icon_file_name VARCHAR(255) DEFAULT NULL, file_size INT DEFAULT NULL, download_count INT NOT NULL, virus_total_status VARCHAR(20) NOT NULL, virus_total_analysis_id VARCHAR(255) DEFAULT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_E96E2794989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql("INSERT INTO setting (setting_key, value, type, label, description, category, position) VALUES ('virustotal_api_key', '', 'text', 'Cle API VirusTotal', 'Cle API pour scanner les fichiers uploades', 'plugins', 0)");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE plugin');
    }
}
