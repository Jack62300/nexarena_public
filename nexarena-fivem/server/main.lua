-- ============================================================
-- NEXARENA VOTE REWARDS - SERVER SIDE
-- ============================================================

local Framework     = nil
local FrameworkType = nil
local InventoryType = nil
local ServerName    = Config.Notification.ServerName
local Cooldowns     = {}
local VoteCache     = {} -- Track who already claimed this cycle

-- ============================================================
-- FRAMEWORK DETECTION
-- ============================================================
local function DetectFramework()
    if Config.Framework ~= 'auto' then
        FrameworkType = Config.Framework
    end

    if not FrameworkType or FrameworkType == 'auto' then
        if GetResourceState('es_extended') == 'started' then
            FrameworkType = 'esx'
        elseif GetResourceState('qb-core') == 'started' then
            FrameworkType = 'qbcore'
        else
            FrameworkType = 'standalone'
        end
    end

    if FrameworkType == 'esx' then
        local success, result = pcall(function()
            return exports['es_extended']:getSharedObject()
        end)
        if success then
            Framework = result
        else
            TriggerEvent('esx:getSharedObject', function(obj) Framework = obj end)
        end
        print('[NEXARENA] Framework detecte: ESX')
    elseif FrameworkType == 'qbcore' then
        Framework = exports['qb-core']:GetCoreObject()
        print('[NEXARENA] Framework detecte: QBCore')
    else
        print('[NEXARENA] Mode standalone')
    end
end

-- ============================================================
-- INVENTORY DETECTION
-- ============================================================
local function DetectInventory()
    if Config.InventorySystem ~= 'auto' then
        InventoryType = Config.InventorySystem
        return
    end

    if GetResourceState('ox_inventory') == 'started' then
        InventoryType = 'ox_inventory'
    elseif FrameworkType == 'esx' then
        InventoryType = 'esx'
    elseif FrameworkType == 'qbcore' then
        InventoryType = 'qbcore'
    else
        InventoryType = 'standalone'
    end

    print('[NEXARENA] Inventaire detecte: ' .. InventoryType)
end

-- ============================================================
-- INITIALIZATION
-- ============================================================
CreateThread(function()
    Wait(2000)
    DetectFramework()
    DetectInventory()
    InitDatabase()
    FetchServerName()
end)

