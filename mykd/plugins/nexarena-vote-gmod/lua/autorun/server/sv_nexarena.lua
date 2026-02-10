--[[
    Nexarena Vote - Server Side
    https://nexarena.fr

    Handles !checkvote chat command:
    - Fetches player's SteamID64
    - Calls the Nexarena API to check vote status
    - Awards DarkRP money if the player has voted
    - Tracks cooldowns to prevent API spam
]]

-- Load shared config
include("nexarena/config.lua")
AddCSLuaFile("nexarena/config.lua")
AddCSLuaFile("autorun/client/cl_nexarena.lua")

-- Cooldown tracking table: SteamID64 -> timestamp of last successful claim
local claimCooldowns = {}

-- Network string for client communication
util.AddNetworkString("nexarena_notify")

--- Send a colored chat message to a player via net message
-- @param ply Player to notify
-- @param msg string Message text
local function NotifyPlayer(ply, msg)
    if not IsValid(ply) then return end

    net.Start("nexarena_notify")
        net.WriteString(msg)
    net.Send(ply)
end

--- Debug log helper
-- @param msg string Message to log
local function DebugLog(msg)
    if NexarenaConfig.Debug then
        print("[Nexarena Debug] " .. msg)
    end
end

--- Check if a player is on claim cooldown
-- @param steamid64 string Player's SteamID64
-- @return boolean Whether the player is on cooldown
-- @return number Remaining minutes (0 if not on cooldown)
local function IsOnCooldown(steamid64)
    local lastClaim = claimCooldowns[steamid64]
    if not lastClaim then
        return false, 0
    end

    local elapsed = os.time() - lastClaim
    local cooldownSeconds = NexarenaConfig.CooldownMinutes * 60

    if elapsed < cooldownSeconds then
        local remaining = math.ceil((cooldownSeconds - elapsed) / 60)
        return true, remaining
    end

    return false, 0
end

--- Award the vote reward to a player
-- @param ply Player to reward
local function AwardReward(ply)
    if not IsValid(ply) then return end

    local amount = NexarenaConfig.RewardMoney

    -- DarkRP money integration
    if amount > 0 then
        if ply.addMoney then
            -- DarkRP is available
            ply:addMoney(amount)
            DebugLog("Awarded $" .. amount .. " to " .. ply:Nick() .. " (" .. ply:SteamID64() .. ")")
        else
            -- DarkRP not available, log a warning
            DebugLog("WARNING: DarkRP not detected. Cannot award money to " .. ply:Nick())
            print("[Nexarena] WARNING: ply:addMoney() not available. Is DarkRP installed?")
        end
    end

    -- Send success message
    local msg = string.format(NexarenaConfig.RewardMessage, string.Comma(amount))
    NotifyPlayer(ply, msg)

    -- Set cooldown
    claimCooldowns[ply:SteamID64()] = os.time()
end

--- Query the Nexarena API to check if a player has voted
-- @param ply Player who ran the command
local function CheckVote(ply)
    if not IsValid(ply) then return end

    local steamid64 = ply:SteamID64()

    if not steamid64 or steamid64 == "" then
        NotifyPlayer(ply, "Impossible de recuperer votre SteamID. Reconnectez-vous.")
        return
    end

    -- Check local cooldown first to avoid unnecessary API calls
    local onCooldown, remaining = IsOnCooldown(steamid64)
    if onCooldown then
        local msg = string.format(NexarenaConfig.CooldownMessage, remaining)
        NotifyPlayer(ply, msg)
        return
    end

    -- Validate config
    if NexarenaConfig.ServerToken == "CHANGE_ME" or NexarenaConfig.ServerToken == "" then
        NotifyPlayer(ply, "Erreur de configuration serveur. Contactez un administrateur.")
        print("[Nexarena] ERROR: ServerToken is not configured! Edit lua/nexarena/config.lua")
        return
    end

    -- Build API URL: /api/v1/servers/{token}/vote/{username}
    -- We use SteamID64 as the username parameter
    local url = string.format(
        "%s/api/v1/servers/%s/vote/%s",
        NexarenaConfig.ApiUrl,
        NexarenaConfig.ServerToken,
        steamid64
    )

    DebugLog("Checking vote for " .. ply:Nick() .. " (SteamID64: " .. steamid64 .. ")")
    DebugLog("API URL: " .. url)

    -- Async HTTP request using GMod's HTTP() function
    HTTP({
        url = url,
        method = "GET",
        headers = {
            ["Accept"] = "application/json",
            ["User-Agent"] = "Nexarena-GMod-Plugin/1.0",
        },
        success = function(code, body, headers)
            if not IsValid(ply) then return end

            DebugLog("API Response [" .. code .. "]: " .. body)

            if code ~= 200 then
                if code == 404 then
                    NotifyPlayer(ply, "Erreur: Token serveur invalide. Contactez un administrateur.")
                    print("[Nexarena] ERROR: API returned 404 - Invalid server token")
                else
                    NotifyPlayer(ply, "Erreur API (code " .. code .. "). Reessayez plus tard.")
                    print("[Nexarena] ERROR: API returned HTTP " .. code)
                end
                return
            end

            -- Parse JSON response
            local data = util.JSONToTable(body)
            if not data then
                NotifyPlayer(ply, "Erreur: Reponse API invalide. Reessayez plus tard.")
                print("[Nexarena] ERROR: Failed to parse JSON response: " .. body)
                return
            end

            if data.voted then
                -- Player has voted, award the reward
                DebugLog("Vote confirmed for " .. ply:Nick() .. " at " .. tostring(data.voted_at))
                AwardReward(ply)
            else
                -- Player has NOT voted
                NotifyPlayer(ply, NexarenaConfig.NotVotedMessage)
            end
        end,
        failed = function(reason)
            if not IsValid(ply) then return end

            NotifyPlayer(ply, "Erreur de connexion au serveur de vote. Reessayez plus tard.")
            print("[Nexarena] ERROR: HTTP request failed - " .. tostring(reason))
        end,
    })
end

-- Register the !checkvote chat command via PlayerSay hook
hook.Add("PlayerSay", "Nexarena_CheckVote", function(ply, text)
    local cmd = string.lower(string.Trim(text))

    if cmd == "!checkvote" or cmd == "!claimvote" or cmd == "!recompense" then
        -- Run the check on next tick to avoid blocking the chat hook
        timer.Simple(0, function()
            if IsValid(ply) then
                CheckVote(ply)
            end
        end)

        -- Return empty string to suppress the command from chat
        return ""
    end
end)

-- Clean up cooldowns when a player disconnects (optional, prevents memory buildup)
hook.Add("PlayerDisconnected", "Nexarena_CleanupCooldowns", function(ply)
    if IsValid(ply) then
        local steamid64 = ply:SteamID64()
        if steamid64 then
            claimCooldowns[steamid64] = nil
        end
    end
end)

-- Startup message
hook.Add("Initialize", "Nexarena_Init", function()
    print("[Nexarena] Vote plugin loaded successfully.")
    if NexarenaConfig.ServerToken == "CHANGE_ME" then
        print("[Nexarena] WARNING: ServerToken is not configured! Edit lua/nexarena/config.lua")
    end
    if NexarenaConfig.Debug then
        print("[Nexarena] Debug mode is ENABLED.")
    end
end)

print("[Nexarena] Server-side vote script loaded.")
