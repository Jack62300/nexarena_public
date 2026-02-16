-- ============================================================
-- NEXARENA CUSTOM NOTIFICATION SYSTEM - CLIENT SIDE
-- ============================================================

local isNuiReady = false

-- ============================================================
-- NUI READY
-- ============================================================
CreateThread(function()
    Wait(1000)
    isNuiReady = true
end)

-- ============================================================
-- RECEIVE NOTIFICATION FROM SERVER
-- ============================================================
RegisterNetEvent('nexarena:client:showNotification', function(data)
    if not isNuiReady then return end

    SendNUIMessage({
        action   = 'showNotification',
        title    = data.title or 'Nexarena',
        message  = data.message or '',
        type     = data.type or 'info',
        duration = data.duration or 8000,
        position = data.position or 'top',
    })
end)
