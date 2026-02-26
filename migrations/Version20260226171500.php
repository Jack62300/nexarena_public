<?php

     declare(strict_types=1);

     namespace DoctrineMigrations;

     use Doctrine\DBAL\Schema\Schema;
     use Doctrine\Migrations\AbstractMigration;

     final class Version20260226171500 extends AbstractMigration
     {
         public function getDescription(): string
         {
             return 'Add server_form_fields JSON column to game_category';
         }

         public function up(Schema $schema): void
         {
             $this->addSql('ALTER TABLE game_category ADD server_form_fields JSON DEFAULT NULL');
         }

         public function down(Schema $schema): void
         {
             $this->addSql('ALTER TABLE game_category DROP COLUMN server_form_fields');
         }
     }