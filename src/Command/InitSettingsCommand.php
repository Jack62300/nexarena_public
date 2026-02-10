<?php

namespace App\Command;

use App\Entity\Setting;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:init-settings',
    description: 'Initialiser les parametres du site par defaut',
)]
class InitSettingsCommand extends Command
{
    private const DEFAULT_SETTINGS = [
        // ========== GENERAL ==========
        ['key' => 'site_name', 'value' => 'Nexarena', 'type' => 'text', 'label' => 'Nom du site', 'description' => 'Le nom affiche dans le header, le footer et les titres de pages', 'category' => 'general', 'position' => 0],
        ['key' => 'site_description', 'value' => 'Classement et listing de serveurs prives de jeux videos', 'type' => 'textarea', 'label' => 'Description du site', 'description' => 'Description courte du site utilisee dans le footer et les meta', 'category' => 'general', 'position' => 1],
        ['key' => 'site_logo', 'value' => 'assets/img/logo/logo.svg', 'type' => 'image', 'label' => 'Logo du site', 'description' => 'Logo affiche dans le header (recommande: SVG ou PNG transparent, 200x50px)', 'category' => 'general', 'position' => 2],
        ['key' => 'site_favicon', 'value' => 'assets/img/logo/icon.svg', 'type' => 'image', 'label' => 'Favicon', 'description' => 'Icone affichee dans l\'onglet du navigateur (recommande: SVG ou PNG 32x32px)', 'category' => 'general', 'position' => 3],
        ['key' => 'site_email', 'value' => 'contact@nexarena.com', 'type' => 'text', 'label' => 'Email de contact', 'description' => 'Adresse email affichee pour le contact', 'category' => 'general', 'position' => 4],
        ['key' => 'site_accent_color', 'value' => '#45f882', 'type' => 'color', 'label' => 'Couleur principale', 'description' => 'Couleur d\'accent du site (boutons, liens, etc.)', 'category' => 'general', 'position' => 5],
        ['key' => 'maintenance_mode', 'value' => '0', 'type' => 'boolean', 'label' => 'Mode maintenance', 'description' => 'Activer le mode maintenance (seuls les admins peuvent acceder au site)', 'category' => 'general', 'position' => 6],

        // ========== BANNIERE / ACCUEIL ==========
        ['key' => 'banner_title', 'value' => 'Nexarena', 'type' => 'text', 'label' => 'Titre de la banniere', 'description' => 'Titre principal affiche dans la banniere de la page d\'accueil', 'category' => 'banner', 'position' => 0],
        ['key' => 'banner_subtitle', 'value' => 'Trouvez et votez pour les meilleurs serveurs de jeux', 'type' => 'text', 'label' => 'Sous-titre de la banniere', 'description' => 'Texte affiche sous le titre principal', 'category' => 'banner', 'position' => 1],
        ['key' => 'banner_text_stroke', 'value' => 'Nexarena', 'type' => 'text', 'label' => 'Texte en fond (text-stroke)', 'description' => 'Grand texte decoratif en arriere-plan de la banniere', 'category' => 'banner', 'position' => 2],
        ['key' => 'banner_bg_image', 'value' => '', 'type' => 'image', 'label' => 'Image de fond banniere', 'description' => 'Image de fond de la banniere (laissez vide pour l\'image par defaut)', 'category' => 'banner', 'position' => 3],
        ['key' => 'banner_cta_text', 'value' => 'Ajouter mon serveur', 'type' => 'text', 'label' => 'Texte du bouton CTA', 'description' => 'Texte du bouton d\'action dans la banniere', 'category' => 'banner', 'position' => 4],
        ['key' => 'banner_cta_url', 'value' => '#', 'type' => 'url', 'label' => 'Lien du bouton CTA', 'description' => 'URL du bouton d\'action (ex: /serveurs/ajouter)', 'category' => 'banner', 'position' => 5],
        ['key' => 'homepage_categories_title', 'value' => 'Categories de jeux', 'type' => 'text', 'label' => 'Titre section categories', 'description' => 'Titre de la section categories sur la page d\'accueil', 'category' => 'banner', 'position' => 6],
        ['key' => 'homepage_categories_subtitle', 'value' => 'Explorez les classements par jeu', 'type' => 'text', 'label' => 'Sous-titre section categories', 'description' => null, 'category' => 'banner', 'position' => 7],
        ['key' => 'homepage_news_title', 'value' => 'Dernieres actualites', 'type' => 'text', 'label' => 'Titre section actualites', 'description' => 'Titre de la section actualites sur la page d\'accueil', 'category' => 'banner', 'position' => 8],
        ['key' => 'homepage_news_subtitle', 'value' => 'Restez informe des dernieres nouvelles', 'type' => 'text', 'label' => 'Sous-titre section actualites', 'description' => null, 'category' => 'banner', 'position' => 9],

        // ========== SEO ==========
        ['key' => 'seo_title', 'value' => 'Nexarena - Classement de Serveurs de Jeux', 'type' => 'text', 'label' => 'Titre SEO (meta title)', 'description' => 'Titre affiche dans l\'onglet du navigateur et les resultats Google', 'category' => 'seo', 'position' => 0],
        ['key' => 'seo_description', 'value' => 'Nexarena - Classement et listing de serveurs prives de jeux videos. Votez pour vos serveurs preferes.', 'type' => 'textarea', 'label' => 'Description SEO (meta description)', 'description' => 'Description pour les moteurs de recherche (max 160 caracteres)', 'category' => 'seo', 'position' => 1],
        ['key' => 'seo_keywords', 'value' => 'serveur, jeux, classement, vote, gaming, minecraft, gmod, ark, rust', 'type' => 'textarea', 'label' => 'Mots-cles SEO', 'description' => 'Mots-cles separes par des virgules', 'category' => 'seo', 'position' => 2],
        ['key' => 'seo_og_image', 'value' => '', 'type' => 'image', 'label' => 'Image Open Graph', 'description' => 'Image affichee lors du partage sur les reseaux sociaux (recommande: 1200x630px)', 'category' => 'seo', 'position' => 3],
        ['key' => 'google_analytics_id', 'value' => '', 'type' => 'text', 'label' => 'Google Analytics ID', 'description' => 'ID Google Analytics (ex: G-XXXXXXXXXX). Laissez vide pour desactiver.', 'category' => 'seo', 'position' => 4],

        // ========== RESEAUX SOCIAUX ==========
        ['key' => 'social_discord', 'value' => '', 'type' => 'url', 'label' => 'Lien Discord', 'description' => 'URL d\'invitation vers votre serveur Discord', 'category' => 'social', 'position' => 0],
        ['key' => 'social_twitter', 'value' => '', 'type' => 'url', 'label' => 'Lien Twitter / X', 'description' => 'URL de votre profil Twitter/X', 'category' => 'social', 'position' => 1],
        ['key' => 'social_youtube', 'value' => '', 'type' => 'url', 'label' => 'Lien YouTube', 'description' => 'URL de votre chaine YouTube', 'category' => 'social', 'position' => 2],
        ['key' => 'social_twitch', 'value' => '', 'type' => 'url', 'label' => 'Lien Twitch', 'description' => 'URL de votre chaine Twitch', 'category' => 'social', 'position' => 3],
        ['key' => 'social_instagram', 'value' => '', 'type' => 'url', 'label' => 'Lien Instagram', 'description' => 'URL de votre profil Instagram', 'category' => 'social', 'position' => 4],
        ['key' => 'social_tiktok', 'value' => '', 'type' => 'url', 'label' => 'Lien TikTok', 'description' => 'URL de votre profil TikTok', 'category' => 'social', 'position' => 5],

        // ========== FOOTER ==========
        ['key' => 'footer_text', 'value' => 'La plateforme de reference pour le classement de serveurs de jeux.', 'type' => 'textarea', 'label' => 'Texte du footer', 'description' => 'Texte descriptif affiche dans le footer', 'category' => 'footer', 'position' => 0],
        ['key' => 'footer_copyright', 'value' => '© {year} Nexarena. Tous droits reserves.', 'type' => 'text', 'label' => 'Texte copyright', 'description' => 'Texte de copyright. Utilisez {year} pour l\'annee dynamique.', 'category' => 'footer', 'position' => 1],
        ['key' => 'footer_show_social', 'value' => '1', 'type' => 'boolean', 'label' => 'Afficher les reseaux sociaux', 'description' => 'Afficher les icones de reseaux sociaux dans le footer', 'category' => 'footer', 'position' => 2],

        // ========== PAGES LEGALES ==========
        ['key' => 'legal_cgu', 'value' => '<h2>Conditions Generales d\'Utilisation</h2><p>Bienvenue sur Nexarena. En accedant et en utilisant ce site, vous acceptez les presentes conditions generales d\'utilisation.</p><h3>1. Objet</h3><p>Les presentes CGU ont pour objet de definir les conditions d\'acces et d\'utilisation de la plateforme Nexarena, accessible a l\'adresse nexarena.com.</p><h3>2. Acces au service</h3><p>Le site est accessible gratuitement a tout utilisateur disposant d\'un acces Internet. L\'inscription est necessaire pour certaines fonctionnalites (ajout de serveurs, vote, etc.).</p><h3>3. Inscription</h3><p>L\'utilisateur s\'engage a fournir des informations exactes lors de son inscription. Il est responsable de la confidentialite de ses identifiants de connexion.</p><h3>4. Comportement des utilisateurs</h3><p>Les utilisateurs s\'engagent a ne pas : publier de contenu illegal, diffamatoire ou offensant ; tenter de manipuler les votes ou le classement ; utiliser des systemes automatises pour interagir avec le site ; porter atteinte au fonctionnement du site.</p><h3>5. Contenu des serveurs</h3><p>Les proprietaires de serveurs sont seuls responsables du contenu et des informations qu\'ils publient. Nexarena se reserve le droit de supprimer tout contenu contraire aux presentes CGU.</p><h3>6. Propriete intellectuelle</h3><p>L\'ensemble du contenu du site (design, textes, logos) est protege par le droit de la propriete intellectuelle. Toute reproduction est interdite sans autorisation prealable.</p><h3>7. Responsabilite</h3><p>Nexarena ne saurait etre tenu responsable des contenus publies par les utilisateurs ni du fonctionnement des serveurs de jeux references sur la plateforme.</p><h3>8. Modification des CGU</h3><p>Nexarena se reserve le droit de modifier les presentes CGU a tout moment. Les utilisateurs seront informes de toute modification.</p><h3>9. Contact</h3><p>Pour toute question relative aux presentes CGU, vous pouvez nous contacter via la page de contact du site.</p>', 'type' => 'html', 'label' => 'Contenu CGU', 'description' => 'Contenu HTML des Conditions Generales d\'Utilisation (editeur riche)', 'category' => 'legal', 'position' => 0],
        ['key' => 'legal_cgv', 'value' => '<h2>Conditions Generales de Vente</h2><p>Les presentes conditions generales de vente regissent les relations contractuelles entre Nexarena et ses utilisateurs dans le cadre de services payants.</p><h3>1. Services proposes</h3><p>Nexarena propose un service gratuit de referencement de serveurs de jeux. Des services premium pourront etre proposes ulterieurement (mise en avant, options de personnalisation, etc.).</p><h3>2. Prix</h3><p>Les prix des services payants sont indiques en euros TTC. Nexarena se reserve le droit de modifier ses tarifs a tout moment.</p><h3>3. Paiement</h3><p>Le paiement s\'effectue en ligne par les moyens de paiement proposes sur le site. La transaction est securisee.</p><h3>4. Droit de retractation</h3><p>Conformement a la legislation en vigueur, l\'utilisateur dispose d\'un delai de 14 jours a compter de la souscription pour exercer son droit de retractation.</p><h3>5. Responsabilite</h3><p>Nexarena s\'engage a fournir les services souscrits avec diligence. La responsabilite de Nexarena est limitee au montant paye par l\'utilisateur.</p><h3>6. Litiges</h3><p>En cas de litige, une solution amiable sera recherchee avant toute action judiciaire. Le droit francais est applicable.</p><h3>7. Contact</h3><p>Pour toute reclamation, contactez-nous via la page de contact du site.</p>', 'type' => 'html', 'label' => 'Contenu CGV', 'description' => 'Contenu HTML des Conditions Generales de Vente (editeur riche)', 'category' => 'legal', 'position' => 1],

        // ========== INSCRIPTION ==========
        ['key' => 'register_enabled', 'value' => '1', 'type' => 'boolean', 'label' => 'Inscription ouverte', 'description' => 'Autoriser les nouveaux utilisateurs a s\'inscrire', 'category' => 'registration', 'position' => 0],
        ['key' => 'register_require_email_verification', 'value' => '0', 'type' => 'boolean', 'label' => 'Verification email requise', 'description' => 'Les utilisateurs doivent verifier leur email avant de pouvoir utiliser le site', 'category' => 'registration', 'position' => 1],
        ['key' => 'register_steam_enabled', 'value' => '1', 'type' => 'boolean', 'label' => 'Connexion Steam activee', 'description' => 'Autoriser la connexion via Steam (OAuth)', 'category' => 'registration', 'position' => 2],
        ['key' => 'register_default_role', 'value' => 'ROLE_USER', 'type' => 'text', 'label' => 'Role par defaut', 'description' => 'Role attribue automatiquement aux nouveaux inscrits', 'category' => 'registration', 'position' => 3],

        // ========== ARTICLES ==========
        ['key' => 'articles_per_page', 'value' => '16', 'type' => 'number', 'label' => 'Articles par page', 'description' => 'Nombre d\'articles affiches par page sur /actualites', 'category' => 'articles', 'position' => 0],
        ['key' => 'articles_show_on_homepage', 'value' => '1', 'type' => 'boolean', 'label' => 'Afficher sur la page d\'accueil', 'description' => 'Afficher la section actualites sur la page d\'accueil', 'category' => 'articles', 'position' => 1],
        ['key' => 'articles_homepage_count', 'value' => '3', 'type' => 'number', 'label' => 'Nombre sur la page d\'accueil', 'description' => 'Nombre d\'articles affiches dans la section actualites de la page d\'accueil', 'category' => 'articles', 'position' => 2],

        // ========== API ==========
        ['key' => 'api_enabled', 'value' => '1', 'type' => 'boolean', 'label' => 'API activee', 'description' => 'Activer l\'acces a l\'API REST publique', 'category' => 'api', 'position' => 0],
        ['key' => 'api_rate_limit', 'value' => '60', 'type' => 'number', 'label' => 'Limite requetes / minute', 'description' => 'Nombre maximum de requetes API par minute et par token', 'category' => 'api', 'position' => 1],
        ['key' => 'api_allow_ip_in_response', 'value' => '0', 'type' => 'boolean', 'label' => 'Inclure IP dans les reponses', 'description' => 'Inclure l\'adresse IP des votants dans les reponses de l\'API', 'category' => 'api', 'position' => 2],

        // ========== CLES API ==========
        ['key' => 'google_client_id', 'value' => '', 'type' => 'text', 'label' => 'Google Client ID', 'description' => 'Client ID de l\'application Google OAuth (console.cloud.google.com)', 'category' => 'api_keys', 'position' => 0],
        ['key' => 'google_client_secret', 'value' => '', 'type' => 'text', 'label' => 'Google Client Secret', 'description' => 'Client Secret de l\'application Google OAuth', 'category' => 'api_keys', 'position' => 1],
        ['key' => 'discord_client_id', 'value' => '', 'type' => 'text', 'label' => 'Discord Client ID', 'description' => 'Client ID de l\'application Discord OAuth (discord.com/developers)', 'category' => 'api_keys', 'position' => 2],
        ['key' => 'discord_client_secret', 'value' => '', 'type' => 'text', 'label' => 'Discord Client Secret', 'description' => 'Client Secret de l\'application Discord OAuth', 'category' => 'api_keys', 'position' => 3],
        ['key' => 'twitch_client_id', 'value' => '', 'type' => 'text', 'label' => 'Twitch Client ID', 'description' => 'Client ID de l\'application Twitch OAuth (dev.twitch.tv)', 'category' => 'api_keys', 'position' => 4],
        ['key' => 'twitch_client_secret', 'value' => '', 'type' => 'text', 'label' => 'Twitch Client Secret', 'description' => 'Client Secret de l\'application Twitch OAuth', 'category' => 'api_keys', 'position' => 5],
        ['key' => 'steam_api_key', 'value' => '', 'type' => 'text', 'label' => 'Steam API Key', 'description' => 'Cle API Steam (steamcommunity.com/dev/apikey)', 'category' => 'api_keys', 'position' => 6],
        ['key' => 'ipgeolocation_api_key', 'value' => '', 'type' => 'text', 'label' => 'IPGeolocation API Key', 'description' => 'Cle API ipgeolocation.io pour la detection VPN/Proxy', 'category' => 'api_keys', 'position' => 7],
        ['key' => 'virustotal_api_key', 'value' => '', 'type' => 'text', 'label' => 'VirusTotal API Key', 'description' => 'Cle API VirusTotal pour scanner les fichiers uploades (virustotal.com)', 'category' => 'api_keys', 'position' => 8],

        // ========== VOTES ==========
        ['key' => 'vote_interval', 'value' => '120', 'type' => 'number', 'label' => 'Intervalle de vote (minutes)', 'description' => 'Temps minimum entre deux votes du meme utilisateur sur le meme serveur (en minutes). 120 = 2 heures.', 'category' => 'votes', 'position' => 0],
        ['key' => 'vote_require_login', 'value' => '1', 'type' => 'boolean', 'label' => 'Connexion requise pour voter', 'description' => 'Les utilisateurs doivent etre connectes pour voter', 'category' => 'votes', 'position' => 1],
        ['key' => 'vote_antifraud_enabled', 'value' => '1', 'type' => 'boolean', 'label' => 'Anti-fraude actif', 'description' => 'Activer la detection anti-fraude (verification IP, cookies, empreinte)', 'category' => 'votes', 'position' => 2],
        ['key' => 'vote_antifraud_max_ip', 'value' => '3', 'type' => 'number', 'label' => 'Max votes par IP / intervalle', 'description' => 'Nombre maximum de votes autorises par adresse IP pendant un meme intervalle de vote. Empeche les multi-comptes depuis la meme IP.', 'category' => 'votes', 'position' => 3],
        ['key' => 'vote_captcha_enabled', 'value' => '0', 'type' => 'boolean', 'label' => 'Captcha actif', 'description' => 'Demander un captcha avant chaque vote', 'category' => 'votes', 'position' => 4],
        ['key' => 'vote_vpn_check_enabled', 'value' => '1', 'type' => 'boolean', 'label' => 'Detection VPN/Proxy', 'description' => 'Bloquer les votes provenant de VPN, proxies et Tor', 'category' => 'votes', 'position' => 5],
        ['key' => 'vote_require_platform', 'value' => '1', 'type' => 'boolean', 'label' => 'Plateforme requise (Discord/Steam)', 'description' => 'Les votants doivent se connecter via Discord ou Steam pour voter', 'category' => 'votes', 'position' => 6],

        // ========== SERVEURS ==========
        ['key' => 'server_require_approval', 'value' => '1', 'type' => 'boolean', 'label' => 'Approbation requise', 'description' => 'Les nouveaux serveurs doivent etre approuves par un admin avant d\'apparaitre', 'category' => 'servers', 'position' => 0],
        ['key' => 'server_max_per_user', 'value' => '5', 'type' => 'number', 'label' => 'Max serveurs par utilisateur', 'description' => 'Nombre maximum de serveurs qu\'un utilisateur peut ajouter', 'category' => 'servers', 'position' => 1],
        ['key' => 'server_allow_banner', 'value' => '1', 'type' => 'boolean', 'label' => 'Banniere serveur autorisee', 'description' => 'Permettre aux proprietaires de serveurs d\'ajouter une banniere', 'category' => 'servers', 'position' => 2],
        ['key' => 'server_max_description_length', 'value' => '5000', 'type' => 'number', 'label' => 'Longueur max description', 'description' => 'Nombre maximum de caracteres pour la description d\'un serveur', 'category' => 'servers', 'position' => 3],

        // ========== WEBHOOKS ==========
        ['key' => 'webhook_vote_enabled', 'value' => '0', 'type' => 'boolean', 'label' => 'Webhook vote actif', 'description' => 'Envoyer un webhook lors de chaque vote', 'category' => 'webhooks', 'position' => 0],
        ['key' => 'webhook_vote_url', 'value' => '', 'type' => 'url', 'label' => 'URL webhook vote', 'description' => 'URL par defaut ou envoyer les notifications de vote (le proprietaire du serveur peut overrider)', 'category' => 'webhooks', 'position' => 1],
        ['key' => 'webhook_secret', 'value' => '', 'type' => 'text', 'label' => 'Secret webhook', 'description' => 'Cle secrete pour signer les webhooks (HMAC-SHA256)', 'category' => 'webhooks', 'position' => 2],
        ['key' => 'webhook_send_ip', 'value' => '1', 'type' => 'boolean', 'label' => 'Envoyer l\'IP du votant', 'description' => 'Inclure l\'adresse IP du votant dans le webhook', 'category' => 'webhooks', 'position' => 3],
        ['key' => 'webhook_send_email', 'value' => '0', 'type' => 'boolean', 'label' => 'Envoyer l\'email du votant', 'description' => 'Inclure l\'email du votant dans le webhook', 'category' => 'webhooks', 'position' => 4],

        // ========== SECURITE ==========
        ['key' => 'security_max_upload_size', 'value' => '5', 'type' => 'number', 'label' => 'Taille max upload (Mo)', 'description' => 'Taille maximale des fichiers uploades en megaoctets (images, bannieres)', 'category' => 'securite', 'position' => 0],
        ['key' => 'security_allowed_origins', 'value' => '', 'type' => 'textarea', 'label' => 'Origines autorisees (CORS)', 'description' => 'Domaines autorises pour les requetes cross-origin (un par ligne). Laissez vide pour tout autoriser.', 'category' => 'securite', 'position' => 1],
    ];

