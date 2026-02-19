<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260218210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add creator_name to plugin + create plugin_submission table';
    }

    public function up(Schema $schema): void
    {
        // Add creatorName to plugin
        $this->addSql('ALTER TABLE plugin ADD creator_name VARCHAR(100) DEFAULT NULL');

        // Create plugin_submission table
        $this->addSql('
            CREATE TABLE plugin_submission (
                id INT AUTO_INCREMENT NOT NULL,
                submitter_user_id INT DEFAULT NULL,
                reviewed_by_id INT DEFAULT NULL,
                linked_plugin_id INT DEFAULT NULL,
                plugin_name VARCHAR(100) NOT NULL,
                creator_name VARCHAR(100) NOT NULL,
                description VARCHAR(500) NOT NULL,
                game_description VARCHAR(150) NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                original_file_name VARCHAR(255) DEFAULT NULL,
                file_size INT DEFAULT NULL,
                status VARCHAR(20) NOT NULL,
                rejection_reason VARCHAR(1000) DEFAULT NULL,
                reviewed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                submitter_ip VARCHAR(45) DEFAULT NULL,
                created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                INDEX IDX_SUBMITTER (submitter_user_id),
                INDEX IDX_REVIEWED_BY (reviewed_by_id),
                INDEX IDX_LINKED_PLUGIN (linked_plugin_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');

        $this->addSql('ALTER TABLE plugin_submission ADD CONSTRAINT FK_sub_submitter FOREIGN KEY (submitter_user_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE plugin_submission ADD CONSTRAINT FK_sub_reviewed_by FOREIGN KEY (reviewed_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE plugin_submission ADD CONSTRAINT FK_sub_linked_plugin FOREIGN KEY (linked_plugin_id) REFERENCES plugin (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plugin_submission DROP FOREIGN KEY FK_sub_submitter');
        $this->addSql('ALTER TABLE plugin_submission DROP FOREIGN KEY FK_sub_reviewed_by');
        $this->addSql('ALTER TABLE plugin_submission DROP FOREIGN KEY FK_sub_linked_plugin');
        $this->addSql('DROP TABLE plugin_submission');
        $this->addSql('ALTER TABLE plugin DROP COLUMN creator_name');
    }
}
