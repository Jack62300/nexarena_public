<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260223201505 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user ADD is_banned TINYINT NOT NULL, ADD ban_reason VARCHAR(500) DEFAULT NULL, ADD banned_at DATETIME DEFAULT NULL, ADD ban_expires_at DATETIME DEFAULT NULL, ADD banned_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649386B8E7 FOREIGN KEY (banned_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_8D93D649386B8E7 ON user (banned_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D649386B8E7');
        $this->addSql('DROP INDEX IDX_8D93D649386B8E7 ON `user`');
        $this->addSql('ALTER TABLE `user` DROP is_banned, DROP ban_reason, DROP banned_at, DROP ban_expires_at, DROP banned_by_id');
    }
}
