<?php

namespace App\Command;

use App\Entity\PremiumPlan;
use App\Entity\Setting;
use App\Repository\PremiumPlanRepository;
use App\Repository\SettingRepository;
use App\Service\SlugService;
use App\Service\WheelService;
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
        ['key' => 'site_email', 'value' => 'contact@nexarena.fr', 'type' => 'text', 'label' => 'Email de contact', 'description' => 'Adresse email affichee pour le contact', 'category' => 'general', 'position' => 4],
        ['key' => 'site_accent_color', 'value' => '#45f882', 'type' => 'color', 'label' => 'Couleur principale', 'description' => 'Couleur d\'accent du site (boutons, liens, etc.)', 'category' => 'general', 'position' => 5],
        ['key' => 'maintenance_mode', 'value' => '0', 'type' => 'boolean', 'label' => 'Mode maintenance', 'description' => 'Activer le mode maintenance (seuls les admins peuvent acceder au site)', 'category' => 'general', 'position' => 6],

        // ========== BANNIERE / ACCUEIL ==========
        ['key' => 'banner_title', 'value' => 'Nexarena', 'type' => 'text', 'label' => 'Titre de la banniere', 'description' => 'Titre principal affiche dans la banniere de la page d\'accueil', 'category' => 'banner', 'position' => 0],
        ['key' => 'banner_subtitle', 'value' => 'Trouvez et votez pour les meilleurs serveurs de jeux', 'type' => 'text', 'label' => 'Sous-titre de la banniere', 'description' => 'Texte affiche sous le titre principal', 'category' => 'banner', 'position' => 1],
        ['key' => 'banner_text_stroke', 'value' => 'Nexarena', 'type' => 'text', 'label' => 'Texte en fond (text-stroke)', 'description' => 'Grand texte decoratif en arriere-plan de la banniere', 'category' => 'banner', 'position' => 2],
        ['key' => 'banner_slides', 'value' => '[]', 'type' => 'text', 'label' => 'Slides de la banniere', 'description' => 'Images du diaporama de la banniere (gerees via l\'interface dediee)', 'category' => 'banner', 'position' => 3],
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
        ['key' => 'seo_site_url', 'value' => 'https://nexarena.eu', 'type' => 'text', 'label' => 'URL du site (canonique)', 'description' => 'URL absolue du site sans slash final (ex: https://nexarena.eu). Utilise pour les balises canonical et le sitemap.', 'category' => 'seo', 'position' => 5],
        ['key' => 'google_verification', 'value' => '', 'type' => 'text', 'label' => 'Code verification Google Search Console', 'description' => 'Contenu de la balise google-site-verification (depuis la Search Console). Laissez vide si non utilise.', 'category' => 'seo', 'position' => 6],

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
        ['key' => 'terms_of_service', 'value' => '<h2>Reglement de Nexarena</h2><p>En vous inscrivant sur Nexarena, vous acceptez l\'ensemble des dispositions du present reglement. Il s\'applique a tous les utilisateurs, proprietaires de serveurs et visiteurs de la plateforme.</p><h3>1. Respect d\'autrui</h3><p>Les utilisateurs s\'engagent a respecter les autres membres de la communaute. Sont strictement interdits :</p><ul><li>Les insultes, le harcelement, les menaces et toute forme d\'intimidation ;</li><li>Les propos discriminatoires fondes sur l\'origine ethnique, la religion, le sexe, l\'orientation sexuelle, le handicap ou tout autre critere protege par la loi ;</li><li>L\'usurpation d\'identite d\'un autre utilisateur ou d\'un membre de l\'equipe Nexarena ;</li><li>La publication d\'informations personnelles d\'autrui sans son consentement (doxxing).</li></ul><h3>2. Contenus interdits</h3><p>Il est formellement interdit de publier, diffuser ou referencer sur Nexarena tout contenu :</p><ul><li><strong>Pornographique ou a caractere sexuel explicite</strong>, y compris tout contenu impliquant des mineurs (CSAM), passible de poursuites penales en France ;</li><li><strong>Illegal</strong> au regard du droit francais, notamment : apologie du terrorisme, incitation a la haine, trafic de stupefiants, contrefacon, piratage de logiciels ou de jeux ;</li><li><strong>Violent ou glorifiant la violence</strong> : images ou descriptions de violence gratuite, actes de torture, d\'automutilation ou incitant a la violence ;</li><li><strong>Trompeur ou frauduleux</strong> : fausses informations, arnaques, phishing, serveurs factices ou liens malveillants ;</li><li><strong>Portant atteinte aux droits d\'auteur</strong> : utilisation non autorisee de marques, logos, musiques ou oeuvres protegees.</li></ul><h3>3. Serveurs references</h3><p>Les proprietaires de serveurs sont seuls responsables du contenu propose sur leurs serveurs et des informations publiees sur leur fiche. Nexarena se reserve le droit de supprimer sans preavis tout serveur dont le contenu violerait le present reglement ou la legislation francaise en vigueur.</p><h3>4. Manipulation des votes</h3><p>Toute tentative de manipulation du systeme de vote est strictement interdite : utilisation de VPN, bots, comptes multiples, achat ou echange de votes. Les serveurs identifies comme fraudeurs seront immediatement bannis.</p><h3>5. Signalement</h3><p>Tout utilisateur peut signaler un contenu inapproprie via les outils de signalement disponibles sur la plateforme. L\'equipe Nexarena traitera chaque signalement dans les meilleurs delais.</p><h3>6. Sanctions</h3><p>Le non-respect du present reglement peut entrainer, selon la gravite des faits : un avertissement ; la suspension temporaire ou definitive du compte ; la suppression du ou des serveurs references ; un signalement aux autorites competentes pour les infractions penales.</p><h3>7. Droit applicable</h3><p>Le present reglement est soumis au droit francais. En cas de litige, les tribunaux francais seront seuls competents.</p><h3>8. Modifications</h3><p>Nexarena se reserve le droit de modifier le present reglement a tout moment. La poursuite de l\'utilisation du site apres modification vaut acceptation du nouveau reglement.</p>', 'type' => 'html', 'label' => 'Reglement (accepte a l\'inscription)', 'description' => 'Contenu HTML du reglement affiche sur /reglement et accepte obligatoirement lors de l\'inscription (editeur riche)', 'category' => 'legal', 'position' => 2],

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
        ['key' => 'google_client_id', 'value' => '', 'type' => 'secret', 'label' => 'Google Client ID', 'description' => 'Client ID de l\'application Google OAuth (console.cloud.google.com)', 'category' => 'api_keys', 'position' => 0],
        ['key' => 'google_client_secret', 'value' => '', 'type' => 'secret', 'label' => 'Google Client Secret', 'description' => 'Client Secret de l\'application Google OAuth', 'category' => 'api_keys', 'position' => 1],
        ['key' => 'discord_client_id', 'value' => '', 'type' => 'secret', 'label' => 'Discord Client ID', 'description' => 'Client ID de l\'application Discord OAuth (discord.com/developers)', 'category' => 'api_keys', 'position' => 2],
        ['key' => 'discord_client_secret', 'value' => '', 'type' => 'secret', 'label' => 'Discord Client Secret', 'description' => 'Client Secret de l\'application Discord OAuth', 'category' => 'api_keys', 'position' => 3],
        ['key' => 'twitch_client_id', 'value' => '', 'type' => 'secret', 'label' => 'Twitch Client ID', 'description' => 'Client ID de l\'application Twitch OAuth (dev.twitch.tv)', 'category' => 'api_keys', 'position' => 4],
        ['key' => 'twitch_client_secret', 'value' => '', 'type' => 'secret', 'label' => 'Twitch Client Secret', 'description' => 'Client Secret de l\'application Twitch OAuth', 'category' => 'api_keys', 'position' => 5],
        ['key' => 'steam_api_key', 'value' => '', 'type' => 'secret', 'label' => 'Steam API Key', 'description' => 'Cle API Steam (steamcommunity.com/dev/apikey)', 'category' => 'api_keys', 'position' => 6],
        ['key' => 'ipqs_api_key', 'value' => '', 'type' => 'secret', 'label' => 'IPQualityScore API Key', 'description' => 'Cle API IPQualityScore pour la detection VPN/Proxy/Tor et le filtrage par pays (ipqualityscore.com)', 'category' => 'api_keys', 'position' => 7],
        ['key' => 'virustotal_api_key', 'value' => '', 'type' => 'secret', 'label' => 'VirusTotal API Key', 'description' => 'Cle API VirusTotal pour scanner les fichiers uploades (virustotal.com)', 'category' => 'api_keys', 'position' => 8],

        // ========== PLUGINS ==========
        ['key' => 'plugin_submission_reward', 'value' => '200', 'type' => 'number', 'label' => 'Recompense soumission plugin (NexBits)', 'description' => 'Nombre de NexBits credites sur le compte de l\'auteur lorsqu\'un plugin soumis est approuve (0 = pas de recompense)', 'category' => 'plugins', 'position' => 0],
        ['key' => 'plugin_submission_enabled', 'value' => '1', 'type' => 'boolean', 'label' => 'Soumissions de plugins activees', 'description' => 'Permettre aux utilisateurs de soumettre des plugins via la page publique', 'category' => 'plugins', 'position' => 1],

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
        ['key' => 'webhook_secret', 'value' => '', 'type' => 'secret', 'label' => 'Secret webhook', 'description' => 'Cle secrete pour signer les webhooks (HMAC-SHA256)', 'category' => 'webhooks', 'position' => 2],
        ['key' => 'webhook_send_ip', 'value' => '1', 'type' => 'boolean', 'label' => 'Envoyer l\'IP du votant', 'description' => 'Inclure l\'adresse IP du votant dans le webhook', 'category' => 'webhooks', 'position' => 3],
        ['key' => 'webhook_send_email', 'value' => '0', 'type' => 'boolean', 'label' => 'Envoyer l\'email du votant', 'description' => 'Inclure l\'email du votant dans le webhook', 'category' => 'webhooks', 'position' => 4],

        // ========== SECURITE ==========
        ['key' => 'security_max_upload_size', 'value' => '5', 'type' => 'number', 'label' => 'Taille max upload (Mo)', 'description' => 'Taille maximale des fichiers uploades en megaoctets (images, bannieres)', 'category' => 'securite', 'position' => 0],
        ['key' => 'security_allowed_origins', 'value' => '', 'type' => 'textarea', 'label' => 'Origines autorisees (CORS)', 'description' => 'Domaines autorises pour les requetes cross-origin (un par ligne). Laissez vide pour tout autoriser.', 'category' => 'securite', 'position' => 1],
        ['key' => 'admin_vpn_block_enabled', 'value' => '1', 'type' => 'boolean', 'label' => 'Bloquer VPN/Proxy sur l\'admin', 'description' => 'Refuser l\'acces a l\'administration aux connexions VPN, proxy et Tor. Desactiver uniquement si vous administrez le site depuis un VPN de confiance.', 'category' => 'securite', 'position' => 3],
        ['key' => 'vpn_block_enabled', 'value' => '0', 'type' => 'boolean', 'label' => 'Bloquer VPN/Proxy sur tout le site', 'description' => 'Refuser l\'acces au site entier aux connexions VPN, proxy et Tor. Necessite la cle API IPQualityScore.', 'category' => 'securite', 'position' => 4],
        ['key' => 'country_block_enabled', 'value' => '0', 'type' => 'boolean', 'label' => 'Filtrage par pays active', 'description' => 'Bloquer les visiteurs dont le pays n\'est pas dans la liste des pays autorises. Necessite la cle API IPQualityScore.', 'category' => 'securite', 'position' => 5],
        ['key' => 'allowed_countries', 'value' => '', 'type' => 'text', 'label' => 'Pays autorises (codes ISO)', 'description' => 'Codes pays ISO 3166-1 alpha-2 separes par des virgules. Ex: FR,BE,CH,LU,MC,CA. Laisser vide = tous les pays autorises.', 'category' => 'securite', 'position' => 6],
        ['key' => 'trusted_ips', 'value' => '', 'type' => 'textarea', 'label' => 'IPs de confiance (whitelist)', 'description' => 'IPs ou plages CIDR exemptees de tous les checks (VPN, pays). Une entree par ligne. Supporte IPv4 exacte (82.66.56.201) et CIDR (82.66.0.0/16).', 'category' => 'securite', 'position' => 7],
        ['key' => 'vpn_fraud_score_threshold', 'value' => '85', 'type' => 'number', 'label' => 'Seuil fraud_score IPQS (0 = desactive)', 'description' => 'Score de fraude minimum (0-100) au-dela duquel une IP est consideree comme suspecte et bloquee. 0 = desactive. Recommande : 85.', 'category' => 'securite', 'position' => 8],

        // ========== PAIEMENT ==========
        ['key' => 'paypal_client_id', 'value' => '', 'type' => 'secret', 'label' => 'PayPal Client ID', 'description' => 'Client ID de l\'application PayPal (developer.paypal.com)', 'category' => 'paiement', 'position' => 0],
        ['key' => 'paypal_client_secret', 'value' => '', 'type' => 'secret', 'label' => 'PayPal Client Secret', 'description' => 'Client Secret de l\'application PayPal', 'category' => 'paiement', 'position' => 1],
        ['key' => 'paypal_sandbox_mode', 'value' => '1', 'type' => 'boolean', 'label' => 'Mode Sandbox', 'description' => 'Utiliser l\'environnement sandbox PayPal pour les tests (desactiver en production)', 'category' => 'paiement', 'position' => 2],
        ['key' => 'paypal_webhook_id', 'value' => '', 'type' => 'secret', 'label' => 'PayPal Webhook ID', 'description' => 'ID du webhook configure dans le dashboard PayPal (pour verification signature)', 'category' => 'paiement', 'position' => 3],
        ['key' => 'payment_currency', 'value' => 'EUR', 'type' => 'text', 'label' => 'Devise', 'description' => 'Code devise ISO pour les paiements (EUR, USD, etc.)', 'category' => 'paiement', 'position' => 4],
        ['key' => 'crypto_pay_enabled', 'value' => '0', 'type' => 'boolean', 'label' => 'Activer Crypto.com Pay', 'description' => 'Afficher le bouton de paiement Crypto.com Pay dans la boutique', 'category' => 'paiement', 'position' => 5],
        ['key' => 'crypto_pay_publishable_key', 'value' => '', 'type' => 'secret', 'label' => 'Crypto.com Pay - Clef Publiable (pk_...)', 'description' => 'Clef publiable depuis le dashboard Crypto.com Pay', 'category' => 'paiement', 'position' => 6],
        ['key' => 'crypto_pay_secret_key', 'value' => '', 'type' => 'secret', 'label' => 'Crypto.com Pay - Clef Secrete (sk_...)', 'description' => 'Clef secrete depuis le dashboard Crypto.com Pay (ne jamais partager)', 'category' => 'paiement', 'position' => 7],
        ['key' => 'crypto_pay_webhook_secret', 'value' => '', 'type' => 'secret', 'label' => 'Crypto.com Pay - Secret Webhook', 'description' => 'Secret pour verifier la signature des webhooks entrants', 'category' => 'paiement', 'position' => 8],
        ['key' => 'stripe_enabled', 'value' => '0', 'type' => 'boolean', 'label' => 'Activer Stripe', 'description' => 'Afficher le bouton de paiement Stripe dans la boutique', 'category' => 'paiement', 'position' => 9],
        ['key' => 'stripe_publishable_key', 'value' => '', 'type' => 'secret', 'label' => 'Stripe - Clef Publiable (pk_...)', 'description' => 'Clef publiable depuis le dashboard Stripe (stripe.com/dashboard)', 'category' => 'paiement', 'position' => 10],
        ['key' => 'stripe_secret_key', 'value' => '', 'type' => 'secret', 'label' => 'Stripe - Clef Secrete (sk_...)', 'description' => 'Clef secrete depuis le dashboard Stripe (ne jamais partager)', 'category' => 'paiement', 'position' => 11],
        ['key' => 'stripe_webhook_secret', 'value' => '', 'type' => 'secret', 'label' => 'Stripe - Secret Webhook (whsec_...)', 'description' => 'Secret pour verifier la signature des webhooks Stripe entrants', 'category' => 'paiement', 'position' => 12],

        // ========== PREMIUM ==========
        ['key' => 'premium_theme_cost', 'value' => '50', 'type' => 'number', 'label' => 'Cout deblocage theme (NexBits)', 'description' => 'Nombre de NexBits pour debloquer le theme personnalise sur un serveur', 'category' => 'premium', 'position' => 0],
        ['key' => 'premium_widget_cost', 'value' => '50', 'type' => 'number', 'label' => 'Cout deblocage widget (NexBits)', 'description' => 'Nombre de NexBits pour debloquer le widget personnalise sur un serveur', 'category' => 'premium', 'position' => 1],
        ['key' => 'premium_recruitment_cost', 'value' => '50', 'type' => 'number', 'label' => 'Cout recrutement extra (NexBits)', 'description' => 'Nombre de NexBits pour chaque annonce de recrutement au-dela de la limite gratuite', 'category' => 'premium', 'position' => 2],
        ['key' => 'premium_recruitment_free_limit', 'value' => '2', 'type' => 'number', 'label' => 'Annonces recrutement gratuites', 'description' => 'Nombre d\'annonces de recrutement gratuites par serveur', 'category' => 'premium', 'position' => 3],
        ['key' => 'premium_boost_cost', 'value' => '1', 'type' => 'number', 'label' => 'Cout boost (NexBoost)', 'description' => 'Nombre de NexBoost par creneau de 12h de mise en avant', 'category' => 'premium', 'position' => 4],
        ['key' => 'premium_max_featured_per_day', 'value' => '5', 'type' => 'number', 'label' => 'Max serveurs mis en avant / jour', 'description' => 'Nombre maximum de serveurs pouvant etre mis en avant le meme jour', 'category' => 'premium', 'position' => 5],
        ['key' => 'premium_enabled', 'value' => '1', 'type' => 'boolean', 'label' => 'Systeme premium actif', 'description' => 'Activer le systeme premium (plans, tokens, deblocages)', 'category' => 'premium', 'position' => 6],
        ['key' => 'premium_theme_gate_enabled', 'value' => '1', 'type' => 'boolean', 'label' => 'Bloquer theme sans premium', 'description' => 'Si desactive, les themes sont gratuits pour tous', 'category' => 'premium', 'position' => 7],
        ['key' => 'premium_widget_gate_enabled', 'value' => '1', 'type' => 'boolean', 'label' => 'Bloquer widget perso sans premium', 'description' => 'Si desactive, le widget personnalise est gratuit pour tous', 'category' => 'premium', 'position' => 8],
        ['key' => 'premium_recruitment_gate_enabled', 'value' => '1', 'type' => 'boolean', 'label' => 'Bloquer recrutement extra sans premium', 'description' => 'Si desactive, les recrutements supplementaires sont gratuits', 'category' => 'premium', 'position' => 9],
        ['key' => 'premium_daily_random_boost_enabled', 'value' => '0', 'type' => 'boolean', 'label' => 'Boost quotidien aleatoire', 'description' => 'Un serveur du bas du classement est mis en avant gratuitement chaque jour', 'category' => 'premium', 'position' => 10],
        ['key' => 'premium_twitch_live_gate_enabled', 'value' => '1', 'type' => 'boolean', 'label' => 'Bloquer Twitch Live sans premium', 'description' => 'Si desactive, l\'integration Twitch Live (embed video + VODs) est gratuite pour tous', 'category' => 'premium', 'position' => 11],
        ['key' => 'premium_twitch_live_cost_tokens', 'value' => '100', 'type' => 'number', 'label' => 'Cout Twitch Live / mois (NexBits)', 'description' => 'Nombre de NexBits pour un mois d\'abonnement Twitch Live', 'category' => 'premium', 'position' => 12],
        ['key' => 'premium_twitch_live_cost_eur', 'value' => '4.99', 'type' => 'text', 'label' => 'Prix Twitch Live / mois (EUR)', 'description' => 'Prix en euros pour un mois d\'abonnement Twitch Live', 'category' => 'premium', 'position' => 13],
        ['key' => 'premium_profile_twitch_gate_enabled', 'value' => '1', 'type' => 'boolean', 'label' => 'Bloquer Twitch Live Profil sans premium', 'description' => 'Si desactive, tout membre peut afficher son stream Twitch sur son profil gratuitement', 'category' => 'premium', 'position' => 13],
        ['key' => 'premium_profile_twitch_cost_tokens', 'value' => '100', 'type' => 'number', 'label' => 'Cout Twitch Live Profil / mois (NexBits)', 'description' => 'NexBits preleves sur le compte du membre pour un mois de Twitch Live sur le profil', 'category' => 'premium', 'position' => 14],
        ['key' => 'premium_selection_title', 'value' => 'Selection premium', 'type' => 'text', 'label' => 'Titre section selection premium', 'description' => 'Titre affiche pour la section des positions payantes sur la page d\'accueil', 'category' => 'premium', 'position' => 14],
        ['key' => 'premium_selection_subtitle', 'value' => 'Les serveurs qui se demarquent', 'type' => 'text', 'label' => 'Sous-titre section selection premium', 'description' => 'Sous-titre de la section selection premium', 'category' => 'premium', 'position' => 15],
        ['key' => 'premium_homepage_pos1_cost', 'value' => '10', 'type' => 'number', 'label' => 'Cout position #1 accueil (NexBoost/12h)', 'description' => 'NexBoost par creneau de 12h pour la position #1 sur la page d\'accueil', 'category' => 'premium', 'position' => 16],
        ['key' => 'premium_homepage_pos2_cost', 'value' => '8', 'type' => 'number', 'label' => 'Cout position #2 accueil (NexBoost/12h)', 'description' => 'NexBoost par creneau de 12h pour la position #2 sur la page d\'accueil', 'category' => 'premium', 'position' => 17],
        ['key' => 'premium_homepage_pos3_cost', 'value' => '6', 'type' => 'number', 'label' => 'Cout position #3 accueil (NexBoost/12h)', 'description' => 'NexBoost par creneau de 12h pour la position #3 sur la page d\'accueil', 'category' => 'premium', 'position' => 18],
        ['key' => 'premium_homepage_pos4_cost', 'value' => '4', 'type' => 'number', 'label' => 'Cout position #4 accueil (NexBoost/12h)', 'description' => 'NexBoost par creneau de 12h pour la position #4 sur la page d\'accueil', 'category' => 'premium', 'position' => 19],
        ['key' => 'premium_homepage_pos5_cost', 'value' => '2', 'type' => 'number', 'label' => 'Cout position #5 accueil (NexBoost/12h)', 'description' => 'NexBoost par creneau de 12h pour la position #5 sur la page d\'accueil', 'category' => 'premium', 'position' => 20],
        ['key' => 'premium_game_pos1_cost', 'value' => '5', 'type' => 'number', 'label' => 'Cout position #1 jeu (NexBoost/12h)', 'description' => 'NexBoost par creneau de 12h pour la position #1 sur une page de jeu', 'category' => 'premium', 'position' => 21],
        ['key' => 'premium_game_pos2_cost', 'value' => '4', 'type' => 'number', 'label' => 'Cout position #2 jeu (NexBoost/12h)', 'description' => 'NexBoost par creneau de 12h pour la position #2 sur une page de jeu', 'category' => 'premium', 'position' => 22],
        ['key' => 'premium_game_pos3_cost', 'value' => '3', 'type' => 'number', 'label' => 'Cout position #3 jeu (NexBoost/12h)', 'description' => 'NexBoost par creneau de 12h pour la position #3 sur une page de jeu', 'category' => 'premium', 'position' => 23],
        ['key' => 'premium_game_pos4_cost', 'value' => '2', 'type' => 'number', 'label' => 'Cout position #4 jeu (NexBoost/12h)', 'description' => 'NexBoost par creneau de 12h pour la position #4 sur une page de jeu', 'category' => 'premium', 'position' => 24],
        ['key' => 'premium_game_pos5_cost', 'value' => '1', 'type' => 'number', 'label' => 'Cout position #5 jeu (NexBoost/12h)', 'description' => 'NexBoost par creneau de 12h pour la position #5 sur une page de jeu', 'category' => 'premium', 'position' => 25],
        ['key' => 'premium_disclaimer', 'type' => 'textarea', 'label' => 'Clause de non-responsabilite (boutique)', 'description' => 'Texte legal affiche en bas de la page boutique. Supporte le HTML basique (<strong>, <a>, etc.).', 'category' => 'premium', 'position' => 26,
            'value' => '<strong>Clause de non-responsabilité — Achats virtuels</strong><br>Les NexBits et NexBoost sont des tokens virtuels à usage exclusif sur la plateforme Nexarena. Ils ne constituent pas une monnaie réelle, ne peuvent être échangés, revendus ou remboursés en dehors des cas prévus par la loi. Nexarena n\'est en aucun cas responsable de l\'utilisation qui en est faite par les propriétaires de serveurs tiers référencés sur la plateforme. Les services premium (mise en avant, thèmes, widgets, recrutements) sont fournis à titre de services numériques : conformément à l\'article L.221-28 du Code de la consommation, le droit de rétractation ne s\'applique pas aux contenus numériques fournis immédiatement après confirmation de l\'achat. En cas de litige, une solution amiable sera recherchée en priorité. Le droit français est applicable. Pour toute réclamation, contactez-nous via la page de contact.'],
        ['key' => 'donation_daily_limit_nexbits', 'value' => '1000', 'type' => 'number', 'label' => 'Limite don quotidien NexBits par utilisateur', 'description' => 'Nombre maximum de NexBits qu\'un utilisateur peut donner a des serveurs en une journee (0 = illimite)', 'category' => 'premium', 'position' => 27],
        ['key' => 'donation_daily_limit_nexboost', 'value' => '10', 'type' => 'number', 'label' => 'Limite don quotidien NexBoost par utilisateur', 'description' => 'Nombre maximum de NexBoost qu\'un utilisateur peut donner a des serveurs en une journee (0 = illimite)', 'category' => 'premium', 'position' => 28],
        ['key' => 'premium_stats_cost', 'value' => '100', 'type' => 'number', 'label' => 'Cout deblocage Statistiques (NexBits)', 'description' => 'Nombre de NexBits pour debloquer les statistiques avancees sur un serveur', 'category' => 'premium', 'position' => 29],
        ['key' => 'premium_stats_gate_enabled', 'value' => '1', 'type' => 'boolean', 'label' => 'Statistiques avancees payantes', 'description' => 'Si desactive, les statistiques avancees sont gratuites pour tous les proprietaires', 'category' => 'premium', 'position' => 30],
        ['key' => 'premium_spreadsheet_url', 'value' => '', 'type' => 'text', 'label' => 'Lien spreadsheet boutique', 'description' => 'URL vers le spreadsheet (Google Sheets, etc.) affiche dans la boutique. Laisser vide pour masquer le lien.', 'category' => 'premium', 'position' => 31],

        // ========== DISCORD ==========
        ['key' => 'discord_bot_url', 'value' => 'http://localhost:3050', 'type' => 'text', 'label' => 'URL de l\'API du bot Discord', 'description' => 'URL de l\'API Express du bot Discord (ex: http://localhost:3050)', 'category' => 'discord', 'position' => 0],
        ['key' => 'discord_bot_api_key', 'value' => '', 'type' => 'secret', 'label' => 'Cle API partagee', 'description' => 'Cle API partagee entre le site et le bot Discord (doit etre identique dans le .env du bot)', 'category' => 'discord', 'position' => 1],
        // Automod sanctions
        ['key' => 'discord_automod_warn_to_mute', 'value' => '3', 'type' => 'number', 'label' => 'Warns avant mute auto', 'description' => 'Nombre d\'avertissements actifs avant mute automatique', 'category' => 'discord', 'position' => 2],
        ['key' => 'discord_automod_warn_to_kick', 'value' => '5', 'type' => 'number', 'label' => 'Warns avant kick auto', 'description' => 'Nombre d\'avertissements actifs avant kick automatique', 'category' => 'discord', 'position' => 3],
        ['key' => 'discord_automod_warn_to_ban', 'value' => '7', 'type' => 'number', 'label' => 'Warns avant ban auto', 'description' => 'Nombre d\'avertissements actifs avant ban automatique', 'category' => 'discord', 'position' => 4],
        ['key' => 'discord_automod_kick_enabled', 'value' => '1', 'type' => 'boolean', 'label' => 'Kick auto actif', 'description' => 'Activer le kick automatique apres X warns', 'category' => 'discord', 'position' => 5],
        ['key' => 'discord_automod_ban_enabled', 'value' => '1', 'type' => 'boolean', 'label' => 'Ban auto actif', 'description' => 'Activer le ban automatique apres X warns', 'category' => 'discord', 'position' => 6],
        ['key' => 'discord_automod_mute_duration', 'value' => '60', 'type' => 'number', 'label' => 'Duree mute auto (minutes)', 'description' => 'Duree du mute automatique en minutes', 'category' => 'discord', 'position' => 7],
        // Role commands
        ['key' => 'discord_cmd_role_add_min_role', 'value' => 'Manager', 'type' => 'text', 'label' => 'Role min pour /role add', 'description' => 'Nom du role Discord minimum requis pour utiliser /role add', 'category' => 'discord', 'position' => 8],
        ['key' => 'discord_cmd_role_remove_min_role', 'value' => 'Manager', 'type' => 'text', 'label' => 'Role min pour /role remove', 'description' => 'Nom du role Discord minimum requis pour utiliser /role remove', 'category' => 'discord', 'position' => 9],
        // Welcome
        ['key' => 'discord_welcome_enabled', 'value' => '0', 'type' => 'boolean', 'label' => 'Message de bienvenue actif', 'description' => 'Envoyer un message de bienvenue quand un membre rejoint le serveur Discord', 'category' => 'discord', 'position' => 10],
        ['key' => 'discord_welcome_channel_id', 'value' => '', 'type' => 'text', 'label' => 'Canal de bienvenue (ID)', 'description' => 'ID du canal Discord ou envoyer les messages de bienvenue', 'category' => 'discord', 'position' => 11],
        ['key' => 'discord_welcome_message', 'value' => 'Bienvenue {user} !', 'type' => 'textarea', 'label' => 'Message de bienvenue', 'description' => 'Message de bienvenue. Variables: {user} {server} {memberCount}', 'category' => 'discord', 'position' => 12],
        ['key' => 'discord_welcome_banner_url', 'value' => '', 'type' => 'url', 'label' => 'Banniere de bienvenue', 'description' => 'URL de l\'image banniere pour le message de bienvenue', 'category' => 'discord', 'position' => 13],
        // Anti-spam
        ['key' => 'discord_antispam_enabled', 'value' => '1', 'type' => 'boolean', 'label' => 'Anti-spam actif', 'description' => 'Activer le module anti-spam Discord', 'category' => 'discord', 'position' => 14],
        ['key' => 'discord_antispam_max_messages', 'value' => '5', 'type' => 'number', 'label' => 'Max messages / intervalle', 'description' => 'Nombre maximum de messages par intervalle avant detection spam', 'category' => 'discord', 'position' => 15],
        ['key' => 'discord_antispam_interval', 'value' => '5', 'type' => 'number', 'label' => 'Intervalle anti-spam (sec)', 'description' => 'Fenetre de detection en secondes', 'category' => 'discord', 'position' => 16],
        ['key' => 'discord_antispam_max_links', 'value' => '3', 'type' => 'number', 'label' => 'Max liens / intervalle', 'description' => 'Nombre maximum de liens par intervalle avant detection spam', 'category' => 'discord', 'position' => 17],
        // Live promotions
        ['key' => 'discord_live_promo_enabled', 'value' => '0', 'type' => 'boolean', 'label' => 'Promotions live actives', 'description' => 'Activer le systeme de promotion live (Twitch/YouTube)', 'category' => 'discord', 'position' => 18],
        ['key' => 'discord_live_promo_channel_id', 'value' => '', 'type' => 'text', 'label' => 'Canal promotions live (ID)', 'description' => 'ID du canal Discord ou poster les annonces de streamers en live', 'category' => 'discord', 'position' => 19],
        ['key' => 'discord_live_promo_cost_per_day', 'value' => '10', 'type' => 'number', 'label' => 'Cout promo / jour (NexBits)', 'description' => 'Nombre de NexBits par jour de promotion live', 'category' => 'discord', 'position' => 20],
        ['key' => 'discord_live_promo_max_days', 'value' => '30', 'type' => 'number', 'label' => 'Duree max promo (jours)', 'description' => 'Nombre maximum de jours pour une promotion live', 'category' => 'discord', 'position' => 21],

        // ========== VOTES (suite) ==========
        ['key' => 'vote_captcha_threshold', 'value' => '10', 'type' => 'number', 'label' => 'Seuil captcha (votes/24h)', 'description' => 'Nombre de votes par 24h avant de demander un captcha', 'category' => 'votes', 'position' => 7],
        ['key' => 'vote_antibot_max_fingerprint_ips', 'value' => '3', 'type' => 'number', 'label' => 'Max IPs par empreinte', 'description' => 'Nombre maximum d\'IPs differentes pour une meme empreinte navigateur', 'category' => 'votes', 'position' => 8],
        ['key' => 'vote_antibot_max_ip_fingerprints', 'value' => '5', 'type' => 'number', 'label' => 'Max empreintes par IP', 'description' => 'Nombre maximum d\'empreintes navigateur pour une meme IP', 'category' => 'votes', 'position' => 9],
        ['key' => 'vote_reward_enabled', 'value' => '1', 'type' => 'boolean', 'label' => 'Recompenses voteur actives', 'description' => 'Les voteurs connectes gagnent des NexBits en votant', 'category' => 'votes', 'position' => 10],
        ['key' => 'vote_reward_tier1_max', 'value' => '5', 'type' => 'number', 'label' => 'Votes palier 1 (gain plein)', 'description' => 'Nombre de votes pour le palier 1 a gain maximal', 'category' => 'votes', 'position' => 11],
        ['key' => 'vote_reward_tier1_amount', 'value' => '1.0', 'type' => 'text', 'label' => 'NexBits par vote (palier 1)', 'description' => 'NexBits gagnes par vote au palier 1', 'category' => 'votes', 'position' => 12],
        ['key' => 'vote_reward_tier2_max', 'value' => '8', 'type' => 'number', 'label' => 'Votes palier 2', 'description' => 'Nombre de votes pour le palier 2', 'category' => 'votes', 'position' => 13],
        ['key' => 'vote_reward_tier2_amount', 'value' => '0.5', 'type' => 'text', 'label' => 'NexBits par vote (palier 2)', 'description' => 'NexBits gagnes par vote au palier 2', 'category' => 'votes', 'position' => 14],
        ['key' => 'vote_reward_tier3_amount', 'value' => '0.25', 'type' => 'text', 'label' => 'NexBits par vote (palier 3+)', 'description' => 'NexBits gagnes par vote au palier 3 et au-dela', 'category' => 'votes', 'position' => 15],
        ['key' => 'vote_reward_new_server_days', 'value' => '7', 'type' => 'number', 'label' => 'Jours nouveau serveur (gain /2)', 'description' => 'Les serveurs de moins de X jours donnent moitie moins de NexBits', 'category' => 'votes', 'position' => 16],
        ['key' => 'vote_reward_max_user_day', 'value' => '8', 'type' => 'number', 'label' => 'Max votes comptabilises/jour/user', 'description' => 'Nombre maximum de votes recompenses par jour et par utilisateur', 'category' => 'votes', 'position' => 17],
        ['key' => 'vote_reward_max_server_day', 'value' => '150', 'type' => 'number', 'label' => 'Max votes comptabilises/jour/serveur', 'description' => 'Nombre maximum de votes recompenses par jour et par serveur', 'category' => 'votes', 'position' => 18],
        ['key' => 'vote_reward_max_tokens_month', 'value' => '200', 'type' => 'number', 'label' => 'Max NexBits/mois via votes', 'description' => 'Nombre maximum de NexBits gagnables par mois via les votes', 'category' => 'votes', 'position' => 19],

        // ========== ROUE COMMUNAUTAIRE ==========
        ['key' => 'wheel_enabled', 'value' => '1', 'type' => 'boolean', 'label' => 'Roue communautaire activee', 'description' => 'Activer la roue de la fortune sur la page de profil', 'category' => 'roue', 'position' => 0],
        ['key' => 'wheel_spin_cost', 'value' => '10', 'type' => 'number', 'label' => 'Cout d\'un tour (NexBits)', 'description' => 'Nombre de NexBits pour un tour payant de la roue', 'category' => 'roue', 'position' => 1],
        ['key' => 'wheel_max_paid_spins_per_day', 'value' => '10', 'type' => 'number', 'label' => 'Max tours payants / jour', 'description' => 'Nombre maximum de tours payants par utilisateur par jour', 'category' => 'roue', 'position' => 2],

        // ========== PARRAINAGE ==========
        ['key' => 'referral_enabled', 'value' => '1', 'type' => 'boolean', 'label' => 'Parrainage active', 'description' => 'Activer le systeme de parrainage (chaque utilisateur peut partager son code)', 'category' => 'referral', 'position' => 0],
        ['key' => 'referral_reward_nexbits', 'value' => '50', 'type' => 'number', 'label' => 'Recompense parrain (NexBits)', 'description' => 'Nombre de NexBits credites au parrain lorsqu\'un filleul s\'inscrit', 'category' => 'referral', 'position' => 1],
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
        'paiement' => 'Paiement',
        'premium' => 'Premium',
        'discord' => 'Discord',
        'legal' => 'Pages legales',
        'roue' => 'Roue communautaire',
    ];

    private const DEFAULT_PLANS = [
        ['name' => 'Starter', 'price' => '5.00', 'tokens' => 500, 'boost' => 0, 'desc' => 'Ideal pour debuter et tester les fonctionnalites premium.'],
        ['name' => 'Standard', 'price' => '10.00', 'tokens' => 1100, 'boost' => 0, 'desc' => '10% de tokens bonus. Le meilleur rapport qualite-prix pour demarrer.'],
        ['name' => 'Pro', 'price' => '25.00', 'tokens' => 3000, 'boost' => 0, 'desc' => '20% de tokens bonus. Pour les gerants de serveurs actifs.'],
        ['name' => 'Elite', 'price' => '50.00', 'tokens' => 6500, 'boost' => 0, 'desc' => '30% de tokens bonus. Debloquez tout et boostez votre serveur.'],
        ['name' => 'Legendary', 'price' => '100.00', 'tokens' => 14000, 'boost' => 0, 'desc' => '40% de tokens bonus. Le pack ultime pour dominer le classement.'],
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private SettingRepository $settingRepo,
        private PremiumPlanRepository $planRepo,
        private SlugService $slugService,
        private WheelService $wheelService,
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

        // Upgrade existing settings to new type if changed (e.g. text → secret)
        $upgraded = 0;
        foreach (self::DEFAULT_SETTINGS as $data) {
            $existing = $this->settingRepo->findByKey($data['key']);
            if ($existing && $existing->getType() !== $data['type']) {
                $existing->setType($data['type']);
                $upgraded++;
            }
        }

        $this->em->flush();

        $io->success("$created parametre(s) cree(s), $upgraded type(s) mis a jour, " . (count(self::DEFAULT_SETTINGS) - $created) . " deja existant(s).");

        // ========== SEED DEFAULT PREMIUM PLANS ==========
        $existingPlans = $this->planRepo->count([]);
        if ($existingPlans === 0) {
            foreach (self::DEFAULT_PLANS as $pos => $data) {
                $plan = new PremiumPlan();
                $plan->setName($data['name']);
                $plan->setSlug($this->slugService->slugify($data['name']));
                $plan->setDescription($data['desc']);
                $plan->setPrice($data['price']);
                $plan->setCurrency('EUR');
                $plan->setTokensGiven($data['tokens']);
                $plan->setBoostTokensGiven($data['boost']);
                $plan->setIsActive(true);
                $plan->setPosition($pos);
                $this->em->persist($plan);
            }
            $this->em->flush();
            $io->success(count(self::DEFAULT_PLANS) . ' plan(s) premium cree(s) par defaut.');
        } else {
            $io->note("$existingPlans plan(s) premium deja existant(s), aucun ajout.");
        }

        // ========== SEED DEFAULT WHEEL PRIZES ==========
        $wheelCreated = $this->wheelService->initDefaultPrizes($this->em);
        if ($wheelCreated > 0) {
            $io->success("$wheelCreated lot(s) de roue cree(s) par defaut.");
        } else {
            $io->note('Lots de la roue deja existants, aucun ajout.');
        }

        return Command::SUCCESS;
    }
}
