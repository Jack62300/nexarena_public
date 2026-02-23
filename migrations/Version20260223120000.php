<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260223120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create ip_ban table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE ip_ban (
                id           INT AUTO_INCREMENT NOT NULL,
                banned_by_id INT DEFAULT NULL,
                revoked_by_id INT DEFAULT NULL,
                ip_address   VARCHAR(45) NOT NULL,
                type         VARCHAR(10) NOT NULL DEFAULT 'permanent',
                duration     INT DEFAULT NULL,
                duration_unit VARCHAR(10) DEFAULT NULL,
                expires_at   DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                reason       VARCHAR(500) DEFAULT NULL,
                is_active    TINYINT(1) NOT NULL DEFAULT 1,
                created_at   DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                revoked_at   DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX idx_ip_ban_ip (ip_address),
                INDEX idx_ip_ban_expires (expires_at),
                INDEX IDX_IP_BAN_BANNED_BY (banned_by_id),
                INDEX IDX_IP_BAN_REVOKED_BY (revoked_by_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE ip_ban
                ADD CONSTRAINT FK_IP_BAN_BANNED_BY  FOREIGN KEY (banned_by_id)  REFERENCES user (id) ON DELETE SET NULL,
                ADD CONSTRAINT FK_IP_BAN_REVOKED_BY FOREIGN KEY (revoked_by_id) REFERENCES user (id) ON DELETE SET NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ip_ban DROP FOREIGN KEY FK_IP_BAN_BANNED_BY');
        $this->addSql('ALTER TABLE ip_ban DROP FOREIGN KEY FK_IP_BAN_REVOKED_BY');
        $this->addSql('DROP TABLE ip_ban');
    }
}
