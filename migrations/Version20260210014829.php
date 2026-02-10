<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260210014829 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY `FK_9474526C1844E6B7`');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C1844E6B7 FOREIGN KEY (server_id) REFERENCES server (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vote DROP FOREIGN KEY `FK_5A1085641844E6B7`');
        $this->addSql('ALTER TABLE vote ADD CONSTRAINT FK_5A1085641844E6B7 FOREIGN KEY (server_id) REFERENCES server (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C1844E6B7');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT `FK_9474526C1844E6B7` FOREIGN KEY (server_id) REFERENCES server (id)');
        $this->addSql('ALTER TABLE vote DROP FOREIGN KEY FK_5A1085641844E6B7');
        $this->addSql('ALTER TABLE vote ADD CONSTRAINT `FK_5A1085641844E6B7` FOREIGN KEY (server_id) REFERENCES server (id)');
    }
}
