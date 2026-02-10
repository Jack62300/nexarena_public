<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260210122357 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE server_collaborator (id INT AUTO_INCREMENT NOT NULL, permissions JSON NOT NULL, added_at DATETIME NOT NULL, server_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_712423D91844E6B7 (server_id), INDEX IDX_712423D9A76ED395 (user_id), UNIQUE INDEX uniq_server_user (server_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE server_collaborator ADD CONSTRAINT FK_712423D91844E6B7 FOREIGN KEY (server_id) REFERENCES server (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE server_collaborator ADD CONSTRAINT FK_712423D9A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE server_collaborator DROP FOREIGN KEY FK_712423D91844E6B7');
        $this->addSql('ALTER TABLE server_collaborator DROP FOREIGN KEY FK_712423D9A76ED395');
        $this->addSql('DROP TABLE server_collaborator');
    }
}
