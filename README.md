# Nexarena

**Plateforme de classement et gestion de serveurs de jeux vidéo** — similaire à top-serveurs.net.

> Projet privé — Tous droits réservés.

---

## Sommaire

- [Apercu](#apercu)
- [Fonctionnalites](#fonctionnalites)
- [Stack technique](#stack-technique)
- [Prerequis](#prerequis)
- [Installation](#installation)
- [Configuration](#configuration)
- [Commandes console](#commandes-console)
- [Deploiement](#deploiement)
  - [Nginx](#nginx)
  - [Apache](#apache)
- [Hierarchie des roles](#hierarchie-des-roles)
- [API REST](#api-rest)
- [Structure du projet](#structure-du-projet)

---

## Apercu

Nexarena permet aux propriétaires de serveurs de jeux vidéo de référencer, promouvoir et gérer leur communauté depuis une interface centralisée. Les joueurs peuvent voter, noter, commenter et recruter sur les serveurs inscrits.

---

## Fonctionnalites

### Serveurs & Classements
- **Classement public** avec filtres par catégorie, tag, type et mots-clés
- **Votes sécurisés** : connexion OAuth obligatoire (Discord, Steam), détection VPN/proxy, anti-double vote
- **Bataille mensuelle** : classement remis à zéro chaque mois avec archivage automatique
- **Requête live** du statut des serveurs (CFX/FiveM, Source Engine, Minecraft SLP)
- **Statistiques** journalières de pages vues avec graphique Chart.js (fonctionnalité premium)
- **Système de notes** 1 à 5 étoiles avec score moyen dénormalisé
- **Favoris** : liste personnelle de serveurs favoris par utilisateur
- **Tags** personnalisés par serveur

### Personnalisation
- **25 thèmes visuels** — Minecraft, FiveM, Rust, CS2, ARK, Garry's Mod, etc.
- **Configurateur de widget** embarquable (iframe) avec aperçu en temps réel
- **Sélection premium** : positions mises en avant sur la homepage et les pages de jeu

### Authentification & Comptes
- Inscription classique (email + mot de passe) + inscription via OAuth
- **OAuth2** : Google, Discord, Twitch, Steam
- **2FA TOTP** (Google Authenticator, Authy, etc.)
- Suppression de compte avec confirmation par mot de passe
- **Parrainage** : code de parrainage unique, récompense en NexBits configurable

### Collaboration
- **9 permissions granulaires** sur les serveurs : gestion membres, candidatures, statistiques, etc.
- Invitation de collaborateurs par email ou identifiant

### Recrutement
- **Annonces de recrutement** avec formulaire dynamique configurable par l'admin du serveur
- Suivi des candidatures avec historique et changement de statut
- **Chat intégré** entre recruteur et candidat
- Notifications en temps réel (badge + liste déroulante)

### Premium & Tokens
- Plans premium avec avantages (statistiques, thèmes exclusifs, positions mises en avant, etc.)
- **NexBits** (monnaie virtuelle par serveur) et **NexBoost** (boosts de visibilité)
- Paiement via PayPal
- Récompenses de vote en tokens configurables

### Plugins
- Catalogue de plugins téléchargeables par plateforme (Spigot, PaperMC, BungeeCord, etc.)
- **Scan VirusTotal** automatique à l'upload
- Soumission par les utilisateurs avec validation admin

### Administration
- **Dashboard** avec graphiques (inscriptions, votes, serveurs, revenus)
- Gestion complète : utilisateurs, serveurs, catégories, tags, articles, partenaires
- **Paramètres globaux** : 118 clés configurables depuis l'interface (dont clés API chiffrées via libsodium)
- **Webhooks Discord** : 16 types d'événements (vote, inscription, serveur approuvé, paiement, etc.)
- **Bannissement IP**, liste noire, journaux d'accès et d'activité
- Gestion des rôles, thèmes, types de serveurs, catégories de jeux

### Autres
- **Recherche globale** (serveurs, utilisateurs, articles)
- **Blog / Actualités** avec éditeur
- **Roue de la fortune** (récompenses configurables)
- **API REST** documentée (statut serveur, votes, joueurs)
- **Partenaires & Services** intégrés
- Sitemap XML dynamique
- Système de maintenance activable depuis les paramètres

---

## Stack technique

| Composant | Version |
|-----------|---------|
| PHP | 8.2+ (recommandé 8.3) |
| Symfony | 7.4 |
| Doctrine ORM | 3.6 |
| MariaDB | 11.8+ |
| Redis | 7+ (sessions, rate limiter, cache) |
| Twig | 3.x |
| Symfony Messenger | 7.4 (queues async) |

**Dépendances clés :**
- `scheb/2fa-bundle` — Authentification deux facteurs TOTP
- `knpuniversity/oauth2-client-bundle` — OAuth2 (Google, Discord, Twitch)
- `endroid/qr-code` — Génération QR code 2FA
- `symfony/mailer` — Emails transactionnels
- `symfony/scheduler` + `symfony/messenger` — Tâches planifiées

---

## Prerequis

- PHP 8.2+ avec les extensions : `intl`, `gd`, `curl`, `mbstring`, `pdo_mysql`, `sodium`, `zip`
- Composer 2.x
- MariaDB 11.8+ (ou MySQL 8.0+)
- Redis 7+
- Node.js 20+ (optionnel, pour recompiler les assets)
- Un serveur web : **Nginx** ou **Apache 2.4+**

---

## Installation

### 1. Cloner le dépôt

```bash
git clone https://github.com/votre-org/nexarena.git
cd nexarena
```

### 2. Installer les dépendances PHP

```bash
composer install --no-dev --optimize-autoloader
```

Pour un environnement de développement :

```bash
composer install
```

### 3. Configurer l'environnement

```bash
cp .env .env.local
```

Editez `.env.local` — voir la section [Configuration](#configuration) ci-dessous.

### 4. Créer la base de données

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate --no-interaction
```

### 5. Initialiser les données par défaut

```bash
# Roles et permissions
php bin/console app:init-permissions

# Categories de jeux par défaut
php bin/console app:init-categories

# 118 paramètres de configuration
php bin/console app:init-settings
```

### 6. Créer le premier administrateur

Créez d'abord un compte depuis l'interface, puis promouvez-le :

```bash
php bin/console app:promote-user admin@monsite.com ROLE_FONDATEUR
```

### 7. Vider le cache

```bash
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```

### 8. Démarrer en développement

```bash
# Avec le CLI Symfony
symfony server:start

# Ou avec le serveur intégré PHP
php -S localhost:8000 -t public/
```

Accédez à `http://localhost:8000` — le panel admin est disponible sous `/admin`.

---

## Configuration

### `.env.local` — Variables d'environnement

```env
###> Application ###
APP_ENV=prod
APP_SECRET=CHANGEZ_MOI_AVEC_32_CARACTERES_ALEATOIRES
APP_DEBUG=0
###< Application ###

###> Base de données ###
# MariaDB
DATABASE_URL="mysql://nexarena_user:motdepasse@127.0.0.1:3306/Nexarena?serverVersion=mariadb-11.8.3&charset=utf8mb4"

# MySQL 8
# DATABASE_URL="mysql://nexarena_user:motdepasse@127.0.0.1:3306/Nexarena?serverVersion=8.0.32&charset=utf8mb4"
###< Base de données ###

###> Redis ###
REDIS_URL=redis://localhost:6379
###< Redis ###

###> Mailer ###
# Gmail SMTP
MAILER_DSN=smtp://user:password@smtp.gmail.com:587?encryption=tls
# Mailgun
# MAILER_DSN=mailgun+smtp://KEY:DOMAIN@default
# Désactivé (dev)
# MAILER_DSN=null://null
###< Mailer ###

###> Messenger ###
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
###< Messenger ###

###> URL de base (pour les emails et liens absolus) ###
DEFAULT_URI=https://www.nexarena.fr
###< URL de base ###

###> OAuth2 (optionnel — peut aussi être configuré dans Admin > Paramètres > Clés API) ###
GOOGLE_CLIENT_ID=votre_google_client_id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=votre_google_client_secret
DISCORD_CLIENT_ID=votre_discord_application_id
DISCORD_CLIENT_SECRET=votre_discord_client_secret
TWITCH_CLIENT_ID=votre_twitch_client_id
TWITCH_CLIENT_SECRET=votre_twitch_client_secret
STEAM_API_KEY=votre_steam_api_key
###< OAuth2 ###

###> Détection VPN/Proxy (optionnel) ###
IPGEOLOCATION_API_KEY=votre_cle_ipgeolocation
###< Détection VPN/Proxy ###
```

> **Générer un `APP_SECRET` sécurisé :**
> ```bash
> php -r "echo bin2hex(random_bytes(16));"
> ```

### Permissions des dossiers

```bash
# Linux/Mac
chmod -R 775 var/ public/uploads/
chown -R www-data:www-data var/ public/uploads/

# Créer les dossiers d'upload si absents
mkdir -p public/uploads/avatars public/uploads/logos public/uploads/banners
```

---

## Commandes console

```bash
php bin/console <commande>
```

| Commande | Description |
|----------|-------------|
| `app:init-permissions` | Initialise les rôles et permissions par défaut |
| `app:init-categories` | Crée les catégories de jeux par défaut |
| `app:init-settings` | Crée les 118 paramètres de configuration par défaut |
| `app:monthly-battle` | Archive le classement mensuel et remet les compteurs à zéro (à planifier en cron) |
| `app:reset-monthly-votes` | Remet uniquement les votes mensuels à zéro |
| `app:promote-user <email> <role>` | Attribue un rôle à un utilisateur |
| `doctrine:migrations:migrate` | Applique les migrations de base de données |
| `cache:clear` | Vide le cache Symfony |
| `messenger:consume async` | Démarre le worker de la file de messages |

### Exemple — Promouvoir un utilisateur en éditeur

```bash
php bin/console app:promote-user redacteur@nexarena.fr ROLE_EDITEUR
```

### Exemple — Cron mensuel (Linux)

```cron
# Tous les 1er du mois à 00:01
1 0 1 * * /usr/bin/php /var/www/nexarena/bin/console app:monthly-battle --env=prod
```

---

## Deploiement

### Nginx

Configuration recommandée pour un virtual host Nginx avec SSL (Certbot/Let's Encrypt) :

```nginx
server {
    listen 80;
    server_name nexarena.fr www.nexarena.fr;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name nexarena.fr www.nexarena.fr;

    root /var/www/nexarena/public;
    index index.php;

    # SSL (généré par Certbot)
    ssl_certificate     /etc/letsencrypt/live/nexarena.fr/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/nexarena.fr/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         HIGH:!aNULL:!MD5;

    # Logs
    access_log /var/log/nginx/nexarena_access.log;
    error_log  /var/log/nginx/nexarena_error.log;

    # Limite de taille pour les uploads (plugins, images)
    client_max_body_size 32M;

    # Sécurité — headers HTTP
    add_header X-Frame-Options           "SAMEORIGIN"   always;
    add_header X-XSS-Protection          "1; mode=block" always;
    add_header X-Content-Type-Options    "nosniff"      always;
    add_header Referrer-Policy           "strict-origin-when-cross-origin" always;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    # Symfony front controller
    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        # Ou TCP : fastcgi_pass 127.0.0.1:9000;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT   $realpath_root;
        fastcgi_param HTTPS on;

        # Performances
        fastcgi_buffer_size       128k;
        fastcgi_buffers           4 256k;
        fastcgi_read_timeout      120;
        internal;
    }

    # Bloquer les autres fichiers PHP
    location ~ \.php$ {
        return 404;
    }

    # Cache des assets statiques
    location ~* \.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot|webp)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Bloquer l'accès aux fichiers sensibles
    location ~ /\.(ht|git|env) {
        deny all;
    }

    # Widgets embarquables — autoriser les iframes cross-origin
    location /widget/ {
        add_header X-Frame-Options "" always;
        add_header Content-Security-Policy "frame-ancestors *" always;
        try_files $uri /index.php$is_args$args;
    }
}
```

> **PHP-FPM :** Adaptez `fastcgi_pass` selon votre version et configuration PHP-FPM.
> Avec PHP 8.3 via socket : `unix:/run/php/php8.3-fpm.sock`

---

### Apache

Configuration recommandée pour un Virtual Host Apache 2.4 avec SSL :

```apache
<VirtualHost *:80>
    ServerName nexarena.fr
    ServerAlias www.nexarena.fr
    Redirect permanent / https://nexarena.fr/
</VirtualHost>

<VirtualHost *:443>
    ServerName nexarena.fr
    ServerAlias www.nexarena.fr

    DocumentRoot /var/www/nexarena/public

    # SSL (généré par Certbot ou manuel)
    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/nexarena.fr/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/nexarena.fr/privkey.pem

    # Logs
    ErrorLog  ${APACHE_LOG_DIR}/nexarena_error.log
    CustomLog ${APACHE_LOG_DIR}/nexarena_access.log combined

    # Limite d'upload (plugins, images)
    LimitRequestBody 33554432

    <Directory /var/www/nexarena/public>
        AllowOverride All
        Require all granted

        # Front controller Symfony
        DirectoryIndex index.php

        <IfModule mod_rewrite.c>
            RewriteEngine On

            # Redirection HTTPS
            RewriteCond %{HTTPS} off
            RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]

            # Laisser passer les fichiers et dossiers existants
            RewriteCond %{REQUEST_FILENAME} !-f
            RewriteCond %{REQUEST_FILENAME} !-d

            # Tout envoyer à index.php
            RewriteRule ^ /index.php [QSA,L]
        </IfModule>
    </Directory>

    # Sécurité — bloquer l'accès aux fichiers sensibles
    <FilesMatch "\.(env|git|htpasswd|htaccess)$">
        Require all denied
    </FilesMatch>

    # PHP-FPM via proxy (recommandé sur Apache 2.4)
    <FilesMatch "\.php$">
        SetHandler "proxy:unix:/run/php/php8.3-fpm.sock|fcgi://localhost"
    </FilesMatch>

    # Cache navigateur des assets statiques
    <IfModule mod_expires.c>
        ExpiresActive On
        ExpiresByType text/css              "access plus 30 days"
        ExpiresByType application/javascript "access plus 30 days"
        ExpiresByType image/jpeg            "access plus 30 days"
        ExpiresByType image/png             "access plus 30 days"
        ExpiresByType image/webp            "access plus 30 days"
        ExpiresByType image/svg+xml         "access plus 30 days"
        ExpiresByType font/woff2            "access plus 1 year"
    </IfModule>

    # Headers de sécurité
    <IfModule mod_headers.c>
        Header always set X-Content-Type-Options "nosniff"
        Header always set X-Frame-Options "SAMEORIGIN"
        Header always set X-XSS-Protection "1; mode=block"
        Header always set Referrer-Policy "strict-origin-when-cross-origin"
    </IfModule>
</VirtualHost>
```

**Modules Apache requis :**
```bash
a2enmod rewrite ssl headers expires proxy_fcgi
systemctl restart apache2
```

> **Note :** Le fichier `public/.htaccess` inclus dans le projet gère déjà le routage Symfony.
> Si `AllowOverride All` est activé, la configuration de base suffit pour le routing.

---

## Hierarchie des roles

```
ROLE_FONDATEUR          — Accès total (owner)
  └── ROLE_DEVELOPPEUR  — Logs techniques, webhooks, accès debug
       └── ROLE_RESPONSABLE — Paramètres globaux, gestion des rôles, thèmes
            └── ROLE_MANAGER — Approbation de contenu, suppression, modération
                 └── ROLE_EDITEUR — Création/édition d'articles, accès back-office
                      └── ROLE_USER — Utilisateur standard inscrit
```

### Attribuer un rôle

```bash
# Promouvoir en fondateur
php bin/console app:promote-user fondateur@nexarena.fr ROLE_FONDATEUR

# Promouvoir en manager
php bin/console app:promote-user manager@nexarena.fr ROLE_MANAGER

# Accès éditorial uniquement
php bin/console app:promote-user redac@nexarena.fr ROLE_EDITEUR
```

---

## API REST

L'API est accessible sous le préfixe `/api/`. Une clé API peut être requise selon la configuration (Admin > Paramètres > API).

### Exemples d'endpoints

#### Statut d'un serveur

```http
GET /api/server/{slug}/status
Authorization: Bearer VOTRE_CLE_API
```

```json
{
  "online": true,
  "players": 47,
  "maxPlayers": 100,
  "version": "1.20.4",
  "ping": 12
}
```

#### Vérifier si un joueur a voté

```http
GET /api/server/{slug}/has-voted?steamid=76561198XXXXXXXXX
Authorization: Bearer VOTRE_CLE_API
```

```json
{
  "voted": true,
  "votedAt": "2026-04-02T14:35:00+02:00"
}
```

#### Liste des joueurs connectés

```http
GET /api/server/{slug}/players
Authorization: Bearer VOTRE_CLE_API
```

> La clé API se configure dans **Admin > Paramètres > Clés API** et est chiffrée en base via libsodium.

---

## Structure du projet

```
nexarena/
├── bin/
│   └── console                    # CLI Symfony
├── config/
│   ├── packages/                  # Configuration des bundles (21 fichiers)
│   ├── routes/                    # Définition des routes
│   └── services.yaml              # Injection de dépendances
├── migrations/                    # Migrations Doctrine
├── public/
│   ├── index.php                  # Front controller
│   ├── uploads/                   # Fichiers uploadés (avatars, logos, plugins)
│   └── .htaccess                  # Routing Apache
├── src/
│   ├── Command/                   # Commandes console (init, cron, promote)
│   ├── Controller/
│   │   ├── Admin/                 # Back-office (37 contrôleurs)
│   │   ├── Api/                   # Endpoints API REST (6 contrôleurs)
│   │   └── *.php                  # Contrôleurs publics et utilisateur
│   ├── Entity/                    # Entités Doctrine (25+ entités)
│   ├── EventListener/             # Rate limiter, maintenance
│   ├── Repository/                # Requêtes Doctrine custom
│   ├── Security/                  # Authenticators OAuth2, TOTP, CSRF
│   ├── Service/                   # Services métier (15+ services)
│   └── Twig/                      # Extensions Twig (functions, filters)
├── templates/
│   ├── admin/                     # Templates back-office
│   ├── home/                      # Page d'accueil
│   ├── server/                    # Page serveur publique
│   ├── user/                      # Espace utilisateur
│   ├── recruitment/               # Recrutement (public)
│   ├── applicant/                 # Suivi candidatures
│   ├── widget/                    # Widgets embarquables
│   ├── plugins/                   # Catalogue plugins
│   ├── security/                  # Login, register, 2FA
│   └── includes/                  # Header, footer, composants
├── tests/                         # Tests PHPUnit
├── var/
│   ├── cache/                     # Cache Symfony
│   └── log/                       # Logs applicatifs
├── .env                           # Variables d'environnement (défaut)
├── .env.local                     # Surcharges locales (non versionné)
├── composer.json
└── symfony.lock
```

---

## Licence

Projet privé — Tous droits réservés © Nexarena.
