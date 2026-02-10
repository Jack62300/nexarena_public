-- =============================================================================
-- Nexarena Vote Reward - Server Side
-- =============================================================================

local cooldowns = {}  -- [playerId] = os.time() of last successful claim
local ESX = nil
local QBCore = nil
local frameworkReady = false

-- =============================================================================
-- Framework Detection
-- =============================================================================

local function InitFramework()
    if frameworkReady then return end

    local fw = Config.Framework

    if fw == 'auto' or fw == 'esx' then
        -- Try ESX (new export method first, then legacy)
        local success, result = pcall(function()
            return exports['es_extended']:getSharedObject()
        end)
        if success and result then
            ESX = result
            frameworkReady = true
            print('[Nexarena] Framework detected: ESX (export)')
            return
        end

        -- Try ESX legacy event
        TriggerEvent('esx:getSharedObject', function(obj)
            ESX = obj
        end)
        if ESX then
            frameworkReady = true
            print('[Nexarena] Framework detected: ESX (event)')
            return
        end
    end

    if fw == 'auto' or fw == 'qbcore' then
        local success, result = pcall(function()
            return exports['qb-core']:GetCoreObject()
        end)
        if success and result then
            QBCore = result
            frameworkReady = true
            print('[Nexarena] Framework detected: QBCore')
            return
        end
    end

    if fw == 'auto' or fw == 'standalone' then
        frameworkReady = true
        print('[Nexarena] Running in standalone mode (no framework)')
    end
end

-- Initialize after a short delay to let other resources load
CreateThread(function()
    Wait(2000)
    InitFramework()
end)

-- =============================================================================
-- Helper Functions
-- =============================================================================

--- Get the Discord identifier from a player's identifiers
---@param playerId number
---@return string|nil discordId The raw Discord user ID (numbers only) or nil
local function GetDiscordId(playerId)
    local identifiers = GetPlayerIdentifiers(playerId)
    for _, id in pairs(identifiers) do
        if string.find(id, 'discord:') then
            return string.gsub(id, 'discord:', '')
        end
    end
    return nil
end

--- Check if a player is still on cooldown
---@param playerId number
---@return boolean onCooldown
---@return number remainingSeconds
local function IsOnCooldown(playerId)
    local lastClaim = cooldowns[playerId]
    if not lastClaim then
        return false, 0
    end

    local elapsed = os.time() - lastClaim
    local remaining = Config.ClaimCooldown - elapsed

    if remaining > 0 then
        return true, remaining
    end

    return false, 0
end

--- Give money reward to a player
---@param playerId number
---@param amount number
---@return boolean success
local function GiveReward(playerId, amount)
    if not frameworkReady then
        InitFramework()
    end

    -- ESX
    if ESX then
        local xPlayer = ESX.GetPlayerFromId(playerId)
        if xPlayer then
            xPlayer.addAccountMoney(Config.MoneyAccount, amount)
            return true
        end
        return false
    end

    -- QBCore
    if QBCore then
        local player = QBCore.Functions.GetPlayer(playerId)
        if player then
            player.Functions.AddMoney('cash', amount, 'nexarena-vote-reward')
            return true
        end
        return false
    end

    -- Standalone: try common money export patterns
    -- You can customize this section for your specific server setup
    local success = pcall(function()
        exports['money']:AddMoney(playerId, amount)
    end)
    if success then return true end

    -- Fallback: log warning
    print(string.format(
        '[Nexarena] WARNING: No framework detected. Cannot give $%d to player %d. '
        .. 'Set Config.Framework or implement your own reward logic in GiveReward().',
        amount, playerId
    ))
    return false
end

--- Build the API URL for vote checking
---@param discordId string
---@return string url
local function BuildApiUrl(discordId)
    return string.format(
        '%s/api/v1/servers/%s/vote/discord/%s',
        Config.ApiUrl,
        Config.ServerToken,
        discordId
    )
end

-- =============================================================================
-- Check Vote Command
-- =============================================================================

RegisterCommand(Config.Commands.CheckVote, function(source, args, rawCommand)
    local playerId = source

    -- Only players can use this (not RCON)
    if playerId <= 0 then
        print('[Nexarena] This command can only be used by players.')
        return
    end

    -- Check cooldown
    local onCooldown, remaining = IsOnCooldown(playerId)
    if onCooldown then
        TriggerClientEvent('chat:addMessage', playerId, {
            args = { string.format(Config.Messages.AlreadyClaimed, remaining) }
        })
        return
    end

    -- Get Discord ID
    local discordId = GetDiscordId(playerId)
    if not discordId then
        TriggerClientEvent('chat:addMessage', playerId, {
            args = { Config.Messages.NoDiscord }
        })
        return
    end

    -- Notify player we are checking
    TriggerClientEvent('chat:addMessage', playerId, {
        args = { Config.Messages.CheckingVote }
    })

    -- Call Nexarena API
    local url = BuildApiUrl(discordId)

    PerformHttpRequest(url, function(statusCode, responseText, headers)
        -- Check if player is still connected
        if not GetPlayerName(playerId) then
            return
        end

        -- Handle HTTP errors
        if statusCode ~= 200 then
            if statusCode == 404 then
                TriggerClientEvent('chat:addMessage', playerId, {
                    args = { Config.Messages.InvalidToken }
                })
            else
                print(string.format(
                    '[Nexarena] API error for player %d (Discord: %s): HTTP %d - %s',
                    playerId, discordId, statusCode, tostring(responseText)
                ))
                TriggerClientEvent('chat:addMessage', playerId, {
                    args = { Config.Messages.ApiError }
                })
            end
            return
        end

        -- Parse JSON response
        local data = json.decode(responseText)
        if not data then
            print('[Nexarena] Failed to parse API response: ' .. tostring(responseText))
            TriggerClientEvent('chat:addMessage', playerId, {
                args = { Config.Messages.ApiError }
            })
            return
        end

        -- Check vote status
        if data.voted then
            -- Give reward
            local success = GiveReward(playerId, Config.RewardMoney)

            if success then
                -- Set cooldown
                cooldowns[playerId] = os.time()

                TriggerClientEvent('chat:addMessage', playerId, {
                    args = { string.format(Config.Messages.VoteConfirmed, Config.RewardMoney) }
                })

                local playerName = GetPlayerName(playerId)
                print(string.format(
                    '[Nexarena] Vote reward given: %s (ID: %d, Discord: %s) - $%d',
                    playerName, playerId, discordId, Config.RewardMoney
                ))
            else
                TriggerClientEvent('chat:addMessage', playerId, {
                    args = { Config.Messages.ApiError }
                })
            end
        else
            TriggerClientEvent('chat:addMessage', playerId, {
                args = { string.format(Config.Messages.VoteNotFound, Config.VoteUrl) }
            })
        end
    end, 'GET', '', {
        ['Content-Type'] = 'application/json',
        ['Accept'] = 'application/json',
    })
end, false) -- false = no ACE permission required

-- =============================================================================
-- Cleanup: remove cooldown entry when player disconnects
-- =============================================================================

AddEventHandler('playerDropped', function(reason)
    local playerId = source
    cooldowns[playerId] = nil
end)

-- =============================================================================
-- Startup
-- =============================================================================

print('[Nexarena] Vote reward resource loaded successfully.')

if Config.ServerToken == 'YOUR_SERVER_TOKEN_HERE' then
    print('[Nexarena] WARNING: Server token is not configured! Edit config.lua and set your token.')
end
