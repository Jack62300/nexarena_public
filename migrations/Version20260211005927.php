<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260211005927 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE featured_booking (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, boost_tokens_used INT NOT NULL, created_at DATETIME NOT NULL, server_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_41CDFD931844E6B7 (server_id), INDEX IDX_41CDFD93A76ED395 (user_id), UNIQUE INDEX uniq_server_date (server_id, date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE premium_plan (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, slug VARCHAR(120) NOT NULL, description LONGTEXT NOT NULL, price NUMERIC(10, 2) NOT NULL, currency VARCHAR(3) DEFAULT \'EUR\' NOT NULL, icon_file_name VARCHAR(255) DEFAULT NULL, tokens_given INT NOT NULL, boost_tokens_given INT NOT NULL, is_active TINYINT NOT NULL, position INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_E0F0818D989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE server_premium_feature (id INT AUTO_INCREMENT NOT NULL, feature VARCHAR(30) NOT NULL, tokens_spent INT NOT NULL, unlocked_at DATETIME NOT NULL, server_id INT NOT NULL, unlocked_by_id INT NOT NULL, INDEX IDX_B17DB52A1844E6B7 (server_id), INDEX IDX_B17DB52A371F3A6E (unlocked_by_id), UNIQUE INDEX uniq_server_feature (server_id, feature), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE transaction (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, amount NUMERIC(10, 2) DEFAULT NULL, currency VARCHAR(3) DEFAULT NULL, tokens_amount INT NOT NULL, boost_tokens_amount INT NOT NULL, paypal_order_id VARCHAR(255) DEFAULT NULL, paypal_status VARCHAR(50) DEFAULT NULL, description VARCHAR(500) DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, plan_id INT DEFAULT NULL, INDEX IDX_723705D1A76ED395 (user_id), INDEX IDX_723705D1E899029B (plan_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE featured_booking ADD CONSTRAINT FK_41CDFD931844E6B7 FOREIGN KEY (server_id) REFERENCES server (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE featured_booking ADD CONSTRAINT FK_41CDFD93A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE server_premium_feature ADD CONSTRAINT FK_B17DB52A1844E6B7 FOREIGN KEY (server_id) REFERENCES server (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE server_premium_feature ADD CONSTRAINT FK_B17DB52A371F3A6E FOREIGN KEY (unlocked_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1E899029B FOREIGN KEY (plan_id) REFERENCES premium_plan (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE user ADD token_balance INT NOT NULL, ADD boost_token_balance INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE featured_booking DROP FOREIGN KEY FK_41CDFD931844E6B7');
        $this->addSql('ALTER TABLE featured_booking DROP FOREIGN KEY FK_41CDFD93A76ED395');
        $this->addSql('ALTER TABLE server_premium_feature DROP FOREIGN KEY FK_B17DB52A1844E6B7');
        $this->addSql('ALTER TABLE server_premium_feature DROP FOREIGN KEY FK_B17DB52A371F3A6E');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D1A76ED395');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D1E899029B');
        $this->addSql('DROP TABLE featured_booking');
        $this->addSql('DROP TABLE premium_plan');
        $this->addSql('DROP TABLE server_premium_feature');
        $this->addSql('DROP TABLE transaction');
        $this->addSql('ALTER TABLE `user` DROP token_balance, DROP boost_token_balance');
    }
}
