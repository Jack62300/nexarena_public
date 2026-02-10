<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260210163000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add API keys settings (moved from .env to admin config)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT IGNORE INTO setting (setting_key, value, type, label, description, category, position) VALUES ('google_client_id', '', 'text', 'Google Client ID', 'Client ID de l''application Google OAuth (console.cloud.google.com)', 'api_keys', 0)");
        $this->addSql("INSERT IGNORE INTO setting (setting_key, value, type, label, description, category, position) VALUES ('google_client_secret', '', 'text', 'Google Client Secret', 'Client Secret de l''application Google OAuth', 'api_keys', 1)");
        $this->addSql("INSERT IGNORE INTO setting (setting_key, value, type, label, description, category, position) VALUES ('discord_client_id', '', 'text', 'Discord Client ID', 'Client ID de l''application Discord OAuth (discord.com/developers)', 'api_keys', 2)");
        $this->addSql("INSERT IGNORE INTO setting (setting_key, value, type, label, description, category, position) VALUES ('discord_client_secret', '', 'text', 'Discord Client Secret', 'Client Secret de l''application Discord OAuth', 'api_keys', 3)");
        $this->addSql("INSERT IGNORE INTO setting (setting_key, value, type, label, description, category, position) VALUES ('twitch_client_id', '', 'text', 'Twitch Client ID', 'Client ID de l''application Twitch OAuth (dev.twitch.tv)', 'api_keys', 4)");
        $this->addSql("INSERT IGNORE INTO setting (setting_key, value, type, label, description, category, position) VALUES ('twitch_client_secret', '', 'text', 'Twitch Client Secret', 'Client Secret de l''application Twitch OAuth', 'api_keys', 5)");
        $this->addSql("INSERT IGNORE INTO setting (setting_key, value, type, label, description, category, position) VALUES ('steam_api_key', '', 'text', 'Steam API Key', 'Cle API Steam (steamcommunity.com/dev/apikey)', 'api_keys', 6)");
        $this->addSql("INSERT IGNORE INTO setting (setting_key, value, type, label, description, category, position) VALUES ('ipgeolocation_api_key', '', 'text', 'IPGeolocation API Key', 'Cle API ipgeolocation.io pour la detection VPN/Proxy', 'api_keys', 7)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM setting WHERE setting_key IN ('google_client_id', 'google_client_secret', 'discord_client_id', 'discord_client_secret', 'twitch_client_id', 'twitch_client_secret', 'steam_api_key', 'ipgeolocation_api_key')");
    }
}
