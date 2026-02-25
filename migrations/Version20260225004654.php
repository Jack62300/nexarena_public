<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260225004654 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE achievement RENAME INDEX uniq_fef0481d989d9b62 TO UNIQ_96737FF1989D9B62');
        $this->addSql('ALTER TABLE user_achievement DROP FOREIGN KEY `FK_user_achievement_achievement`');
        $this->addSql('ALTER TABLE user_achievement CHANGE is_viewed is_viewed TINYINT NOT NULL');
        $this->addSql('ALTER TABLE user_achievement RENAME INDEX idx_1c32b345a76ed395 TO IDX_3F68B664A76ED395');
        $this->addSql('ALTER TABLE user_achievement RENAME INDEX idx_1c32b345f7a2c2fc TO IDX_3F68B664B3EC99FE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE achievement RENAME INDEX uniq_96737ff1989d9b62 TO UNIQ_FEF0481D989D9B62');
        $this->addSql('ALTER TABLE user_achievement CHANGE is_viewed is_viewed TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE user_achievement RENAME INDEX idx_3f68b664b3ec99fe TO IDX_1C32B345F7A2C2FC');
        $this->addSql('ALTER TABLE user_achievement RENAME INDEX idx_3f68b664a76ed395 TO IDX_1C32B345A76ED395');
    }
}
