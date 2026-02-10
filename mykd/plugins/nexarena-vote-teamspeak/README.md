# Nexarena Vote - Bot TeamSpeak 3

Bot TeamSpeak 3 qui permet a vos joueurs de verifier leur vote Nexarena et de recevoir automatiquement un groupe serveur en recompense.

## Prerequis

- Python 3.10 ou superieur
- Un serveur TeamSpeak 3 avec acces ServerQuery
- Un serveur enregistre sur [Nexarena](https://nexarena.fr) avec un token API

## Installation

1. **Cloner ou copier** les fichiers dans un dossier sur votre machine.

2. **Installer les dependances** :

```bash
pip install -r requirements.txt
```

3. **Configurer** le fichier `config.json` :

```json
{
  "ts_host": "127.0.0.1",
  "ts_port": 10011,
  "ts_user": "serveradmin",
  "ts_password": "VOTRE_MOT_DE_PASSE_SERVERQUERY",
  "ts_virtual_server_id": 1,
  "nexarena_api_url": "https://nexarena.fr",
  "nexarena_server_token": "VOTRE_TOKEN_SERVEUR",
  "vote_url": "https://nexarena.fr/serveur/votre-serveur",
  "reward_group_id": 10,
  "bot_nickname": "NexarenaBot",
  "command_prefix": "!",
  "cooldown_seconds": 30,
  "reconnect_delay": 10,
  "api_timeout": 10
}
```

### Parametres

| Parametre | Description |
|---|---|
| `ts_host` | Adresse IP du serveur TeamSpeak |
| `ts_port` | Port ServerQuery (par defaut : 10011) |
| `ts_user` | Identifiant ServerQuery |
| `ts_password` | Mot de passe ServerQuery |
| `ts_virtual_server_id` | ID du serveur virtuel (par defaut : 1) |
| `nexarena_api_url` | URL de base Nexarena |
| `nexarena_server_token` | Token API de votre serveur (dans Gestion > API) |
| `vote_url` | Lien direct vers la page de vote de votre serveur |
| `reward_group_id` | ID du groupe serveur a attribuer (0 = desactive) |
| `bot_nickname` | Pseudo du bot sur TeamSpeak |
| `command_prefix` | Prefixe des commandes (par defaut : `!`) |
| `cooldown_seconds` | Delai entre deux utilisations de `!checkvote` par utilisateur |
| `reconnect_delay` | Delai en secondes avant tentative de reconnexion |
| `api_timeout` | Timeout des appels API en secondes |

## Utilisation

### Demarrer le bot

```bash
python nexarena_vote.py
```

### Commandes disponibles

Les joueurs envoient ces commandes en **message prive** au bot :

| Commande | Description |
|---|---|
| `!vote` | Affiche le lien de vote |
| `!checkvote <pseudo>` | Verifie le vote et attribue le groupe recompense |

### Exemple

```
Joueur -> Bot : !vote
Bot -> Joueur : Votez pour notre serveur ici : https://nexarena.fr/serveur/mon-serveur
                Apres avoir vote, utilisez !checkvote <votre_pseudo> pour recevoir votre recompense.

Joueur -> Bot : !checkvote MonPseudo
Bot -> Joueur : Vote confirme pour MonPseudo !
                Le groupe recompense vous a ete attribue. Merci d'avoir vote !
```

## Trouver l'ID du groupe recompense

1. Connectez-vous en ServerQuery ou utilisez le client TeamSpeak
2. Allez dans **Permissions** > **Groupes de serveurs**
3. Notez l'ID du groupe que vous souhaitez attribuer aux voteurs
4. Renseignez cet ID dans `reward_group_id` de `config.json`

## Execution en arriere-plan

### Linux (systemd)

Creez le fichier `/etc/systemd/system/nexarena-vote-ts.service` :

```ini
[Unit]
Description=Nexarena Vote Bot TeamSpeak
After=network.target

[Service]
Type=simple
User=nexarena
WorkingDirectory=/opt/nexarena-vote-teamspeak
ExecStart=/usr/bin/python3 nexarena_vote.py
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Puis :

```bash
sudo systemctl daemon-reload
sudo systemctl enable nexarena-vote-ts
sudo systemctl start nexarena-vote-ts
```

### Windows

Utilisez le Planificateur de taches ou un outil comme [NSSM](https://nssm.cc/) pour executer le script en tant que service.

## Logs

Les logs sont affiches dans la console et enregistres dans `nexarena_vote.log` dans le meme dossier que le script.

## Support

Pour toute question, rendez-vous sur [Nexarena](https://nexarena.fr).
