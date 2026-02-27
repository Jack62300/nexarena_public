<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260227190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make recruitment_listing.server_id nullable (server optional on listings)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recruitment_listing DROP FOREIGN KEY FK_EF92D8F51844E6B7');
        $this->addSql('ALTER TABLE recruitment_listing MODIFY server_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE recruitment_listing ADD CONSTRAINT FK_EF92D8F51844E6B7 FOREIGN KEY (server_id) REFERENCES server (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recruitment_listing DROP FOREIGN KEY FK_EF92D8F51844E6B7');
        $this->addSql('DELETE FROM recruitment_listing WHERE server_id IS NULL');
        $this->addSql('ALTER TABLE recruitment_listing MODIFY server_id INT NOT NULL');
        $this->addSql('ALTER TABLE recruitment_listing ADD CONSTRAINT FK_EF92D8F51844E6B7 FOREIGN KEY (server_id) REFERENCES server (id) ON DELETE CASCADE');
    }
}
