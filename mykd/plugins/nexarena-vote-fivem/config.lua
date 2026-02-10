Config = {}

-- =============================================================================
-- Nexarena API Configuration
-- =============================================================================

-- Your server API token (found in your server management panel on Nexarena)
Config.ServerToken = 'YOUR_SERVER_TOKEN_HERE'

-- Base URL of the Nexarena API (no trailing slash)
Config.ApiUrl = 'https://nexarena.fr'

-- The public vote page URL shown to players
-- Replace {slug} with your actual server slug from Nexarena
Config.VoteUrl = 'https://nexarena.fr/serveur/{slug}'

-- =============================================================================
-- Reward Configuration
-- =============================================================================

-- Framework detection: 'auto', 'esx', 'qbcore', 'standalone'
-- 'auto' will try ESX first, then QBCore, then standalone
Config.Framework = 'auto'

-- Money reward amount given when a player has voted
Config.RewardMoney = 50000

-- Money account to add the reward to (ESX: 'money', 'bank', 'black_money')
Config.MoneyAccount = 'money'

-- =============================================================================
-- Cooldown Configuration
-- =============================================================================

-- Cooldown in seconds before a player can claim their reward again
-- This is a LOCAL cooldown to avoid API spam. The actual vote cooldown is
-- managed server-side by Nexarena.
Config.ClaimCooldown = 120 -- 2 minutes

-- =============================================================================
-- Messages
-- =============================================================================

Config.Messages = {
    -- Vote command
    VoteUrl           = '^2[Nexarena]^0 Vote for our server: ^3%s',
    VoteReminder      = '^2[Nexarena]^0 After voting, use ^3/checkvote^0 to claim your reward!',

    -- Check vote results
    VoteConfirmed     = '^2[Nexarena]^0 Thank you for voting! You received ^3$%s^0!',
    VoteNotFound      = '^1[Nexarena]^0 No vote found. Please vote first at: ^3%s',
    NoDiscord         = '^1[Nexarena]^0 You must have Discord connected to FiveM to check your vote.',
    AlreadyClaimed    = '^1[Nexarena]^0 You already claimed your reward. Try again in ^3%d^0 seconds.',
    CheckingVote      = '^2[Nexarena]^0 Checking your vote...',

    -- Errors
    ApiError          = '^1[Nexarena]^0 Could not verify your vote. Please try again later.',
    InvalidToken      = '^1[Nexarena]^0 Server configuration error. Please contact an administrator.',
}

-- =============================================================================
-- Command Names
-- =============================================================================

Config.Commands = {
    Vote      = 'vote',       -- Opens/shows the vote URL
    CheckVote = 'checkvote',  -- Checks vote status and claims reward
}
