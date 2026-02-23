<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260223195038 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blacklist_entry CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE blacklist_entry RENAME INDEX idx_created_by TO IDX_752724CBB03A8386');
        $this->addSql('ALTER TABLE ip_ban CHANGE type type VARCHAR(10) NOT NULL, CHANGE expires_at expires_at DATETIME DEFAULT NULL, CHANGE is_active is_active TINYINT NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE revoked_at revoked_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE ip_ban RENAME INDEX idx_ip_ban_banned_by TO IDX_693A09FD386B8E7');
        $this->addSql('ALTER TABLE ip_ban RENAME INDEX idx_ip_ban_revoked_by TO IDX_693A09FDFB8FE773');
        $this->addSql('ALTER TABLE plugin_submission CHANGE reviewed_at reviewed_at DATETIME DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE plugin_submission RENAME INDEX idx_reviewed_by TO IDX_743F5488FC6B21F1');
        $this->addSql('ALTER TABLE plugin_submission RENAME INDEX idx_submitter TO IDX_743F5488352F83EF');
        $this->addSql('ALTER TABLE plugin_submission RENAME INDEX idx_linked_plugin TO IDX_743F5488FA96E4C4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blacklist_entry CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE blacklist_entry RENAME INDEX idx_752724cbb03a8386 TO IDX_CREATED_BY');
        $this->addSql('ALTER TABLE ip_ban CHANGE type type VARCHAR(10) DEFAULT \'permanent\' NOT NULL, CHANGE expires_at expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE is_active is_active TINYINT DEFAULT 1 NOT NULL, CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE revoked_at revoked_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE ip_ban RENAME INDEX idx_693a09fd386b8e7 TO IDX_IP_BAN_BANNED_BY');
        $this->addSql('ALTER TABLE ip_ban RENAME INDEX idx_693a09fdfb8fe773 TO IDX_IP_BAN_REVOKED_BY');
        $this->addSql('ALTER TABLE plugin_submission CHANGE reviewed_at reviewed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE plugin_submission RENAME INDEX idx_743f5488352f83ef TO IDX_SUBMITTER');
        $this->addSql('ALTER TABLE plugin_submission RENAME INDEX idx_743f5488fc6b21f1 TO IDX_REVIEWED_BY');
        $this->addSql('ALTER TABLE plugin_submission RENAME INDEX idx_743f5488fa96e4c4 TO IDX_LINKED_PLUGIN');
    }
}
