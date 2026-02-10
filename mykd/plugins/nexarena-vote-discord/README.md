# Nexarena Vote - Bot Discord

Bot Discord pour verifier les votes et attribuer des recompenses via l'API Nexarena.

## Prerequis

- **Node.js 18+** (utilise `fetch` natif)
- Un bot Discord cree sur le [portail developpeur](https://discord.com/developers/applications)

## Installation

```bash
cd nexarena-vote-discord
npm install
```

## Configuration

Editez le fichier `config.json` :

| Cle | Description |
|-----|-------------|
| `bot_token` | Token du bot Discord |
| `client_id` | ID de l'application Discord |
| `guild_id` | ID du serveur Discord |
| `nexarena_api_url` | URL de base Nexarena (`https://nexarena.fr`) |
| `nexarena_server_token` | Token API de votre serveur (page Gestion > API) |
| `vote_url` | Lien direct vers la page de vote de votre serveur |
| `reward_role_id` | ID du role a attribuer aux voteurs |
| `embed_color` | Couleur des embeds (defaut : `#45f882`) |

## Permissions du bot

Lors de l'invitation du bot, activez ces permissions :
- **Manage Roles** - pour attribuer le role de recompense
- **Send Messages** - pour repondre aux commandes
- **Use Slash Commands** - pour les commandes `/vote` et `/checkvote`

Lien d'invitation :
```
https://discord.com/api/oauth2/authorize?client_id=VOTRE_CLIENT_ID&permissions=268435456&scope=bot%20applications.commands
```

Le role du bot doit etre **au-dessus** du role de recompense dans la hierarchie des roles du serveur.

## Lancement

```bash
npm start
```

## Commandes

| Commande | Description |
|----------|-------------|
| `/vote` | Affiche le lien pour voter sur Nexarena |
| `/checkvote` | Verifie le vote et attribue le role de recompense |

## API utilisee

```
GET /api/v1/servers/{token}/vote/discord/{discordId}
```

Reponse :
```json
{
  "voted": true,
  "discord_id": "123456789",
  "voted_at": "2026-02-10T14:30:00+00:00"
}
```
