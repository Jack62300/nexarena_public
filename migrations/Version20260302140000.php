<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260302140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create wheel_prize table for configurable wheel prizes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE wheel_prize (
            id INT AUTO_INCREMENT NOT NULL,
            position INT NOT NULL,
            label VARCHAR(50) NOT NULL,
            nexbits INT NOT NULL,
            nexboost INT NOT NULL,
            weight INT NOT NULL,
            color VARCHAR(7) NOT NULL,
            is_jackpot TINYINT(1) NOT NULL DEFAULT 0,
            UNIQUE INDEX UNIQ_WHEEL_PRIZE_POSITION (position),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE wheel_prize');
    }
}
