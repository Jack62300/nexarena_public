<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260302120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add wheel_spin table and free_spins/last_free_spin_month columns to user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE wheel_spin (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            type VARCHAR(10) NOT NULL,
            section_index INT NOT NULL,
            prize_label VARCHAR(50) NOT NULL,
            nexbits_won INT NOT NULL,
            nexboost_won INT NOT NULL,
            is_jackpot TINYINT(1) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_wheel_spin_user (user_id),
            INDEX idx_wheel_spin_created (created_at),
            PRIMARY KEY(id),
            CONSTRAINT FK_wheel_spin_user FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE `user` ADD free_spins INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE `user` ADD last_free_spin_month VARCHAR(7) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE wheel_spin');
        $this->addSql('ALTER TABLE `user` DROP COLUMN free_spins');
        $this->addSql('ALTER TABLE `user` DROP COLUMN last_free_spin_month');
    }
}
