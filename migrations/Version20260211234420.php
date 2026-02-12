<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260211234420 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE featured_booking ADD scope VARCHAR(20) DEFAULT \'homepage\' NOT NULL, ADD position SMALLINT DEFAULT 1 NOT NULL, ADD game_category_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE featured_booking ADD CONSTRAINT FK_41CDFD93CC13DFE0 FOREIGN KEY (game_category_id) REFERENCES game_category (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_41CDFD93CC13DFE0 ON featured_booking (game_category_id)');
        $this->addSql('CREATE INDEX idx_featured_scope_pos_range ON featured_booking (scope, position, starts_at, ends_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE featured_booking DROP FOREIGN KEY FK_41CDFD93CC13DFE0');
        $this->addSql('DROP INDEX IDX_41CDFD93CC13DFE0 ON featured_booking');
        $this->addSql('DROP INDEX idx_featured_scope_pos_range ON featured_booking');
        $this->addSql('ALTER TABLE featured_booking DROP scope, DROP position, DROP game_category_id');
    }
}
