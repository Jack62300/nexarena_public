# Nexarena - Documentation des commandes Symfony

## Vue d'ensemble

Nexarena dispose de 10 commandes Symfony personnalisees. 4 d'entre elles sont executees automatiquement par le **Symfony Scheduler** (plus besoin de crontab). Les 6 autres sont des commandes manuelles d'administration.

Les votes ne sont jamais remis a zero : ils sont conserves et cumules de mois en mois.

---

## Demarrage du Scheduler

Le Scheduler remplace les crontabs. Il faut lancer un seul worker qui gere toutes les taches planifiees.

```bash
# Developpement (premier plan)
php bin/console messenger:consume scheduler_default -vv

# Production (via systemd)
sudo systemctl start nexarena-scheduler
```

Pour verifier les taches planifiees :

```bash
php bin/console debug:scheduler
```

---

## Commandes planifiees (automatiques)

Ces commandes sont executees automatiquement par le Scheduler. Elles peuvent aussi etre lancees manuellement.

### `app:send-scheduled-announcements`

**Frequence** : Chaque minute
**Role** : Envoie les annonces Discord dont l'heure programmee est passee.

Verifie la table `discord_announcement` pour les annonces avec un `scheduledAt` depasse et non encore envoyees (`sentAt IS NULL`). Envoie chaque annonce au bot Discord via l'API, puis enregistre le `sentAt` et le `discordMessageId`.

```bash
# Execution manuelle
php bin/console app:send-scheduled-announcements

# Avec details
php bin/console app:send-scheduled-announcements -v
```

**Prerequis** : Le bot Discord doit etre en ligne pour recevoir les annonces.

---

### `app:process-twitch-subscriptions`

**Frequence** : Tous les jours a 04:00
**Role** : Gere l'expiration et le renouvellement automatique des abonnements Twitch Live.

Parcourt les abonnements Twitch actifs. Si un abonnement est expire, il tente un renouvellement automatique (si le solde de tokens le permet). Sinon, il desactive l'abonnement.

```bash
# Execution manuelle
php bin/console app:process-twitch-subscriptions
```

**Sortie** : Affiche le nombre d'abonnements renouveles et expires.

---

### `app:monthly-random-boost`

**Frequence** : Le 1er du mois a 00:05
**Role** : Tire au sort un serveur eligible et offre un jeton NexBoost a son proprietaire.

**Criteres d'eligibilite** :
1. Le serveur doit etre actif et approuve
2. Le serveur doit exister depuis au moins 48 heures (date de creation >= 48h)
3. Le proprietaire doit s'etre connecte il y a moins de 48 heures
4. Le serveur doit avoir au minimum 15 votes mensuels

Parmi les serveurs eligibles, les 10 avec le plus de votes sont retenus comme candidats. Un seul est tire au sort. Le proprietaire recoit 1 jeton NexBoost et une notification systeme.

```bash
# Execution manuelle (hors 1er du mois)
php bin/console app:monthly-random-boost --force

# Simulation sans attribution
php bin/console app:monthly-random-boost --force --dry-run

# Le 1er du mois (pas besoin de --force)
php bin/console app:monthly-random-boost
```

**Options** :
| Option | Description |
|--------|-------------|
| `--dry-run` | Simule le tirage sans attribuer de jeton |
| `--force` | Force l'execution meme si on n'est pas le 1er du mois |

---

### `app:monthly-battle`

**Frequence** : Le 1er du mois a 00:30
**Role** : Archive le top 10 des serveurs du mois precedent et designe le gagnant.

Cree un enregistrement `MonthlyBattle` avec :
- Le mois et l'annee du mois precedent
- Les donnees JSON des 10 meilleurs serveurs (id, nom, votes, rang)
- Le serveur gagnant (celui avec le plus de votes)

Les votes ne sont pas remis a zero. Ils sont conserves et cumules de mois en mois.

```bash
# Execution manuelle
php bin/console app:monthly-battle
```

**Securite** : Si un MonthlyBattle existe deja pour le mois precedent, la commande ne fait rien (idempotente).

---

## Planning mensuel du 1er du mois

Les commandes du 1er du mois s'executent dans cet ordre :

| Heure | Commande | Description |
|-------|----------|-------------|
| 00:05 | `app:monthly-random-boost` | Tirage NexBoost parmi les serveurs eligibles |
| 00:30 | `app:monthly-battle` | Archive le top 10 et designe le gagnant |

---

## Commandes manuelles (administration)

Ces commandes ne sont PAS planifiees. Elles sont executees manuellement par un administrateur.

### `app:init-settings`

**Role** : Initialise ou met a jour les parametres du site dans la base de donnees.

Insere les parametres par defaut (site_name, site_logo, etc.) et les plans Premium. Ne modifie pas les valeurs existantes, ajoute uniquement les parametres manquants.

```bash
php bin/console app:init-settings
```

**Quand l'utiliser** :
- Apres un premier deploiement
- Apres une mise a jour qui ajoute de nouveaux parametres
- Pour reinitialiser les parametres manquants

