<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260304120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add server_rating, server_favorite, referral tables and related columns';
    }

    public function up(Schema $schema): void
    {
        // Ratings
        $this->addSql('CREATE TABLE server_rating (
            id INT AUTO_INCREMENT NOT NULL,
            server_id INT NOT NULL,
            user_id INT NOT NULL,
            rating SMALLINT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_server_rating_server (server_id),
            INDEX IDX_server_rating_user (user_id),
            UNIQUE INDEX UNIQ_server_user_rating (server_id, user_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_server_rating_server FOREIGN KEY (server_id) REFERENCES server (id) ON DELETE CASCADE,
            CONSTRAINT FK_server_rating_user FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE server ADD average_rating DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE server ADD rating_count INT DEFAULT 0 NOT NULL');

        // Favorites
        $this->addSql('CREATE TABLE server_favorite (
            id INT AUTO_INCREMENT NOT NULL,
            server_id INT NOT NULL,
            user_id INT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_server_favorite_server (server_id),
            INDEX IDX_server_favorite_user (user_id),
            UNIQUE INDEX UNIQ_server_user_favorite (server_id, user_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_server_favorite_server FOREIGN KEY (server_id) REFERENCES server (id) ON DELETE CASCADE,
            CONSTRAINT FK_server_favorite_user FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Referral
        $this->addSql('ALTER TABLE `user` ADD referral_code VARCHAR(12) DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD referred_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD referral_count INT DEFAULT 0 NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_user_referral_code ON `user` (referral_code)');
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT FK_user_referred_by FOREIGN KEY (referred_by_id) REFERENCES `user` (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE referral (
            id INT AUTO_INCREMENT NOT NULL,
            referrer_id INT NOT NULL,
            referred_id INT NOT NULL,
            reward_amount INT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_referral_referrer (referrer_id),
            UNIQUE INDEX UNIQ_referral_referred (referred_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_referral_referrer FOREIGN KEY (referrer_id) REFERENCES `user` (id) ON DELETE CASCADE,
            CONSTRAINT FK_referral_referred FOREIGN KEY (referred_id) REFERENCES `user` (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE referral');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_user_referred_by');
        $this->addSql('DROP INDEX UNIQ_user_referral_code ON `user`');
        $this->addSql('ALTER TABLE `user` DROP referral_code, DROP referred_by_id, DROP referral_count');
        $this->addSql('DROP TABLE server_favorite');
        $this->addSql('ALTER TABLE server DROP average_rating, DROP rating_count');
        $this->addSql('DROP TABLE server_rating');
    }
}
