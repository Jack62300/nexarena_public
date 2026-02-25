<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260225022648 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reward_nexbits and reward_nexboost columns to achievement table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE achievement ADD reward_nexbits INT DEFAULT 0 NOT NULL, ADD reward_nexboost INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE achievement DROP reward_nexbits, DROP reward_nexboost');
    }
}