---

### `app:init-categories`

**Role** : Initialise les categories et sous-categories par defaut (Minecraft, GTA, CS2, etc.).

```bash
php bin/console app:init-categories
```

**Quand l'utiliser** : Uniquement au premier deploiement.

---

### `app:init-permissions`

**Role** : Initialise les roles et permissions par defaut dans la base de donnees.

```bash
php bin/console app:init-permissions
```

**Quand l'utiliser** : Uniquement au premier deploiement ou apres l'ajout de nouveaux roles.

---

### `app:promote-user`

**Role** : Attribue un role a un utilisateur par son adresse email.

```bash
# Syntaxe
php bin/console app:promote-user <email> <role>

# Exemples
php bin/console app:promote-user admin@nexarena.com ROLE_FONDATEUR
php bin/console app:promote-user moderateur@example.com ROLE_MANAGER
php bin/console app:promote-user editeur@example.com ROLE_EDITEUR
```

**Roles disponibles** (du plus eleve au plus bas) :
| Role | Description |
|------|-------------|
| `ROLE_FONDATEUR` | Fondateur - acces total |
| `ROLE_DEVELOPPEUR` | Developpeur - acces technique |
| `ROLE_RESPONSABLE` | Responsable - gestion globale |
| `ROLE_MANAGER` | Manager - gestion des serveurs et du contenu |
| `ROLE_EDITEUR` | Editeur - edition du contenu |
| `ROLE_USER` | Utilisateur standard (attribue automatiquement) |

---

### `app:reset-monthly-votes`

**Role** : Remet a zero les votes mensuels de tous les serveurs. Commande d'urgence uniquement.

Cette commande n'est PAS planifiee. Les votes sont conserves de mois en mois. Elle n'est la qu'en cas de besoin exceptionnel (ex: remise a zero manuelle apres un bug).

```bash
php bin/console app:reset-monthly-votes
```

**Attention** : Cette action est irreversible. N'utiliser qu'en cas de necessite absolue.

---

### `app:test-random-boost`

**Role** : Commande de test pour le systeme de boost aleatoire quotidien (daily boost). Permet de verifier l'etat du boost du jour et de tester l'attribution.

```bash
# Voir les candidats et positions disponibles (dry-run)
php bin/console app:test-random-boost

# Voir le statut du boost du jour
php bin/console app:test-random-boost --status

# Executer le boost pour de vrai
php bin/console app:test-random-boost --run
```

**Options** :
| Option | Description |
|--------|-------------|
| `--status` | Affiche le boost actif du jour (serveur, proprietaire, position) |
| `--run` | Execute reellement le boost (au lieu du mode dry-run) |

---

## Deploiement - Service systemd

Le fichier `nexarena-scheduler.service` a la racine du projet contient la configuration systemd pour le Scheduler.

### Installation

```bash
# 1. Copier le fichier service
sudo cp nexarena-scheduler.service /etc/systemd/system/

# 2. Adapter les chemins dans le fichier si necessaire
sudo nano /etc/systemd/system/nexarena-scheduler.service

# 3. Recharger systemd
sudo systemctl daemon-reload

# 4. Activer le demarrage automatique
sudo systemctl enable nexarena-scheduler

# 5. Demarrer le service
sudo systemctl start nexarena-scheduler
```

### Gestion du service

```bash
# Voir le statut
sudo systemctl status nexarena-scheduler

# Voir les logs en temps reel
sudo journalctl -u nexarena-scheduler -f

# Voir les 100 derniers logs
sudo journalctl -u nexarena-scheduler -n 100

# Redemarrer (apres un deploiement)
sudo systemctl restart nexarena-scheduler

# Arreter
sudo systemctl stop nexarena-scheduler
```

### Notes importantes

- Le worker s'arrete automatiquement apres **1 heure** (`--time-limit=3600`) et est redemarre par systemd. Cela evite les fuites memoire.
- La limite memoire est fixee a **128 Mo** (`--memory-limit=128M`).
- En production, utilisez `APP_ENV=prod` (configure dans le fichier service).
- Apres chaque deploiement, redemarrez le service : `sudo systemctl restart nexarena-scheduler`

---

## Premier deploiement - Checklist

```bash
# 1. Installer les dependances
composer install --no-dev --optimize-autoloader

# 2. Creer la base de donnees
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate --no-interaction

# 3. Initialiser les donnees
php bin/console app:init-settings
php bin/console app:init-categories
php bin/console app:init-permissions

# 4. Creer le premier administrateur
php bin/console app:promote-user admin@nexarena.com ROLE_FONDATEUR

# 5. Vider le cache
php bin/console cache:clear

# 6. Installer et demarrer le scheduler
sudo cp nexarena-scheduler.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable nexarena-scheduler
sudo systemctl start nexarena-scheduler

# 7. Verifier
php bin/console debug:scheduler
sudo systemctl status nexarena-scheduler
```
