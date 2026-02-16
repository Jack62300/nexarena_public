-- ============================================================
-- NEXARENA VOTE REWARDS - CLIENT SIDE
-- ============================================================

-- ============================================================
-- CHAT SUGGESTIONS
-- ============================================================
CreateThread(function()
    TriggerEvent('chat:addSuggestion', '/' .. Config.Commands.Vote, 'Affiche le lien pour voter sur Nexarena')
    TriggerEvent('chat:addSuggestion', '/' .. Config.Commands.CheckVote, 'Verifie votre vote et recupere vos recompenses')
    TriggerEvent('chat:addSuggestion', '/' .. Config.Commands.VoteTop, 'Affiche le classement des meilleurs voteurs')
end)

-- ============================================================
-- GIVE WEAPON (standalone mode)
-- ============================================================
RegisterNetEvent('nexarena:client:giveWeapon', function(weaponName, ammo)
    local ped = PlayerPedId()
    local hash = GetHashKey(weaponName)
    GiveWeaponToPed(ped, hash, ammo or 0, false, true)
end)
