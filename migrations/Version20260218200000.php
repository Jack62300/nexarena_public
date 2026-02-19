<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260218200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create blacklist_entry table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE blacklist_entry (
                id INT AUTO_INCREMENT NOT NULL,
                created_by_id INT DEFAULT NULL,
                type VARCHAR(20) NOT NULL,
                value VARCHAR(255) NOT NULL,
                reason VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                UNIQUE INDEX UNIQ_TYPE_VALUE (type, value),
                INDEX IDX_CREATED_BY (created_by_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');

        $this->addSql('
            ALTER TABLE blacklist_entry
                ADD CONSTRAINT FK_blacklist_entry_created_by
                FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE blacklist_entry DROP FOREIGN KEY FK_blacklist_entry_created_by');
        $this->addSql('DROP TABLE blacklist_entry');
    }
}