-- ============================================================
-- DATABASE INITIALIZATION
-- ============================================================
function InitDatabase()
    if not Config.Database.Enabled then return end

    local hasOxmysql  = GetResourceState('oxmysql') == 'started'
    local hasMysqlAsync = GetResourceState('mysql-async') == 'started'
    local hasGhmattimysql = GetResourceState('ghmattimysql') == 'started'

    if not hasOxmysql and not hasMysqlAsync and not hasGhmattimysql then
        print('[NEXARENA] ^1ATTENTION: Aucune ressource MySQL detectee. Le suivi BDD est desactive.^0')
        Config.Database.Enabled = false
        return
    end

    -- Create table if not exists
    local query = [[
        CREATE TABLE IF NOT EXISTS `nexarena_votes` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `identifier` VARCHAR(100) NOT NULL,
            `player_name` VARCHAR(100) DEFAULT NULL,
            `vote_count` INT(11) NOT NULL DEFAULT 0,
            `last_vote_at` DATETIME DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_identifier` (`identifier`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ]]

    ExecuteQuery(query, {})
    print('[NEXARENA] Table nexarena_votes initialisee.')
end

-- ============================================================
-- DATABASE HELPERS (compatible oxmysql, mysql-async, ghmattimysql)
-- ============================================================
function ExecuteQuery(query, params)
    if GetResourceState('oxmysql') == 'started' then
        exports.oxmysql:execute(query, params)
    elseif GetResourceState('mysql-async') == 'started' then
        exports['mysql-async']:mysql_execute(query, params)
    elseif GetResourceState('ghmattimysql') == 'started' then
        exports.ghmattimysql:execute(query, params)
    end
end

function FetchQuery(query, params, cb)
    if GetResourceState('oxmysql') == 'started' then
        exports.oxmysql:fetch(query, params, cb)
    elseif GetResourceState('mysql-async') == 'started' then
        exports['mysql-async']:mysql_fetch_all(query, params, cb)
    elseif GetResourceState('ghmattimysql') == 'started' then
        exports.ghmattimysql:execute(query, params, cb)
    else
        if cb then cb({}) end
    end
end

-- ============================================================
-- FETCH SERVER NAME FROM API
-- ============================================================
function FetchServerName()
    if ServerName and ServerName ~= '' then return end

    local url = Config.ApiUrl .. '/api/v1/servers/' .. Config.ServerToken .. '/stats'

    PerformHttpRequest(url, function(statusCode, response)
        if statusCode == 200 and response then
            local data = json.decode(response)
            if data and data.name then
                ServerName = data.name
                print('[NEXARENA] Nom du serveur: ' .. ServerName)
            end
        end
    end, 'GET', '', { ['Accept'] = 'application/json' })
end

-- ============================================================
-- PLAYER IDENTIFIERS
-- ============================================================
function GetPlayerIdentifier(playerId, idType)
    local identifiers = GetPlayerIdentifiers(playerId)
    for _, id in pairs(identifiers) do
        if string.find(id, idType .. ':') then
            return string.gsub(id, idType .. ':', '')
        end
    end
    return nil
end

function GetCheckIdentifier(playerId)
    local method = Config.CheckMethod

    if method == 'discord' then
        local discordId = GetPlayerIdentifier(playerId, 'discord')
        if not discordId then return nil, 'discord' end
        return discordId, 'discord'

    elseif method == 'steam' then
        local steamHex = GetPlayerIdentifier(playerId, 'steam')
        if not steamHex then return nil, 'steam' end
        -- Convert steam hex to Steam64 ID
        local steam64 = tonumber(steamHex, 16)
        if not steam64 then return nil, 'steam' end
        return tostring(steam64), 'steam'

    elseif method == 'ip' then
        local ip = GetPlayerEndpoint(playerId)
        if ip then
            ip = string.match(ip, '([%d%.]+)')
        end
        if not ip then return nil, 'ip' end
        return ip, 'ip'

    elseif method == 'username' then
        local name = GetPlayerName(playerId)
        if not name then return nil, 'username' end
        return name, 'username'
    end

    return nil, method
end

-- ============================================================
-- BUILD API URL
-- ============================================================
function BuildApiUrl(identifier, method)
    local base = Config.ApiUrl .. '/api/v1/servers/' .. Config.ServerToken .. '/vote/'

    if method == 'discord' then
        return base .. 'discord/' .. identifier
    elseif method == 'steam' then
        return base .. 'steam/' .. identifier
    elseif method == 'ip' then
        return base .. 'ip/' .. identifier
    elseif method == 'username' then
        return base .. identifier
    end

    return nil
end

-- ============================================================
-- COOLDOWN MANAGEMENT
-- ============================================================
function IsOnCooldown(playerId)
    local now = os.time()
    if Cooldowns[playerId] and (now - Cooldowns[playerId]) < Config.CheckCooldown then
        return true
    end
    return false
end

function SetCooldown(playerId)
    Cooldowns[playerId] = os.time()
end

-- ============================================================
-- REWARD SYSTEM
-- ============================================================
function GiveRewards(playerId)
    local playerName = GetPlayerName(playerId)

    for _, reward in ipairs(Config.Rewards) do
        if reward.type == 'money' then
            GiveMoney(playerId, reward.account or 'money', reward.amount)

        elseif reward.type == 'item' then
            GiveItem(playerId, reward.name, reward.amount or 1, reward.metadata or {})

        elseif reward.type == 'weapon' then
            GiveWeapon(playerId, reward.name, reward.ammo or 0)
        end
    end

    -- Build reward summary for notification
    local rewardLabels = {}
    for _, reward in ipairs(Config.Rewards) do
        table.insert(rewardLabels, reward.label or reward.name)
    end

    return table.concat(rewardLabels, ', ')
end

-- ============================================================
-- GIVE MONEY
-- ============================================================
function GiveMoney(playerId, account, amount)
    if FrameworkType == 'esx' and Framework then
        local xPlayer = Framework.GetPlayerFromId(playerId)
        if xPlayer then
            xPlayer.addAccountMoney(account, amount)
        end

    elseif FrameworkType == 'qbcore' and Framework then
        local player = Framework.Functions.GetPlayer(playerId)
        if player then
            player.Functions.AddMoney(account, amount, 'nexarena-vote-reward')
        end

    else
        print('[NEXARENA] [STANDALONE] GiveMoney(' .. playerId .. ', ' .. account .. ', ' .. amount .. ')')
    end
end

-- ============================================================
-- GIVE ITEM (multi-inventory support)
-- ============================================================
function GiveItem(playerId, itemName, amount, metadata)
    -- ox_inventory (prioritaire si disponible)
    if InventoryType == 'ox_inventory' then
        local success = exports.ox_inventory:AddItem(playerId, itemName, amount, metadata or {})
        if not success then
            print('[NEXARENA] ^1Impossible de donner l\'item ' .. itemName .. ' au joueur ' .. playerId .. '^0')
        end
        return
    end

    -- ESX
    if FrameworkType == 'esx' and Framework then
        local xPlayer = Framework.GetPlayerFromId(playerId)
        if xPlayer then
            xPlayer.addInventoryItem(itemName, amount)
        end
        return
    end

    -- QBCore
    if FrameworkType == 'qbcore' and Framework then
        local player = Framework.Functions.GetPlayer(playerId)
        if player then
            player.Functions.AddItem(itemName, amount, false, metadata or {})
            TriggerClientEvent('inventory:client:ItemBox', playerId, QBCore.Shared.Items[itemName], 'add', amount)
        end
        return
    end

    print('[NEXARENA] [STANDALONE] GiveItem(' .. playerId .. ', ' .. itemName .. ', ' .. amount .. ')')
end

-- ============================================================
-- GIVE WEAPON
-- ============================================================
function GiveWeapon(playerId, weaponName, ammo)
    if FrameworkType == 'esx' and Framework then
        local xPlayer = Framework.GetPlayerFromId(playerId)
        if xPlayer then
            xPlayer.addWeapon(weaponName, ammo)
        end

    elseif FrameworkType == 'qbcore' and Framework then
        local player = Framework.Functions.GetPlayer(playerId)
        if player then
            player.Functions.AddItem(string.lower(weaponName), 1)
            if ammo > 0 then
                local ammoType = 'ammo-9'
                player.Functions.AddItem(ammoType, ammo)
            end
        end

    else
        -- Standalone: give weapon natively
        TriggerClientEvent('nexarena:client:giveWeapon', playerId, weaponName, ammo)
    end
end

-- ============================================================
-- DATABASE: UPDATE VOTE COUNT
-- ============================================================
function UpdateVoteCount(playerId, identifier)
    if not Config.Database.Enabled then return end

    local playerName = GetPlayerName(playerId) or 'Unknown'

    local query = [[
        INSERT INTO `nexarena_votes` (`identifier`, `player_name`, `vote_count`, `last_vote_at`, `created_at`)
        VALUES (?, ?, 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            `vote_count` = `vote_count` + 1,
            `player_name` = ?,
            `last_vote_at` = NOW()
    ]]

    ExecuteQuery(query, { identifier, playerName, playerName })
end

-- ============================================================
-- SEND NOTIFICATION
-- ============================================================
function SendNotification(playerId, title, message, type)
    TriggerClientEvent('nexarena:client:showNotification', playerId, {
        title    = title,
        message  = message,
        type     = type or 'info',       -- 'success', 'error', 'info', 'vote'
        duration = Config.Notification.Duration,
        position = Config.Notification.Position,
    })
end

function SendNotificationToAll(title, message, type)
    TriggerClientEvent('nexarena:client:showNotification', -1, {
        title    = title,
        message  = message,
        type     = type or 'vote',
        duration = Config.Notification.Duration,
        position = Config.Notification.Position,
    })
end

-- ============================================================
-- CHECK VOTE COMMAND
-- ============================================================
RegisterCommand(Config.Commands.CheckVote, function(source)
    local playerId = source
    if playerId <= 0 then return end

    -- Cooldown check
    if IsOnCooldown(playerId) then
        SendNotification(playerId, 'Nexarena', Config.Messages.Cooldown, 'error')
        return
    end

    -- Get identifier
    local identifier, method = GetCheckIdentifier(playerId)
    if not identifier then
        local msg = string.gsub(Config.Messages.NoIdentifier, '{method}', method)
        if method == 'discord' then
            msg = Config.Messages.NoDiscord
        elseif method == 'steam' then
            msg = Config.Messages.NoSteam
        end
        SendNotification(playerId, 'Nexarena', msg, 'error')
        return
    end

    -- Build API URL
    local url = BuildApiUrl(identifier, method)
    if not url then
        SendNotification(playerId, 'Nexarena', Config.Messages.ApiError, 'error')
        return
    end

    SetCooldown(playerId)

    -- API request
    PerformHttpRequest(url, function(statusCode, response)
        -- Check player still connected
        if not GetPlayerName(playerId) then return end

        if statusCode ~= 200 then
            if statusCode == 401 then
                print('[NEXARENA] ^1Token API invalide ! Verifiez Config.ServerToken^0')
            elseif statusCode == 403 then
                print('[NEXARENA] ^1IP non autorisee. Ajoutez l\'IP de votre serveur dans Nexarena.^0')
            elseif statusCode == 429 then
                print('[NEXARENA] ^3Rate limit API atteint. Reessayez plus tard.^0')
            end
            SendNotification(playerId, 'Nexarena', Config.Messages.ApiError, 'error')
            return
        end

        local data = json.decode(response)
        if not data then
            SendNotification(playerId, 'Nexarena', Config.Messages.ApiError, 'error')
            return
        end

        if not data.voted then
            SendNotification(playerId, 'Nexarena', Config.Messages.NotVoted, 'error')
            return
        end

        -- Check if already claimed (local cache)
        local cacheKey = identifier .. '_' .. (data.voted_at or '')
        if VoteCache[playerId] and VoteCache[playerId] == cacheKey then
            SendNotification(playerId, 'Nexarena', Config.Messages.AlreadyClaimed, 'error')
            return
        end

        -- Give rewards
        local rewardSummary = GiveRewards(playerId)
        VoteCache[playerId] = cacheKey

        -- Update database
        UpdateVoteCount(playerId, identifier)

        -- Personal notification
        SendNotification(playerId, 'Merci pour votre vote !', 'Recompenses : ' .. rewardSummary, 'success')

        -- Server-wide notification
        if Config.Notification.ShowToEveryone then
            local playerName = GetPlayerName(playerId) or 'Un joueur'
            local srvName = ServerName or 'le serveur'
            SendNotificationToAll(
                'Nouveau vote !',
                playerName .. ' a vote pour ' .. srvName .. ' sur Nexarena !',
                'vote'
            )
        end

        print('[NEXARENA] ' .. GetPlayerName(playerId) .. ' a recupere ses recompenses de vote.')

    end, 'GET', '', {
        ['Accept']       = 'application/json',
        ['Content-Type'] = 'application/json',
    })
end, false)

-- ============================================================
-- VOTE LINK COMMAND
-- ============================================================
RegisterCommand(Config.Commands.Vote, function(source)
    local playerId = source
    if playerId <= 0 then return end

    local voteUrl = Config.ApiUrl .. '/serveur/' .. Config.ServerSlug
    SendNotification(playerId, 'Votez pour nous !', 'Rendez-vous sur : ' .. voteUrl, 'info')

    TriggerClientEvent('chat:addMessage', playerId, {
        args = { string.gsub(Config.Messages.VoteLink, '{url}', voteUrl) }
    })
    TriggerClientEvent('chat:addMessage', playerId, {
        args = { Config.Messages.VoteReminder }
    })
end, false)

-- ============================================================
-- VOTE TOP COMMAND (requires database)
-- ============================================================
RegisterCommand(Config.Commands.VoteTop, function(source)
    local playerId = source
    if playerId <= 0 then return end

    if not Config.Database.Enabled then
        SendNotification(playerId, 'Nexarena', 'Le classement des votes n\'est pas active sur ce serveur.', 'error')
        return
    end

    FetchQuery('SELECT `player_name`, `vote_count` FROM `nexarena_votes` ORDER BY `vote_count` DESC LIMIT 10', {}, function(results)
        if not results or #results == 0 then
            SendNotification(playerId, 'Classement', 'Aucun vote enregistre.', 'info')
            return
        end

        TriggerClientEvent('chat:addMessage', playerId, {
            args = { '~g~[NEXARENA]~w~ === Top 10 Voteurs ===' }
        })

        for i, row in ipairs(results) do
            local medal = ''
            if i == 1 then medal = '^3[1er]^0 '
            elseif i == 2 then medal = '^5[2e]^0 '
            elseif i == 3 then medal = '^1[3e]^0 '
            else medal = '[' .. i .. 'e] '
            end

            TriggerClientEvent('chat:addMessage', playerId, {
                args = { medal .. (row.player_name or 'Inconnu') .. ' - ' .. row.vote_count .. ' votes' }
            })
        end
    end)
end, false)

-- ============================================================
-- CLEANUP ON DISCONNECT
-- ============================================================
AddEventHandler('playerDropped', function()
    local playerId = source
    Cooldowns[playerId] = nil
    VoteCache[playerId] = nil
end)

-- ============================================================
-- EXPORTS (pour utilisation externe)
-- ============================================================
exports('CheckPlayerVote', function(playerId, cb)
    local identifier, method = GetCheckIdentifier(playerId)
    if not identifier then
        if cb then cb(false, 'no_identifier') end
        return
    end

    local url = BuildApiUrl(identifier, method)
    if not url then
        if cb then cb(false, 'invalid_url') end
        return
    end

    PerformHttpRequest(url, function(statusCode, response)
        if statusCode == 200 then
            local data = json.decode(response)
            if cb then cb(data and data.voted or false, data) end
        else
            if cb then cb(false, 'api_error') end
        end
    end, 'GET', '', {
        ['Accept']       = 'application/json',
        ['Content-Type'] = 'application/json',
    })
end)

exports('GetPlayerVoteCount', function(playerId, cb)
    if not Config.Database.Enabled then
        if cb then cb(0) end
        return
    end

    local identifier = GetCheckIdentifier(playerId)
    if not identifier then
        if cb then cb(0) end
        return
    end

    FetchQuery('SELECT `vote_count` FROM `nexarena_votes` WHERE `identifier` = ?', { identifier }, function(results)
        if results and #results > 0 then
            if cb then cb(results[1].vote_count) end
        else
            if cb then cb(0) end
        end
    end)
end)
