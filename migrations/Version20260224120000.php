<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260224120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace badge system with achievement system (rename tables, add rarity + is_viewed)';
    }

    public function up(Schema $schema): void
    {
        // Rename badge → achievement
        $this->addSql('RENAME TABLE badge TO achievement');

        // Add rarity column (default: common)
        $this->addSql("ALTER TABLE achievement ADD rarity VARCHAR(20) NOT NULL DEFAULT 'common'");

        // Drop color column (replaced by rarity)
        $this->addSql('ALTER TABLE achievement DROP COLUMN color');

        // Rename user_badge → user_achievement and update FK column name
        $this->addSql('RENAME TABLE user_badge TO user_achievement');
        $this->addSql('ALTER TABLE user_achievement CHANGE badge_id achievement_id INT NOT NULL');

        // Add is_viewed column
        $this->addSql('ALTER TABLE user_achievement ADD is_viewed TINYINT(1) NOT NULL DEFAULT 0');

        // Update FK constraint name for clarity
        $this->addSql('ALTER TABLE user_achievement DROP FOREIGN KEY IF EXISTS FK_3AF36DC6F7A2C2FC');
        $this->addSql('ALTER TABLE user_achievement ADD CONSTRAINT FK_user_achievement_achievement FOREIGN KEY (achievement_id) REFERENCES achievement(id) ON DELETE CASCADE');

        // Rename unique index
        $this->addSql('ALTER TABLE user_achievement DROP INDEX IF EXISTS unique_user_badge');
        $this->addSql('ALTER TABLE user_achievement ADD UNIQUE INDEX unique_user_achievement (user_id, achievement_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_achievement DROP INDEX unique_user_achievement');
        $this->addSql('ALTER TABLE user_achievement ADD UNIQUE INDEX unique_user_badge (user_id, achievement_id)');
        $this->addSql('ALTER TABLE user_achievement DROP FOREIGN KEY FK_user_achievement_achievement');
        $this->addSql('ALTER TABLE user_achievement ADD CONSTRAINT FK_3AF36DC6F7A2C2FC FOREIGN KEY (achievement_id) REFERENCES achievement(id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_achievement DROP is_viewed');
        $this->addSql('ALTER TABLE user_achievement CHANGE achievement_id badge_id INT NOT NULL');
        $this->addSql('RENAME TABLE user_achievement TO user_badge');
        $this->addSql("ALTER TABLE achievement ADD color VARCHAR(7) DEFAULT NULL");
        $this->addSql('ALTER TABLE achievement DROP rarity');
        $this->addSql('RENAME TABLE achievement TO badge');
    }
}
