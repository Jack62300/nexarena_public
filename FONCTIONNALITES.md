# Nexarena - Documentation des fonctionnalites

> Plateforme de classement et gestion de serveurs de jeux (Symfony 7.4 / PHP 8.3)

---

## Table des matieres

- [1. Cote Public (Visiteur)](#1-cote-public-visiteur)
- [2. Cote Utilisateur (ROLE_USER)](#2-cote-utilisateur-role_user)
- [3. Cote Administrateur](#3-cote-administrateur)
- [4. API](#4-api)
- [5. Systeme technique](#5-systeme-technique)

---

## 1. Cote Public (Visiteur)

### 1.1 Page d'accueil

- **Top 3 serveurs** du mois affiches en vedette avec votes et categorie
- **Serveurs mis en avant** par l'administration (classement manuel par position)
- **Grille de categories** avec icones et images (Minecraft, FiveM, Rust, CS2, GMod, etc.)
- **Barre partenaires** : logos cliquables des partenaires officiels
- **Barre services** : logos des services suivis par Nexarena
- Effets visuels : animations de fond, gradients, particules

### 1.2 Classement des serveurs

- **Classement par categorie parente** (`/classement/categorie/{slug}`) : tous les serveurs d'une categorie (ex: Serveurs de jeux)
- **Classement par sous-categorie** (`/classement/{slug}`) : serveurs d'un jeu specifique (ex: Minecraft)
- Pagination (10 serveurs par page)
- Tri par nombre de votes mensuels

### 1.3 Page serveur

- **Onglet Information** :
  - Banniere personnalisee
  - Description riche (HTML via Quill.js)
  - Image de presentation
  - Liens sociaux (Discord, Twitter, Twitch, YouTube, Instagram, site web)
  - Embed Twitch en direct si chaine configuree
  - Serveurs similaires (meme categorie)

- **Onglet Serveur** (requete live) :
  - Statut en temps reel (en ligne / hors ligne)
  - Nombre de joueurs / slots max
  - Liste des joueurs connectes (nom, ping) avec recherche
  - Auto-rafraichissement toutes les 60 secondes
  - Protocoles supportes : CFX (FiveM/RedM), Source Engine (CS2, Rust, GMod, ARK, DayZ), Minecraft SLP

- **Onglet Avis** :
  - Commentaires des utilisateurs connectes
  - Affichage : avatar, pseudo, role, date, contenu
  - Le proprietaire du serveur peut signaler un commentaire (flag avec raison)

- **Systeme de themes** : 25 themes visuels differents (Minecraft, GTA, FiveM, Rust, CS2, Valorant, LoL, Fortnite, ARK, GMod, DayZ, Cyberpunk, Purple, Crimson, Ocean, Rose, etc.)
  - Couleurs d'accentuation personnalisees
  - Image de fond par theme
  - Elements decoratifs lateraux
  - Proprietes CSS dynamiques (`--sv-accent`, `--sv-bg-body`, `--sv-hero-from`, etc.)

### 1.4 Systeme de vote

- **Vote OAuth obligatoire** : connexion via Discord ou Steam requise pour voter
- Flux : clic "Voter" → redirection OAuth (Discord/Steam) → callback → verification → vote enregistre
- **Anti-triche** :
  - Cooldown par IP, Discord ID et Steam ID (1 vote par periode configurable)
  - Detection VPN/Proxy via ipgeolocation.io (configurable par l'admin)
  - Verification de serveur actif et approuve
- Vote incremente les compteurs `totalVotes` et `monthlyVotes` du serveur
- **Webhook** : notification automatique au serveur si configure (payload signe HMAC-SHA256)

### 1.5 Widgets embarquables

- **Widget carte serveur** (`/widget/{slug}/card`) :
  - Embed HTML/iframe pour sites externes
  - Personnalisation visuelle : couleur d'accent, fond, texte, mode (dark/light), border-radius
  - Options : masquer footer, masquer description
  - Independant du theme du serveur

- **Widget top votants** (`/widget/{slug}/voters`) :
  - Liste des meilleurs votants du serveur
  - Meme systeme de personnalisation

### 1.6 Actualites / Blog

- Liste des articles publies (`/actualites`) avec pagination (16 par page)
- Page article individuelle (`/actualites/{slug}`) avec contenu riche
- Image de couverture par article

### 1.7 Plugins

- Catalogue de plugins telechargeables (`/plugins`)
- Filtrage par categorie : Jeux, Vocal, Hebergement
- Telechargement direct en ZIP
- Compteur de telechargements
- Plugins disponibles : Minecraft (Bukkit/Java), FiveM (Lua ESX/QBCore), GMod (GLua DarkRP), Discord (Node.js discord.js v14), TeamSpeak (Python ts3)

### 1.8 Recrutement

- **Liste publique** (`/recrutement`) :
  - Offres de recrutement approuvees et actives
  - Filtrage par categorie parente
  - Carte visuelle avec image, serveur, description, date
  - Compteur d'offres actives

- **Page offre** (`/recrutement/{slug}`) :
  - Banniere + description HTML detaillee
  - Informations serveur (nom, categorie, sous-categorie)
  - **Formulaire dynamique** de candidature (champs configures par le gestionnaire) :
    - Types : texte, zone de texte, select, radio, checkbox, email, nombre
    - Champs obligatoires/optionnels avec placeholders
  - Option "Connexion requise" pour certaines offres

### 1.9 Authentification

- **Inscription** classique (email + mot de passe + pseudo)
- **Connexion OAuth** : Google, Discord, Twitch, Steam
- **Liaison de comptes** : si un email OAuth correspond a un compte existant, les comptes sont lies
- **Completion d'inscription** : si le provider OAuth ne fournit pas d'email (Steam), formulaire de completion
- **2FA TOTP** : authentification a deux facteurs (QR code + code a 6 chiffres)
- **Throttling** : 5 tentatives max en 15 minutes

### 1.10 Pages legales

- Conditions Generales d'Utilisation (`/cgu`)
- Conditions Generales de Vente (`/cgv`)
- Contenu configurable via les parametres admin (HTML)

---

## 2. Cote Utilisateur (ROLE_USER)

### 2.1 Profil utilisateur

**Route** : `/profil`

- **Avatar** :
  - Upload d'avatar local (image)
  - Ou avatar automatique depuis le provider OAuth (Google, Discord, Twitch)
  - Double logique d'affichage : URL OAuth vs fichier local (`uploads/avatars/`)

- **Modification email** : changement d'adresse email avec verification CSRF
- **Modification mot de passe** : ancien + nouveau mot de passe avec confirmation

- **2FA (Authentification a deux facteurs)** :
  - Activation : genere un secret TOTP + QR code (flow AJAX)
  - Confirmation : saisie du code a 6 chiffres pour valider
  - Desactivation : avec confirmation par mot de passe
  - Compatible avec Google Authenticator, Authy, etc.

### 2.2 Gestion des serveurs

#### Liste des serveurs (`/mes-serveurs`)
- **Mes serveurs** : liste des serveurs dont l'utilisateur est proprietaire
- **Serveurs partages** : serveurs ou l'utilisateur est collaborateur (tag "Collaborateur")
- Boutons rapides : modifier, gerer, supprimer (selon permissions)

#### Creation d'un serveur (`/serveur/ajouter`)
- Formulaire complet :
  - Nom, description courte, description complete (Quill.js)
  - Categorie parente + sous-categorie (chargement dynamique AJAX)
  - Type de serveur
  - IP, port, URL de connexion
  - Slots, serveur prive (oui/non)
  - Liens sociaux (Discord, Twitter, Twitch, YouTube, Instagram, site web)
  - Banniere + image de presentation (upload avec validation MIME)

#### Edition d'un serveur (`/serveur/{id}/modifier`)
- Meme formulaire que la creation
- **Permissions granulaires** pour les collaborateurs :
  - `edit_info` : modifier nom, description, categorie, type, IP/port
  - `edit_images` : modifier banniere et image de presentation
  - `edit_social` : modifier liens sociaux et Twitch
- Champs desactives visuellement si l'utilisateur n'a pas la permission

#### Gestion avancee (`/serveur/{id}/gestion`)

- **API** (permission `manage_api`) :
  - Token API unique (genere automatiquement, regenerable)
  - IPs autorisees pour l'API (max 2 adresses IP)

- **Webhooks** (permission `manage_webhooks`) :
  - Activer/desactiver les webhooks de vote
  - URL de webhook configurable

- **Theme** (permission `manage_theme`) :
  - Selecteur visuel parmi 25 themes (grille de cartes colorees)
  - Apercu des couleurs et icones de chaque theme

- **Widget** :
  - Configurateur interactif avec onglets (carte / votants)
  - Pickers de couleurs (accent, fond, texte, texte secondaire)
  - Toggle mode sombre/clair
  - Slider de border-radius
  - Cases a cocher (masquer footer, masquer description)
  - Apercu iframe en direct
  - Code embed auto-genere avec bouton copier

- **Moderation des commentaires** (permission `moderate_comments`) :
  - Liste des commentaires du serveur
  - Signaler un commentaire (flag avec raison)

- **Statut serveur** (permission `manage_status`) :
  - Activer/desactiver la verification de statut en direct

- **Collaborateurs** (proprietaire uniquement) :
  - Ajouter un collaborateur par nom d'utilisateur (max 10)
  - Supprimer un collaborateur
  - Gerer les permissions individuelles (9 permissions) :
    - `edit_info` - Modifier les informations
    - `edit_images` - Modifier les images
    - `edit_social` - Modifier les liens sociaux
    - `manage_webhooks` - Gerer les webhooks
    - `manage_theme` - Gerer le theme
    - `manage_api` - Gerer l'API
    - `manage_status` - Gerer le statut
    - `moderate_comments` - Moderer les commentaires
    - `delete_server` - Supprimer le serveur

- **Zone de danger** :
  - Suppression du serveur (permission `delete_server`, confirmation requise)

### 2.3 Systeme de recrutement (Gestionnaire)

#### Liste des offres (`/mes-recrutements`)
- Toutes les offres de recrutement creees par l'utilisateur
- Statuts visibles : Brouillon, En attente, Approuve, Revision demandee, Refuse
- Actions rapides par offre

#### Creer une offre (`/mes-recrutements/creer/{serverId}`)
- Titre, description (HTML)
- 2 images illustratives (upload)
- Option "Connexion requise" pour les candidats
- Necesssite la permission `manage_recruitment` sur le serveur

#### Constructeur de formulaire (`/mes-recrutements/{id}/formulaire`)
- Interface interactive glisser-deposer
- Types de champs : texte, zone de texte, select, radio, checkbox, email, nombre
- Configuration par champ : label, placeholder, obligatoire, options (select/radio)
- Maximum 20 champs par formulaire, 50 options par select/radio
- Apercu en direct
- Serialisation JSON automatique

#### Cycle de vie d'une offre
1. **Brouillon** (draft) : edition libre
2. **Soumission** → statut "En attente" (pending)
3. **Admin approuve** → "Approuve" (approved) → visible publiquement si `isActive` et `formFields` non vide
4. **Admin demande revision** → "Revision demandee" (revision_requested) avec raison → l'utilisateur corrige et resoumet
5. **Admin refuse** → "Refuse" (rejected) avec raison

#### Gestion des candidatures (`/mes-recrutements/{id}/candidatures`)
- Liste des candidatures recues par offre
- Indicateur lu/non lu
- Statut de chaque candidature (En attente, Acceptee, Refusee)

#### Detail d'une candidature (`/mes-recrutements/{id}/candidatures/{appId}`)
- Informations du candidat (nom, email, compte utilisateur si connecte)
- Reponses au formulaire (label + reponse)
- **Actions** :
  - **Accepter** la candidature (avec commentaire optionnel)
  - **Refuser** la candidature (avec raison)
  - **Activer le chat** pour discuter avec le candidat

#### Chat avec le candidat
- Active par le gestionnaire uniquement
- Messagerie en temps reel (polling AJAX toutes les 3 secondes)
- Bulles de chat stylisees (gestionnaire vs candidat)
- Maximum 2000 caracteres par message
- Notifications automatiques a chaque nouveau message

### 2.4 Suivi des candidatures (Candidat)

#### Mes candidatures (`/mes-candidatures`)
- Liste de toutes les candidatures soumises par l'utilisateur
- Carte par candidature avec :
  - Titre de l'offre + nom du serveur
  - Badge de statut (En attente / Acceptee / Refusee)
  - Indicateur "Chat actif" si le gestionnaire a active la discussion
  - Date de soumission

#### Detail de candidature (`/mes-candidatures/{id}`)
- **Hero de statut** avec couleur dynamique :
  - Jaune (#ffc107) : En attente
  - Vert (#45f882) : Acceptee
  - Rouge (#dc3545) : Refusee
- Commentaire du gestionnaire (si accepte/refuse)
- Informations du reviewer et date de la decision
- Rappel des reponses soumises
- **Panel de chat** (si active par le gestionnaire) :
  - Historique complet des messages
  - Envoi de messages en temps reel
  - Polling automatique toutes les 3 secondes

### 2.5 Notifications

- **Cloche de notification** dans la navbar avec badge rouge (nombre de non lues)
- **Dropdown** affichant les 10 dernieres notifications
- Bouton "Tout marquer comme lu"
- Clic sur une notification → redirection vers la page concernee + marquage comme lue
- **Polling automatique** du compteur toutes les 30 secondes

#### Types de notifications
| Evenement | Destinataire | Description |
|-----------|-------------|-------------|
| Nouvelle candidature recue | Proprietaire + collaborateurs `manage_recruitment` | "Nouvelle candidature de {nom} pour {offre}" |
| Candidature acceptee/refusee | Candidat (si utilisateur connecte) | "Votre candidature pour {offre} a ete {acceptee/refusee}" |
| Nouveau message chat | L'autre partie (gestionnaire ou candidat) | "Nouveau message de {pseudo}" |
| Chat active | Candidat | "Le chat a ete active pour votre candidature" |

---

## 3. Cote Administrateur

### 3.1 Hierarchie des roles

| Role | Niveau | Acces |
|------|--------|-------|
| `ROLE_FONDATEUR` | 6 (max) | Tout, suppression d'utilisateurs |
| `ROLE_DEVELOPPEUR` | 5 | Logs, webhooks, tout ci-dessous |
| `ROLE_RESPONSABLE` | 4 | Parametres, roles, themes, tout ci-dessous |
| `ROLE_MANAGER` | 3 | Approbation, suppression de contenu, tout ci-dessous |
| `ROLE_EDITEUR` | 2 | Creation/edition de contenu, acces admin |
| `ROLE_USER` | 1 | Utilisateur standard, pas d'acces admin |

### 3.2 Dashboard (`/admin`)

**Role minimum** : `ROLE_EDITEUR`

- **6 cartes de statistiques** :
  - Utilisateurs totaux, nouveaux aujourd'hui
  - Articles publies
  - Categories actives
  - Serveurs approuves
  - Votes totaux

- **3 graphiques** (Chart.js) :
  - Serveurs par categorie (doughnut)
  - Inscriptions par mois (courbe)
  - Votes par mois (barres)

- **Utilisateurs recents** : tableau avec avatar, pseudo, email, role, date d'inscription, methode OAuth
- **Articles recents** : tableau avec titre, statut, date

### 3.3 Gestion des articles (`/admin/articles`)

**Role minimum** : `ROLE_EDITEUR` (suppression : `ROLE_MANAGER`)

- Liste des articles avec titre, statut publie/brouillon, date
- Creation / edition :
  - Titre, slug (auto-genere)
  - Contenu riche (editeur Quill.js)
  - Image de couverture (upload)
  - Toggle publie / brouillon
- Suppression d'article

### 3.4 Gestion des categories

#### Categories parentes (`/admin/parent-categories`)
**Role minimum** : `ROLE_EDITEUR` (suppression : `ROLE_MANAGER`)

- Liste des categories avec icone, image, nombre de sous-categories
- Creation / edition :
  - Nom, slug, icone (classe FontAwesome), image, description
  - **Type de requete** (queryType) : CFX (FiveM/RedM), Source Engine (CS2/Rust/GMod), Minecraft, ou aucun
  - Position, actif/inactif

#### Sous-categories / Jeux (`/admin/categories`)
**Role minimum** : `ROLE_EDITEUR` (suppression : `ROLE_MANAGER`)

- Liste avec categorie parente, image, position
- Creation / edition :
  - Nom, slug, image, description
  - Categorie parente (select)
  - Couleur (hex), position, actif/inactif

#### Types de serveur (`/admin/server-types`)
**Role minimum** : `ROLE_EDITEUR` (suppression : `ROLE_MANAGER`)

- Types rattaches a une categorie parente (ex: "Vanilla", "Modde" pour Minecraft)
- Nom, slug, categorie, position, actif/inactif

### 3.5 Gestion des serveurs (`/admin/servers`)

**Role minimum** : `ROLE_EDITEUR` (edition/approbation/toggle : `ROLE_MANAGER`)

- **Liste filtrable** :
  - Filtre par categorie
  - Filtre par statut : en attente, approuve, actif, inactif
  - Colonnes : nom, proprietaire, categorie, type, votes mensuels/totaux, statut

- **Actions** :
  - **Approuver** / retirer l'approbation (toggle)
  - **Activer** / desactiver (toggle)
  - **Editer** : modification de tous les champs du serveur
  - **Supprimer** le serveur

### 3.6 Serveurs mis en avant (`/admin/featured-servers`)

**Role minimum** : `ROLE_MANAGER`

- Recherche AJAX de serveurs par nom
- Ajouter un serveur en vedette
- Supprimer la mise en avant
- Modifier la position d'affichage (ordre sur la homepage)

### 3.7 Gestion des votes (`/admin/votes`)

**Role minimum** : `ROLE_EDITEUR` (suppression : `ROLE_MANAGER`)

- Liste de tous les votes avec :
  - Serveur, utilisateur (si connecte)
  - Methode de vote : badge Discord (bleu) ou Steam (gris)
  - ID plateforme (Discord ID / Steam ID)
  - Adresse IP du votant
  - Statut VPN (verifie / non verifie / detecte)
  - Date du vote
- Suppression d'un vote (decremente les compteurs du serveur)

### 3.8 Moderation des commentaires (`/admin/comments`)

**Role minimum** : `ROLE_EDITEUR` (actions : `ROLE_MANAGER`)

- **Onglet Signales** (`/admin/comments/flagged`) :
  - Commentaires signales par les proprietaires de serveurs
  - Affichage : serveur, auteur, contenu, raison du signalement, date
  - Actions :
    - **Approuver la suppression** : soft-delete (marque comme supprime)
    - **Rejeter le signalement** : retire le flag
    - **Supprimer definitivement** : hard delete

- **Onglet Tous** (`/admin/comments`) :
  - Tous les commentaires, filtrage par serveur
  - Hard delete possible

- **Badge sidebar** : nombre de commentaires signales en attente

### 3.9 Gestion des plugins (`/admin/plugins`)

**Role minimum** : `ROLE_EDITEUR` (suppression : `ROLE_MANAGER`)

- Liste des plugins avec : nom, plateforme, categorie, version, telechargements, statut VirusTotal
- Creation / edition :
  - Nom, slug, description (courte + longue)
  - Plateforme (Minecraft, FiveM, GMod, Discord, TeamSpeak)
  - Categorie (Jeux, Vocal, Hebergement)
  - Version
  - Upload fichier ZIP
  - Upload icone
  - Actif / inactif

- **Scan VirusTotal** :
  - Scan automatique a l'upload du fichier ZIP
  - Statuts : En attente, Propre, Signale, Erreur
  - Bouton "Rafraichir le scan" pour verifier le resultat
  - API key configurable dans les parametres

### 3.10 Gestion des partenaires (`/admin/partners`)

**Role minimum** : `ROLE_EDITEUR` (suppression : `ROLE_MANAGER`)

- Deux types : **Partenaire** et **Service**
- Creation / edition :
  - Nom, URL, type (partenaire/service)
  - Upload du logo
  - Position (ordre d'affichage)
  - Actif / inactif
- Affiches sur la homepage dans des barres dediees

### 3.11 Gestion du recrutement (`/admin/recruitment`)

**Role minimum** : `ROLE_EDITEUR` (approbation/revision/rejet/suppression : `ROLE_MANAGER`)

- **Liste filtrable par onglets de statut** :
  - Tous, En attente, Brouillon, Approuve, Revision demandee, Refuse
  - Badge de compteur pour les offres en attente (sidebar)

- **Detail d'une offre** :
  - Informations completes : titre, serveur, auteur, statut, dates
  - Description HTML
  - Images
  - Champs du formulaire configures

- **Actions d'approbation** :
  - **Approuver** : l'offre devient visible publiquement
  - **Demander une revision** : avec raison (l'utilisateur peut corriger et resoumettre)
  - **Refuser** : avec raison definitive
  - **Supprimer** : suppression de l'offre

### 3.12 Gestion des utilisateurs (`/admin/users`)

**Role minimum** : `ROLE_MANAGER` (edition roles : `ROLE_RESPONSABLE`, suppression : `ROLE_FONDATEUR`)

- Liste de tous les utilisateurs :
  - Avatar, pseudo, email, role principal (badge colore), date d'inscription
  - Icones des methodes OAuth liees (Google, Discord, Twitch, Steam)

- **Edition des roles** :
  - Attribution de roles (impossible d'attribuer un role >= au sien)
  - Protection contre l'escalade de privileges

- **Suppression** (`ROLE_FONDATEUR` uniquement)

### 3.13 Parametres du site (`/admin/settings`)

**Role minimum** : `ROLE_RESPONSABLE`

- Interface par onglets de categories :

| Categorie | Parametres |
|-----------|-----------|
| **General** | Nom du site, logo, favicon, description |
| **Banniere** | Image banniere homepage, texte, sous-texte |
| **SEO** | Titre, meta description, mots-cles, image Open Graph |
| **Social** | URLs Discord, Twitter, YouTube, Twitch, Instagram, TikTok |
| **Footer** | Copyright, afficher les reseaux sociaux |
| **Inscription** | Message de bienvenue, parametres d'inscription |
| **Articles** | Parametres des articles |
| **API** | Rate limit API (requetes/minute, defaut 60) |
| **Cles API** | Google Client ID/Secret, Discord Client ID/Secret, Twitch Client ID/Secret, Steam API Key, IPGeolocation API Key, VirusTotal API Key |
| **Votes** | Detection VPN activee, plateforme requise (Discord/Steam/les deux) |
| **Serveurs** | Parametres serveurs |
| **Plugins** | Cle VirusTotal |
| **Securite** | Taille max upload, origines autorisees |
| **Legal** | Contenu HTML des pages CGU et CGV |

- Types de valeurs : texte, zone de texte, booleen, nombre, couleur, URL, image, HTML

### 3.14 Roles et permissions (`/admin/roles`)

**Role minimum** : `ROLE_RESPONSABLE`

- **21 permissions** reparties en categories :
  - Dashboard, Articles, Categories, Serveurs, Votes, Utilisateurs, Roles, Parametres, Webhooks, Logs

- **Roles systeme** (non supprimables) :
  - ROLE_USER, ROLE_EDITEUR, ROLE_MANAGER, ROLE_RESPONSABLE, ROLE_DEVELOPPEUR, ROLE_FONDATEUR
  - Modification limitee : couleur et description uniquement

- **Roles personnalises** :
  - Creation de roles avec nom, nom technique, couleur, description
  - Attribution de permissions individuelles
  - Position dans la hierarchie

### 3.15 Gestion des themes (`/admin/themes`)

**Role minimum** : `ROLE_RESPONSABLE`

- **25 themes** disponibles : Default, Minecraft, GTA, FiveM, Arma, Rust, CS2, Valorant, LoL, Fortnite, ARK, GMod, DayZ, Unturned, Space Engineers, Terraria, Roblox, Discord, TeamSpeak, Hosting, Cyberpunk, Purple, Crimson, Ocean, Rose

- **3 types d'images par theme** :
  - `bg` : image de fond pleine page
  - `decor-left` : element decoratif gauche
  - `decor-right` : element decoratif droit

- Upload / suppression par theme
- Stockage : `public/uploads/themes/{theme_key}/{type}.{ext}`

### 3.16 Logs et Webhooks

**Role minimum** : `ROLE_DEVELOPPEUR`

- **Logs** (`/admin/logs`) : visualisation des logs systeme
- **Webhooks** (`/admin/webhooks`) : gestion des webhooks

---

## 4. API

### 4.1 API serveur (authentifiee par token)

**Base URL** : `/api/v1/servers/{token}/`

Chaque serveur possede un token API unique de 64 caracteres. Optionnellement, les IPs autorisees peuvent etre restreintes (max 2).

| Endpoint | Methode | Description |
|----------|---------|-------------|
| `/vote/{username}` | GET | Verifier si un pseudo a vote recemment |
| `/vote/ip/{ip}` | GET | Verifier si une IP a vote recemment |
| `/vote/discord/{discordId}` | GET | Verifier si un Discord ID a vote recemment |
| `/vote/user/{userId}` | GET | Verifier si un utilisateur (ID) a vote recemment |
| `/stats` | GET | Statistiques du serveur (votes, categorie) |
| `/voters` | GET | Top votants pagines (limit 1-50, defaut 20) |

**Rate limiting** : configurable via parametres admin (defaut 60 requetes/minute), base sur IP.

### 4.2 API statut serveur

| Endpoint | Methode | Description |
|----------|---------|-------------|
| `/api/server-status/{id}` | GET | Statut en direct (online, joueurs, maxJoueurs, listeJoueurs) |

Protocoles : CFX (HTTP JSON), Source Engine (UDP A2S), Minecraft (TCP SLP)

### 4.3 API formulaires

| Endpoint | Methode | Description |
|----------|---------|-------------|
| `/api/form/game-categories/{categoryId}` | GET | Sous-categories pour une categorie parente |
| `/api/form/server-types/{categoryId}` | GET | Types de serveur pour une categorie |

### 4.4 API notifications (authentifiee)

| Endpoint | Methode | Description |
|----------|---------|-------------|
| `/api/notifications` | GET | Notifications recentes + compteur non lues (`?count_only=1` possible) |
| `/api/notifications/{id}/read` | POST | Marquer une notification comme lue |
| `/api/notifications/read-all` | POST | Tout marquer comme lu |

### 4.5 API chat recrutement (authentifiee)

**Cote gestionnaire** :

| Endpoint | Methode | Description |
|----------|---------|-------------|
| `/api/recruitment/chat/{appId}/messages` | GET | Messages du chat (`?after=lastId` pour polling incremental) |
| `/api/recruitment/chat/{appId}/send` | POST | Envoyer un message (JSON `{content}`) |

**Cote candidat** :

| Endpoint | Methode | Description |
|----------|---------|-------------|
| `/api/applicant/chat/{appId}/messages` | GET | Messages du chat |
| `/api/applicant/chat/{appId}/send` | POST | Envoyer un message (uniquement si chat active) |

---

## 5. Systeme technique

### 5.1 Securite

- **Session** : `cookie_secure: auto`, `cookie_httponly: true`, `cookie_samesite: lax`
- **Login throttling** : 5 tentatives max en 15 minutes
- **Uploads** : whitelist MIME, taille max configurable, `basename()` sur tous les `unlink()`, `.htaccess` anti-execution PHP
- **OAuth** : `hash_equals()` pour la comparaison d'etat
- **SSL** : verification SSL activee sur toutes les requetes cURL
- **Prevention d'escalade de roles** : impossible d'attribuer un role superieur au sien
- **Validation API** : `FILTER_VALIDATE_IP`, regex sur Discord IDs, whitelist d'IPs
- **Rate limiting dynamique** : configurable en base de donnees, cache PSR-6

### 5.2 Commandes console

| Commande | Description |
|----------|-------------|
| `app:init-categories` | Initialise les categories et sous-categories par defaut |
| `app:init-permissions` | Cree les 21 permissions + 6 roles systeme |
| `app:init-settings` | Cree les 118 parametres par defaut (14 categories) |
| `app:monthly-battle` | Archive le top 10 mensuel, designe le gagnant, remet les compteurs a zero |
| `app:reset-monthly-votes` | Remet tous les votes mensuels a zero |
| `app:promote-user {email} {role}` | Attribue un role a un utilisateur |

### 5.3 Entites (19)

| Entite | Description |
|--------|-------------|
| User | Utilisateurs avec OAuth multi-provider + 2FA TOTP |
| Server | Serveurs de jeux avec votes, theme, API, webhooks |
| Category | Categories parentes (Serveurs de jeux, Vocal, etc.) |
| GameCategory | Sous-categories / jeux (Minecraft, FiveM, etc.) |
| ServerType | Types de serveur par categorie (Vanilla, Modde, etc.) |
| ServerCollaborator | Co-gestionnaires avec 9 permissions granulaires |
| Vote | Votes avec tracking IP/Discord/Steam + detection VPN |
| Comment | Commentaires avec moderation (flag + soft-delete) |
| MonthlyBattle | Archives mensuelles du top 10 + gagnant |
| Article | Articles / actualites du blog |
| Setting | Parametres cle-valeur (118 parametres, 14 categories) |
| Role | Roles personnalisables avec permissions |
| Permission | 21 permissions granulaires |
| Plugin | Plugins telechargeables avec scan VirusTotal |
| Partner | Partenaires et services (logos homepage) |
| RecruitmentListing | Offres de recrutement avec workflow d'approbation |
| RecruitmentApplication | Candidatures avec formulaire dynamique + statut |
| RecruitmentMessage | Messages de chat entre gestionnaire et candidat |
| Notification | Notifications in-app (cloche navbar) |

### 5.4 Services (15)

| Service | Fonction |
|---------|----------|
| SlugService | Generation de slugs URL-friendly |
| SettingsService | Gestion des parametres cle-valeur avec cache memoire |
| VoteService | Logique de vote, cooldowns, anti-triche |
| ServerService | Upload/validation des images serveur |
| ThemeService | Catalogue de 25 themes avec palettes completes |
| GameServerQueryService | Requetes live CFX / Source Engine / Minecraft |
| IpSecurityService | Detection VPN/Proxy via ipgeolocation.io |
| VirusTotalService | Scan de fichiers via VirusTotal API v3 |
| OAuthRegistrationService | Inscription/liaison de comptes OAuth |
| WebhookService | Envoi de webhooks de vote (HMAC-SHA256) |
| StatsService | Fournisseur de statistiques pour le dashboard |
| StatusService | Verification TCP simple de disponibilite |
| RecruitmentService | Upload images + validation formulaire de recrutement |
| NotificationService | Creation/gestion des notifications in-app |
| SettingsEnvVarProcessor | Variables d'environnement depuis la base de donnees |

### 5.5 Extensions Twig (9)

| Extension | Fonctions |
|-----------|----------|
| SettingsExtension | `setting()`, `setting_bool()` |
| RoleExtension | `role_badge()`, `get_role()`, `get_highest_role()` |
| CommentExtension | `flagged_comments_count()` |
| AvatarExtension | `user_avatar()` |
| ThemeExtension | `server_theme()`, `theme_image()`, filtre `hex_to_rgb` |
| NavigationExtension | `nav_categories()` |
| SteamExtension | `steam_login_url()` |
| RecruitmentExtension | `pending_recruitments_count()` |
| NotificationExtension | `unread_notifications_count()` |
