<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260218181924 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add icon field to game_category table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game_category ADD icon VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game_category DROP COLUMN icon');
    }
}
