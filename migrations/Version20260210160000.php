<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260210160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing settings: vote_vpn_check_enabled, vote_require_platform, security_max_upload_size, security_allowed_origins';
    }

    public function up(Schema $schema): void
    {
        // Vote settings
        $this->addSql("INSERT IGNORE INTO setting (setting_key, value, type, label, description, category, position) VALUES ('vote_vpn_check_enabled', '1', 'boolean', 'Detection VPN/Proxy', 'Bloquer les votes provenant de VPN, proxies et Tor', 'votes', 5)");
        $this->addSql("INSERT IGNORE INTO setting (setting_key, value, type, label, description, category, position) VALUES ('vote_require_platform', '1', 'boolean', 'Plateforme requise (Discord/Steam)', 'Les votants doivent se connecter via Discord ou Steam pour voter', 'votes', 6)");

        // Security settings
        $this->addSql("INSERT IGNORE INTO setting (setting_key, value, type, label, description, category, position) VALUES ('security_max_upload_size', '5', 'number', 'Taille max upload (Mo)', 'Taille maximale des fichiers uploades en megaoctets (images, bannieres)', 'securite', 0)");
        $this->addSql("INSERT IGNORE INTO setting (setting_key, value, type, label, description, category, position) VALUES ('security_allowed_origins', '', 'textarea', 'Origines autorisees (CORS)', 'Domaines autorises pour les requetes cross-origin (un par ligne). Laissez vide pour tout autoriser.', 'securite', 1)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM setting WHERE setting_key IN ('vote_vpn_check_enabled', 'vote_require_platform', 'security_max_upload_size', 'security_allowed_origins')");
    }
}
