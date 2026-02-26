<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260227180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la table access_log pour les logs d\'accès VPN/pays (IpAccessListener).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE access_log (
                id           INT AUTO_INCREMENT NOT NULL,
                ip           VARCHAR(45)  NOT NULL,
                remote_addr  VARCHAR(45)  DEFAULT NULL,
                path         VARCHAR(255) NOT NULL,
                method       VARCHAR(10)  NOT NULL,
                country_code VARCHAR(2)   DEFAULT NULL,
                vpn_detected TINYINT(1)   DEFAULT NULL,
                fraud_score  INT          DEFAULT NULL,
                trusted      TINYINT(1)   NOT NULL,
                blocked      TINYINT(1)   NOT NULL,
                block_reason VARCHAR(30)  DEFAULT NULL,
                user_agent   VARCHAR(500) DEFAULT NULL,
                created_at   DATETIME     NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                PRIMARY KEY (id),
                INDEX idx_al_created       (created_at),
                INDEX idx_al_ip            (ip),
                INDEX idx_al_blocked_created (blocked, created_at)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE access_log');
    }
}
