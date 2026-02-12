<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260211170005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE rating (id INT AUTO_INCREMENT NOT NULL, score SMALLINT NOT NULL, created_at DATETIME NOT NULL, server_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_D88926221844E6B7 (server_id), INDEX IDX_D8892622A76ED395 (user_id), UNIQUE INDEX unique_server_user_rating (server_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE server_tag (server_id INT NOT NULL, tag_id INT NOT NULL, INDEX IDX_3D40BDD91844E6B7 (server_id), INDEX IDX_3D40BDD9BAD26311 (tag_id), PRIMARY KEY (server_id, tag_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tag (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, slug VARCHAR(50) NOT NULL, color VARCHAR(7) DEFAULT NULL, is_active TINYINT NOT NULL, position INT NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_389B783989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE rating ADD CONSTRAINT FK_D88926221844E6B7 FOREIGN KEY (server_id) REFERENCES server (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rating ADD CONSTRAINT FK_D8892622A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE server_tag ADD CONSTRAINT FK_3D40BDD91844E6B7 FOREIGN KEY (server_id) REFERENCES server (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE server_tag ADD CONSTRAINT FK_3D40BDD9BAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE server ADD click_count INT NOT NULL');
        $this->addSql('CREATE INDEX idx_server_click_count ON server (click_count)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rating DROP FOREIGN KEY FK_D88926221844E6B7');
        $this->addSql('ALTER TABLE rating DROP FOREIGN KEY FK_D8892622A76ED395');
        $this->addSql('ALTER TABLE server_tag DROP FOREIGN KEY FK_3D40BDD91844E6B7');
        $this->addSql('ALTER TABLE server_tag DROP FOREIGN KEY FK_3D40BDD9BAD26311');
        $this->addSql('DROP TABLE rating');
        $this->addSql('DROP TABLE server_tag');
        $this->addSql('DROP TABLE tag');
        $this->addSql('DROP INDEX idx_server_click_count ON server');
        $this->addSql('ALTER TABLE server DROP click_count');
    }
}