    private const CATEGORY_LABELS = [
        'general' => 'General',
        'banner' => 'Banniere & Accueil',
        'seo' => 'SEO & Referencement',
        'social' => 'Reseaux sociaux',
        'footer' => 'Footer',
        'registration' => 'Inscription',
        'articles' => 'Articles',
        'api' => 'API',
        'api_keys' => 'Cles API',
        'votes' => 'Votes',
        'servers' => 'Serveurs',
        'webhooks' => 'Webhooks',
        'plugins' => 'Plugins',
        'securite' => 'Securite',
        'legal' => 'Pages legales',
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private SettingRepository $settingRepo,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $created = 0;

        foreach (self::DEFAULT_SETTINGS as $data) {
            $existing = $this->settingRepo->findByKey($data['key']);
            if (!$existing) {
                $setting = new Setting();
                $setting->setKey($data['key']);
                $setting->setValue($data['value']);
                $setting->setType($data['type']);
                $setting->setLabel($data['label']);
                $setting->setDescription($data['description']);
                $setting->setCategory($data['category']);
                $setting->setPosition($data['position']);
                $this->em->persist($setting);
                $created++;
            }
        }

        $this->em->flush();

        $io->success("$created parametre(s) cree(s), " . (count(self::DEFAULT_SETTINGS) - $created) . " deja existant(s).");

        return Command::SUCCESS;
    }
}
