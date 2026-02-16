<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260213141318 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE server ADD token_balance INT NOT NULL, ADD boost_token_balance INT NOT NULL');
        $this->addSql('ALTER TABLE transaction ADD server_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D11844E6B7 FOREIGN KEY (server_id) REFERENCES server (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_723705D11844E6B7 ON transaction (server_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE server DROP token_balance, DROP boost_token_balance');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D11844E6B7');
        $this->addSql('DROP INDEX IDX_723705D11844E6B7 ON transaction');
        $this->addSql('ALTER TABLE transaction DROP server_id');
    }
}
