Config = {}

-- ============================================================
-- NEXARENA API
-- ============================================================
Config.ServerToken = 'YOUR_SERVER_TOKEN_HERE'       -- Token API de votre serveur (dans Gestion > API)
Config.ApiUrl      = 'https://nexarena.fr'          -- URL de Nexarena (ne pas modifier)
Config.ServerSlug  = 'mon-serveur'                  -- Slug de votre serveur (pour le lien de vote)

-- ============================================================
-- METHODE DE VERIFICATION
-- Choisir comment verifier si le joueur a vote
-- Options: 'discord', 'steam', 'ip', 'username'
-- ============================================================
Config.CheckMethod = 'discord'

-- ============================================================
-- FRAMEWORK
-- 'auto' = detection automatique (ESX / QBCore)
-- 'esx', 'qbcore', 'standalone'
-- ============================================================
Config.Framework = 'auto'

-- ============================================================
-- COMMANDES
-- ============================================================
Config.Commands = {
    Vote      = 'vote',         -- Affiche le lien de vote
    CheckVote = 'checkvote',    -- Verifie le vote et donne la recompense
    VoteTop   = 'votetop',      -- Affiche le classement des votes (si BDD activee)
}

-- ============================================================
-- COOLDOWN
-- Temps minimum entre deux verifications (en secondes)
-- Empeche le spam de l'API
-- ============================================================
Config.CheckCooldown = 120  -- 2 minutes

-- ============================================================
-- NOTIFICATIONS
-- ============================================================
Config.Notification = {
    Duration       = 8000,    -- Duree d'affichage (ms)
    Position       = 'top',   -- 'top' ou 'bottom'
    ShowToEveryone = true,    -- Notifier tout le serveur quand quelqu'un vote
    ServerName     = '',      -- Nom du serveur (vide = recupere automatiquement via l'API)
}

-- ============================================================
-- RECOMPENSES
-- Ajoutez autant de recompenses que vous voulez
-- Types: 'money', 'item', 'weapon'
--
-- Pour 'money':
--   account = 'money' | 'bank' | 'black_money' (ESX)
--             'cash' | 'bank' | 'crypto' (QBCore)
--
-- Pour 'item':
--   name = nom de l'item dans votre inventaire
--   metadata = {} (optionnel, pour ox_inventory)
--
-- Pour 'weapon':
--   name = nom de l'arme (ex: 'WEAPON_PISTOL')
--   ammo = nombre de munitions (optionnel, defaut 0)
-- ============================================================
Config.Rewards = {
    {
        type    = 'money',
        account = 'money',
        amount  = 50000,
        label   = '50 000$',
    },
    -- Exemples (decommentez pour activer) :
    -- {
    --     type     = 'item',
    --     name     = 'bread',
    --     amount   = 5,
    --     label    = '5x Pain',
    --     metadata = {},  -- Pour ox_inventory (optionnel)
    -- },
    -- {
    --     type  = 'weapon',
    --     name  = 'WEAPON_PISTOL',
    --     ammo  = 50,
    --     label = 'Pistolet + 50 balles',
    -- },
}

-- ============================================================
-- INVENTAIRE (pour les items)
-- 'auto' = detection automatique
-- 'ox_inventory', 'esx', 'qbcore', 'standalone'
-- ============================================================
Config.InventorySystem = 'auto'

-- ============================================================
-- BASE DE DONNEES - SUIVI DES VOTES
-- Si active, insere/met a jour le nombre de votes par joueur
-- Table: nexarena_votes
-- ============================================================
Config.Database = {
    Enabled = false,  -- Mettre a true pour activer le suivi BDD
}

-- ============================================================
-- MESSAGES
-- ============================================================
Config.Messages = {
    VoteLink        = '~g~[NEXARENA]~w~ Votez pour nous : ~b~{url}',
    VoteReminder    = '~g~[NEXARENA]~w~ Apres avoir vote, tapez ~y~/checkvote~w~ pour recuperer vos recompenses !',
    AlreadyClaimed  = '~r~[NEXARENA]~w~ Vous avez deja recupere votre recompense de vote.',
    NotVoted        = '~r~[NEXARENA]~w~ Vous n\'avez pas encore vote ! Tapez ~y~/vote~w~ pour voter.',
    Cooldown        = '~r~[NEXARENA]~w~ Veuillez patienter avant de re-verifier.',
    ApiError        = '~r~[NEXARENA]~w~ Erreur de communication avec l\'API. Reessayez plus tard.',
    NoIdentifier    = '~r~[NEXARENA]~w~ Impossible de recuperer votre identifiant ({method}). Verifiez votre configuration.',
    RewardReceived  = '~g~[NEXARENA]~w~ Recompenses recues ! Merci pour votre vote !',
    NoDiscord       = '~r~[NEXARENA]~w~ Vous devez lier votre compte Discord a FiveM pour utiliser cette fonctionnalite.',
    NoSteam         = '~r~[NEXARENA]~w~ Vous devez lancer FiveM via Steam pour utiliser cette fonctionnalite.',
}
