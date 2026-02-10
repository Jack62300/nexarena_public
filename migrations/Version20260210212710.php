<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260210212710 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(30) NOT NULL, title VARCHAR(255) NOT NULL, message VARCHAR(500) NOT NULL, link VARCHAR(500) DEFAULT NULL, is_read TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX idx_notification_user (user_id), INDEX idx_notification_read (user_id, is_read), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE recruitment_message (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, application_id INT NOT NULL, sender_id INT NOT NULL, INDEX IDX_922FA05EF624B39D (sender_id), INDEX idx_message_application (application_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recruitment_message ADD CONSTRAINT FK_922FA05E3E030ACD FOREIGN KEY (application_id) REFERENCES recruitment_application (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recruitment_message ADD CONSTRAINT FK_922FA05EF624B39D FOREIGN KEY (sender_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE recruitment_application ADD status VARCHAR(20) DEFAULT \'pending\' NOT NULL, ADD status_comment LONGTEXT DEFAULT NULL, ADD reviewed_at DATETIME DEFAULT NULL, ADD chat_enabled TINYINT DEFAULT 0 NOT NULL, ADD reviewed_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE recruitment_application ADD CONSTRAINT FK_3EB2A123FC6B21F1 FOREIGN KEY (reviewed_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_3EB2A123FC6B21F1 ON recruitment_application (reviewed_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAA76ED395');
        $this->addSql('ALTER TABLE recruitment_message DROP FOREIGN KEY FK_922FA05E3E030ACD');
        $this->addSql('ALTER TABLE recruitment_message DROP FOREIGN KEY FK_922FA05EF624B39D');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE recruitment_message');
        $this->addSql('ALTER TABLE recruitment_application DROP FOREIGN KEY FK_3EB2A123FC6B21F1');
        $this->addSql('DROP INDEX IDX_3EB2A123FC6B21F1 ON recruitment_application');
        $this->addSql('ALTER TABLE recruitment_application DROP status, DROP status_comment, DROP reviewed_at, DROP chat_enabled, DROP reviewed_by_id');
    }
}
