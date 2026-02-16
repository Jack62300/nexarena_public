================================================================
  NEXARENA VOTE REWARDS - Plugin FiveM
  Version 2.0.0
================================================================

INSTALLATION
------------
1. Copiez le dossier "nexarena-fivem" dans votre dossier resources/
2. Ajoutez "ensure nexarena-fivem" dans votre server.cfg
3. Configurez config.lua avec votre token API et vos preferences

CONFIGURATION RAPIDE
--------------------
1. Config.ServerToken : Votre token API (Gestion > API sur Nexarena)
2. Config.ServerSlug  : Le slug de votre serveur (visible dans l'URL)
3. Config.CheckMethod : 'discord', 'steam', 'ip' ou 'username'
4. Config.Rewards     : Configurez vos recompenses (argent, items, armes)

METHODES DE VERIFICATION
-------------------------
- discord  : Verifie via l'ID Discord du joueur (recommande)
- steam    : Verifie via l'ID Steam du joueur
- ip       : Verifie via l'adresse IP du joueur
- username : Verifie via le pseudo du joueur

FRAMEWORKS SUPPORTES
--------------------
- ESX (Legacy & New)
- QBCore
- Standalone (natif)

INVENTAIRES SUPPORTES
---------------------
- ox_inventory (prioritaire si detecte)
- ESX inventory (par defaut avec ESX)
- QBCore inventory (par defaut avec QBCore)
- Mode standalone

BASE DE DONNEES (optionnel)
----------------------------
Activez Config.Database.Enabled = true pour tracker les votes.
Necessite: oxmysql, mysql-async ou ghmattimysql
La table est creee automatiquement au demarrage.

COMMANDES
---------
/vote       - Affiche le lien de vote
/checkvote  - Verifie le vote et donne les recompenses
/votetop    - Classement des meilleurs voteurs (si BDD activee)

EXPORTS (pour vos scripts)
---------------------------
-- Verifier si un joueur a vote
exports['nexarena-fivem']:CheckPlayerVote(playerId, function(voted, data)
    if voted then
        print('Le joueur a vote !')
    end
end)

-- Obtenir le nombre de votes d'un joueur (necessite BDD)
exports['nexarena-fivem']:GetPlayerVoteCount(playerId, function(count)
    print('Votes: ' .. count)
end)

SUPPORT
-------
https://nexarena.fr
