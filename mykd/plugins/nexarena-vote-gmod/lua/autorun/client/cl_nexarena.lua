--[[
    Nexarena Vote - Client Side
    https://nexarena.fr

    Handles:
    - !vote chat command: Opens the vote URL in the Steam overlay browser
    - Receiving chat notifications from the server
]]

-- Load shared config
include("nexarena/config.lua")

--- Send a colored chat message to the local player
-- @param msg string Message text
local function ChatNotify(msg)
    chat.AddText(
        NexarenaConfig.PrefixColor, NexarenaConfig.Prefix,
        NexarenaConfig.TextColor, msg
    )
end

-- Register the !vote chat command via PlayerSay hook
hook.Add("PlayerSay", "Nexarena_VoteCommand", function(ply, text)
    if ply ~= LocalPlayer() then return end

    local cmd = string.lower(string.Trim(text))

    if cmd == "!vote" or cmd == "!voter" then
        -- Open the vote URL in Steam overlay browser on the next frame
        timer.Simple(0, function()
            local voteUrl = NexarenaConfig.VoteUrl

            if not voteUrl or voteUrl == "" or string.find(voteUrl, "your%-server%-slug") then
                ChatNotify("Erreur: L'URL de vote n'est pas configuree. Contactez un administrateur.")
                return
            end

            gui.OpenURL(voteUrl)
            ChatNotify(NexarenaConfig.VoteOpenMessage)
        end)

        -- Return empty string to suppress the command from chat
        return ""
    end
end)

-- Receive notifications from the server
net.Receive("nexarena_notify", function()
    local msg = net.ReadString()
    if msg and msg ~= "" then
        ChatNotify(msg)
        -- Also play a subtle sound for feedback
        surface.PlaySound("buttons/button15.wav")
    end
end)

-- Print available commands when the player spawns for the first time
local hasShownHelp = false
hook.Add("InitPostEntity", "Nexarena_ClientInit", function()
    -- Delay to ensure chat is ready
    timer.Simple(5, function()
        if not hasShownHelp then
            hasShownHelp = true
            ChatNotify("Tapez !vote pour voter et !checkvote pour recuperer votre recompense.")
        end
    end)
end)

print("[Nexarena] Client-side vote script loaded.")
