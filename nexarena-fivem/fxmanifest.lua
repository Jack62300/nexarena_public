fx_version 'cerulean'
game 'gta5'

name 'nexarena-fivem'
description 'Nexarena Vote Rewards - Systeme de verification et recompenses de vote'
author 'Nexarena'
version '2.0.0'
url 'https://nexarena.fr'

lua54 'yes'

shared_scripts {
    'config.lua',
}

server_scripts {
    'server/main.lua',
}

client_scripts {
    'client/main.lua',
    'client/notification.lua',
}

ui_page 'html/notification.html'

files {
    'html/notification.html',
    'html/notification.css',
    'html/notification.js',
}
