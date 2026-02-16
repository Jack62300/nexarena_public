TEST EDITION

# Nexarena

Plateforme de classement et gestion de serveurs de jeux vidéo.

## Stack technique

- **Framework** : Symfony 7.4
- **PHP** : 8.3+
- **Base de données** : MariaDB 11.8+
- **Authentification** : OAuth2 (Google, Discord, Twitch, Steam) + 2FA TOTP

## Fonctionnalités principales

- **Classement de serveurs** avec système de votes (OAuth Discord/Steam, anti-triche VPN)
- **25 thèmes visuels** personnalisables par serveur (Minecraft, FiveM, Rust, CS2, etc.)
- **Requête live** des serveurs de jeux (CFX, Source Engine, Minecraft SLP)
- **Système de recrutement** avec formulaire dynamique, approbation admin, chat et notifications
- **Collaboration** sur les serveurs avec 9 permissions granulaires
- **Widgets embarquables** avec configurateur visuel
- **Plugins** téléchargeables avec scan VirusTotal
- **Blog / Actualités**
- **Dashboard admin** complet avec statistiques et graphiques
- **API REST** pour intégration externe (vérification votes, stats, joueurs)

## Installation

### Prérequis

- PHP 8.3+
- Composer
- MariaDB 11.8+ (ou MySQL 8.0+)
- Extensions PHP : `intl`, `gd`, `curl`, `mbstring`, `pdo_mysql`

### Étapes

```bash
# 1. Cloner le dépôt
git clone https://github.com/Jack62300/nexarea_website.git
cd nexarea_website

# 2. Installer les dépendances
composer install

# 3. Configurer l'environnement
cp .env .env.local
# Éditer .env.local avec vos valeurs (DB, OAuth, API keys)

# 4. Créer la base de données
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# 5. Initialiser les données
php bin/console app:init-permissions
php bin/console app:init-categories
php bin/console app:init-settings

# 6. Créer un administrateur
php bin/console app:promote-user votre@email.com ROLE_FONDATEUR

# 7. Lancer le serveur
symfony server:start
# ou
php -S localhost:8000 -t public/
```

### Configuration `.env.local`

```env
# Base de données
DATABASE_URL="mysql://user:password@127.0.0.1:3306/Nexarena?serverVersion=11.8.3-MariaDB&charset=utf8mb4"

# Secret Symfony (générer avec: php -r "echo bin2hex(random_bytes(16));")
APP_SECRET=votre_secret_ici

# OAuth (optionnel)
GOOGLE_CLIENT_ID=xxx
GOOGLE_CLIENT_SECRET=xxx
DISCORD_CLIENT_ID=xxx
DISCORD_CLIENT_SECRET=xxx
TWITCH_CLIENT_ID=xxx
TWITCH_CLIENT_SECRET=xxx
STEAM_API_KEY=xxx

# VPN Detection (optionnel)
IPGEOLOCATION_API_KEY=xxx
```

> Les clés API peuvent aussi être configurées depuis le panel admin (Paramètres > Clés API).

## Commandes utiles

| Commande | Description |
|----------|-------------|
| `app:init-permissions` | Initialise les rôles et permissions |
| `app:init-categories` | Crée les catégories par défaut |
| `app:init-settings` | Crée les 118 paramètres par défaut |
| `app:monthly-battle` | Archive le classement mensuel (cron) |
| `app:reset-monthly-votes` | Remet les votes mensuels à zéro |
| `app:promote-user <email> <role>` | Attribue un rôle à un utilisateur |

## Hiérarchie des rôles

```
ROLE_FONDATEUR (tout)
  └── ROLE_DEVELOPPEUR (logs, webhooks)
       └── ROLE_RESPONSABLE (paramètres, rôles, thèmes)
            └── ROLE_MANAGER (approbation, suppression)
                 └── ROLE_EDITEUR (création/édition contenu, accès admin)
                      └── ROLE_USER (utilisateur standard)
```

## Structure du projet

```
src/
├── Command/          # Commandes console (init, monthly battle, promote)
├── Controller/
│   ├── Admin/        # Back-office (dashboard, CRUD, modération)
│   ├── Api/          # Endpoints API (serveurs, notifications, formulaires)
│   └── ...           # Contrôleurs publics et utilisateur
├── Entity/           # 19 entités Doctrine
├── EventListener/    # Rate limiter API
├── Repository/       # Repositories avec requêtes custom
├── Security/         # Authenticators OAuth + TOTP
├── Service/          # 15 services métier
└── Twig/             # 9 extensions Twig

templates/
├── admin/            # Templates back-office
├── applicant/        # Suivi candidatures (candidat)
├── articles/         # Blog
├── home/             # Page d'accueil
├── includes/         # Header, footer, notifications
├── plugins/          # Catalogue de plugins
├── ranking/          # Classements
├── recruitment/      # Offres de recrutement (public)
├── security/         # Login, register, 2FA
├── server/           # Page serveur publique
├── user/             # Espace utilisateur (serveurs, recrutement)
└── widget/           # Widgets embarquables
```

## Documentation

Voir [FONCTIONNALITES.md](FONCTIONNALITES.md) pour la documentation complète de toutes les fonctionnalités.

## Licence

Projet privé - Tous droits réservés.
