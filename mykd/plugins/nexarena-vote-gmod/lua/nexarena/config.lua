--[[
    Nexarena Vote - Configuration
    https://nexarena.fr

    Shared configuration file loaded by both server and client.
    Edit this file to match your server settings.
]]

NexarenaConfig = NexarenaConfig or {}

-- Your server API token (found in your server management panel on Nexarena)
-- IMPORTANT: Keep this secret! Only the server needs it.
NexarenaConfig.ServerToken = "CHANGE_ME"

-- Nexarena API base URL (no trailing slash)
NexarenaConfig.ApiUrl = "https://nexarena.fr"

-- The vote page URL that players will be directed to
-- Replace "your-server-slug" with your actual server slug from Nexarena
NexarenaConfig.VoteUrl = "https://nexarena.fr/serveur/your-server-slug"

-- Reward amount (DarkRP money) given when a player has voted
-- Set to 0 to disable money rewards
NexarenaConfig.RewardMoney = 10000

-- Message sent to the player when they successfully claim their vote reward
NexarenaConfig.RewardMessage = "Merci d'avoir vote ! Vous avez recu $%s en recompense."

-- Message sent when the player has NOT voted yet
NexarenaConfig.NotVotedMessage = "Vous n'avez pas encore vote. Tapez !vote pour ouvrir la page de vote."

-- Message sent when the player is on cooldown (already claimed their reward)
NexarenaConfig.CooldownMessage = "Vous avez deja recupere votre recompense. Reessayez dans %s minutes."

-- Message shown when the vote URL opens
NexarenaConfig.VoteOpenMessage = "La page de vote s'ouvre dans votre navigateur Steam. Tapez !checkvote apres avoir vote pour recuperer votre recompense."

-- Cooldown in minutes before a player can claim again (prevents spamming the API)
-- This is a LOCAL cooldown only; the actual vote interval is controlled server-side by Nexarena.
NexarenaConfig.CooldownMinutes = 5

-- Chat command prefix color (RGB)
NexarenaConfig.PrefixColor = Color(69, 248, 130)

-- Chat command text color (RGB)
NexarenaConfig.TextColor = Color(255, 255, 255)

-- Prefix shown in chat messages
NexarenaConfig.Prefix = "[Nexarena] "

-- Enable debug prints in server console
NexarenaConfig.Debug = false
