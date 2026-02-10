-- =============================================================================
-- Nexarena Vote Reward - Client Side
-- =============================================================================

-- =============================================================================
-- Vote Command: Show vote URL in chat
-- =============================================================================

RegisterCommand(Config.Commands.Vote, function(source, args, rawCommand)
    -- Show the vote URL in chat
    TriggerEvent('chat:addMessage', {
        args = { string.format(Config.Messages.VoteUrl, Config.VoteUrl) }
    })

    -- Show reminder about /checkvote
    TriggerEvent('chat:addMessage', {
        args = { Config.Messages.VoteReminder }
    })
end, false)

-- =============================================================================
-- Chat Suggestions: Show command hints in the chat input
-- =============================================================================

TriggerEvent('chat:addSuggestion', '/' .. Config.Commands.Vote, 'Open the vote page for our server on Nexarena.', {})

TriggerEvent('chat:addSuggestion', '/' .. Config.Commands.CheckVote, 'Check your vote status and claim your reward.', {})
