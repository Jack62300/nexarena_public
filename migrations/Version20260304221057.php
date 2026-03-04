<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260304221057 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE access_log CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE referral DROP INDEX UNIQ_referral_referred, ADD INDEX IDX_73079D00CFE2A98 (referred_id)');
        $this->addSql('ALTER TABLE referral DROP FOREIGN KEY `FK_referral_referrer`');
        $this->addSql('ALTER TABLE referral CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('DROP INDEX idx_referral_referrer ON referral');
        $this->addSql('CREATE INDEX IDX_73079D00798C22DB ON referral (referrer_id)');
        $this->addSql('ALTER TABLE referral ADD CONSTRAINT `FK_referral_referrer` FOREIGN KEY (referrer_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE server_favorite DROP FOREIGN KEY `FK_server_favorite_server`');
        $this->addSql('ALTER TABLE server_favorite DROP FOREIGN KEY `FK_server_favorite_user`');
        $this->addSql('ALTER TABLE server_favorite CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('DROP INDEX idx_server_favorite_server ON server_favorite');
        $this->addSql('CREATE INDEX IDX_AA600F651844E6B7 ON server_favorite (server_id)');
        $this->addSql('DROP INDEX idx_server_favorite_user ON server_favorite');
        $this->addSql('CREATE INDEX IDX_AA600F65A76ED395 ON server_favorite (user_id)');
        $this->addSql('ALTER TABLE server_favorite ADD CONSTRAINT `FK_server_favorite_server` FOREIGN KEY (server_id) REFERENCES server (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE server_favorite ADD CONSTRAINT `FK_server_favorite_user` FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE server_rating DROP FOREIGN KEY `FK_server_rating_server`');
        $this->addSql('ALTER TABLE server_rating DROP FOREIGN KEY `FK_server_rating_user`');
        $this->addSql('ALTER TABLE server_rating CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('DROP INDEX idx_server_rating_server ON server_rating');
        $this->addSql('CREATE INDEX IDX_A1F51FE41844E6B7 ON server_rating (server_id)');
        $this->addSql('DROP INDEX idx_server_rating_user ON server_rating');
        $this->addSql('CREATE INDEX IDX_A1F51FE4A76ED395 ON server_rating (user_id)');
        $this->addSql('ALTER TABLE server_rating ADD CONSTRAINT `FK_server_rating_server` FOREIGN KEY (server_id) REFERENCES server (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE server_rating ADD CONSTRAINT `FK_server_rating_user` FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY `FK_user_referred_by`');
        $this->addSql('ALTER TABLE user CHANGE password_reset_token_expires_at password_reset_token_expires_at DATETIME DEFAULT NULL');
        $this->addSql('DROP INDEX uniq_pwd_reset_token ON user');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D6496B7BA4B6 ON user (password_reset_token)');
        $this->addSql('DROP INDEX uniq_user_referral_code ON user');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D6496447454A ON user (referral_code)');
        $this->addSql('DROP INDEX fk_user_referred_by ON user');
        $this->addSql('CREATE INDEX IDX_8D93D649758C8114 ON user (referred_by_id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT `FK_user_referred_by` FOREIGN KEY (referred_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE wheel_prize CHANGE is_jackpot is_jackpot TINYINT NOT NULL');
        $this->addSql('DROP INDEX uniq_wheel_prize_position ON wheel_prize');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D6753843462CE4F5 ON wheel_prize (position)');
        $this->addSql('ALTER TABLE wheel_spin CHANGE created_at created_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE access_log CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE referral DROP INDEX IDX_73079D00CFE2A98, ADD UNIQUE INDEX UNIQ_referral_referred (referred_id)');
        $this->addSql('ALTER TABLE referral DROP FOREIGN KEY FK_73079D00798C22DB');
        $this->addSql('ALTER TABLE referral CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('DROP INDEX idx_73079d00798c22db ON referral');
        $this->addSql('CREATE INDEX IDX_referral_referrer ON referral (referrer_id)');
        $this->addSql('ALTER TABLE referral ADD CONSTRAINT FK_73079D00798C22DB FOREIGN KEY (referrer_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE server_favorite DROP FOREIGN KEY FK_AA600F651844E6B7');
        $this->addSql('ALTER TABLE server_favorite DROP FOREIGN KEY FK_AA600F65A76ED395');
        $this->addSql('ALTER TABLE server_favorite CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('DROP INDEX idx_aa600f65a76ed395 ON server_favorite');
        $this->addSql('CREATE INDEX IDX_server_favorite_user ON server_favorite (user_id)');
        $this->addSql('DROP INDEX idx_aa600f651844e6b7 ON server_favorite');
        $this->addSql('CREATE INDEX IDX_server_favorite_server ON server_favorite (server_id)');
        $this->addSql('ALTER TABLE server_favorite ADD CONSTRAINT FK_AA600F651844E6B7 FOREIGN KEY (server_id) REFERENCES server (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE server_favorite ADD CONSTRAINT FK_AA600F65A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE server_rating DROP FOREIGN KEY FK_A1F51FE41844E6B7');
        $this->addSql('ALTER TABLE server_rating DROP FOREIGN KEY FK_A1F51FE4A76ED395');
        $this->addSql('ALTER TABLE server_rating CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('DROP INDEX idx_a1f51fe4a76ed395 ON server_rating');
        $this->addSql('CREATE INDEX IDX_server_rating_user ON server_rating (user_id)');
        $this->addSql('DROP INDEX idx_a1f51fe41844e6b7 ON server_rating');
        $this->addSql('CREATE INDEX IDX_server_rating_server ON server_rating (server_id)');
        $this->addSql('ALTER TABLE server_rating ADD CONSTRAINT FK_A1F51FE41844E6B7 FOREIGN KEY (server_id) REFERENCES server (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE server_rating ADD CONSTRAINT FK_A1F51FE4A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D649758C8114');
        $this->addSql('ALTER TABLE `user` CHANGE password_reset_token_expires_at password_reset_token_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('DROP INDEX uniq_8d93d6496447454a ON `user`');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_user_referral_code ON `user` (referral_code)');
        $this->addSql('DROP INDEX idx_8d93d649758c8114 ON `user`');
        $this->addSql('CREATE INDEX FK_user_referred_by ON `user` (referred_by_id)');
        $this->addSql('DROP INDEX uniq_8d93d6496b7ba4b6 ON `user`');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_pwd_reset_token ON `user` (password_reset_token)');
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT FK_8D93D649758C8114 FOREIGN KEY (referred_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE wheel_prize CHANGE is_jackpot is_jackpot TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('DROP INDEX uniq_d6753843462ce4f5 ON wheel_prize');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_WHEEL_PRIZE_POSITION ON wheel_prize (position)');
        $this->addSql('ALTER TABLE wheel_spin CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
