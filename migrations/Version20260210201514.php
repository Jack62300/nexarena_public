<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260210201514 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE recruitment_application (id INT AUTO_INCREMENT NOT NULL, applicant_name VARCHAR(255) NOT NULL, applicant_email VARCHAR(255) NOT NULL, responses JSON NOT NULL, is_read TINYINT NOT NULL, created_at DATETIME NOT NULL, listing_id INT NOT NULL, applicant_user_id INT DEFAULT NULL, INDEX IDX_3EB2A123D12B766C (applicant_user_id), INDEX idx_application_listing (listing_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE recruitment_listing (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, image1 VARCHAR(255) DEFAULT NULL, image2 VARCHAR(255) DEFAULT NULL, status VARCHAR(20) DEFAULT \'draft\' NOT NULL, revision_reason LONGTEXT DEFAULT NULL, rejection_reason LONGTEXT DEFAULT NULL, form_fields JSON NOT NULL, is_active TINYINT NOT NULL, approved_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, server_id INT NOT NULL, author_id INT NOT NULL, approved_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_EF92D8F5989D9B62 (slug), INDEX IDX_EF92D8F51844E6B7 (server_id), INDEX IDX_EF92D8F5F675F31B (author_id), INDEX IDX_EF92D8F52D234F6A (approved_by_id), INDEX idx_recruitment_status (status), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE recruitment_application ADD CONSTRAINT FK_3EB2A123D4619D1A FOREIGN KEY (listing_id) REFERENCES recruitment_listing (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recruitment_application ADD CONSTRAINT FK_3EB2A123D12B766C FOREIGN KEY (applicant_user_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE recruitment_listing ADD CONSTRAINT FK_EF92D8F51844E6B7 FOREIGN KEY (server_id) REFERENCES server (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recruitment_listing ADD CONSTRAINT FK_EF92D8F5F675F31B FOREIGN KEY (author_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE recruitment_listing ADD CONSTRAINT FK_EF92D8F52D234F6A FOREIGN KEY (approved_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE recruitment_application DROP FOREIGN KEY FK_3EB2A123D4619D1A');
        $this->addSql('ALTER TABLE recruitment_application DROP FOREIGN KEY FK_3EB2A123D12B766C');
        $this->addSql('ALTER TABLE recruitment_listing DROP FOREIGN KEY FK_EF92D8F51844E6B7');
        $this->addSql('ALTER TABLE recruitment_listing DROP FOREIGN KEY FK_EF92D8F5F675F31B');
        $this->addSql('ALTER TABLE recruitment_listing DROP FOREIGN KEY FK_EF92D8F52D234F6A');
        $this->addSql('DROP TABLE recruitment_application');
        $this->addSql('DROP TABLE recruitment_listing');
    }
}
