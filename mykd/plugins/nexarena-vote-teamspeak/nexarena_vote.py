#!/usr/bin/env python3
"""
Nexarena Vote Bot pour TeamSpeak 3
Verifie les votes via l'API Nexarena et attribue un groupe serveur.

Commandes:
  !vote              - Affiche le lien de vote
  !checkvote <pseudo> - Verifie le vote et attribue le groupe recompense
"""

import json
import logging
import sys
import time
from pathlib import Path

import requests
import ts3

# ---------------------------------------------------------------------------
# Logging
# ---------------------------------------------------------------------------
logging.basicConfig(
    level=logging.INFO,
    format="[%(asctime)s] [%(levelname)s] %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S",
    handlers=[
        logging.StreamHandler(sys.stdout),
        logging.FileHandler("nexarena_vote.log", encoding="utf-8"),
    ],
)
logger = logging.getLogger("nexarena-vote-ts")

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------
CONFIG_PATH = Path(__file__).parent / "config.json"


def load_config() -> dict:
    """Charge la configuration depuis config.json."""
    if not CONFIG_PATH.exists():
        logger.error("Fichier config.json introuvable: %s", CONFIG_PATH)
        sys.exit(1)

    with open(CONFIG_PATH, "r", encoding="utf-8") as fh:
        config = json.load(fh)

    required_keys = [
        "ts_host",
        "ts_port",
        "ts_user",
        "ts_password",
        "ts_virtual_server_id",
        "nexarena_api_url",
        "nexarena_server_token",
        "vote_url",
        "reward_group_id",
    ]
    missing = [k for k in required_keys if k not in config]
    if missing:
        logger.error("Cles manquantes dans config.json: %s", ", ".join(missing))
        sys.exit(1)

    return config


# ---------------------------------------------------------------------------
# Nexarena API
# ---------------------------------------------------------------------------
class NexarenaAPI:
    """Client HTTP pour l'API Nexarena."""

    def __init__(self, base_url: str, server_token: str, timeout: int = 10):
        self.base_url = base_url.rstrip("/")
        self.server_token = server_token
        self.timeout = timeout
        self.session = requests.Session()
        self.session.headers.update({
            "Accept": "application/json",
            "User-Agent": "NexarenaVoteBot-TeamSpeak/1.0",
        })

    def check_vote(self, username: str) -> dict | None:
        """
        Verifie si un utilisateur a vote.
        GET /api/v1/servers/{token}/vote/{username}
        Retourne le JSON de reponse ou None en cas d'erreur.
        """
        url = f"{self.base_url}/api/v1/servers/{self.server_token}/vote/{username}"
        try:
            resp = self.session.get(url, timeout=self.timeout)
            resp.raise_for_status()
            return resp.json()
        except requests.ConnectionError:
            logger.error("Impossible de joindre l'API Nexarena (%s)", url)
        except requests.Timeout:
            logger.error("Timeout lors de l'appel API Nexarena (%s)", url)
        except requests.HTTPError as exc:
            logger.error("Erreur HTTP %s: %s", exc.response.status_code, exc.response.text)
        except Exception as exc:
            logger.error("Erreur inattendue lors de l'appel API: %s", exc)
        return None


# ---------------------------------------------------------------------------
# Cooldown tracker
# ---------------------------------------------------------------------------
class CooldownTracker:
    """Suivi des cooldowns par identifiant unique client."""

    def __init__(self, cooldown_seconds: int = 30):
        self.cooldown = cooldown_seconds
        self._last_use: dict[str, float] = {}

    def is_on_cooldown(self, unique_id: str) -> bool:
        last = self._last_use.get(unique_id, 0)
        return (time.time() - last) < self.cooldown

    def remaining(self, unique_id: str) -> int:
        last = self._last_use.get(unique_id, 0)
        rem = self.cooldown - (time.time() - last)
        return max(0, int(rem))

    def mark(self, unique_id: str) -> None:
        self._last_use[unique_id] = time.time()


# ---------------------------------------------------------------------------
# Bot principal
# ---------------------------------------------------------------------------
class NexarenaTeamSpeakBot:
    """Bot TeamSpeak ServerQuery pour le systeme de vote Nexarena."""

    def __init__(self, config: dict):
        self.config = config
        self.api = NexarenaAPI(
            base_url=config["nexarena_api_url"],
            server_token=config["nexarena_server_token"],
            timeout=config.get("api_timeout", 10),
        )
        self.cooldown = CooldownTracker(config.get("cooldown_seconds", 30))
        self.prefix = config.get("command_prefix", "!")
        self.reconnect_delay = config.get("reconnect_delay", 10)
        self.ts: ts3.query.TS3ServerConnection | None = None
        self._running = True

    # -- Connection ----------------------------------------------------------

    def connect(self) -> None:
        """Etablit la connexion ServerQuery et selectionne le serveur virtuel."""
        logger.info(
            "Connexion a TeamSpeak %s:%s ...",
            self.config["ts_host"],
            self.config["ts_port"],
        )
        self.ts = ts3.query.TS3ServerConnection(
            self.config["ts_host"],
            self.config["ts_port"],
        )
        self.ts.login(
            client_login_name=self.config["ts_user"],
            client_login_password=self.config["ts_password"],
        )
        self.ts.use(sid=self.config["ts_virtual_server_id"])

        # Renommer le bot
        nickname = self.config.get("bot_nickname", "NexarenaBot")
        try:
            self.ts.clientupdate(client_nickname=nickname)
        except ts3.query.TS3QueryError as exc:
            # Erreur 513 = nickname deja utilise, pas critique
            if exc.resp.error["id"] != "513":
                raise
            logger.warning("Le pseudo '%s' est deja utilise, on garde le pseudo par defaut.", nickname)

        # Enregistrer pour recevoir les messages texte (textprivate)
        self.ts.servernotifyregister(event="textprivate")

        logger.info("Connecte et en ecoute (pseudo: %s).", nickname)

    def disconnect(self) -> None:
        """Ferme proprement la connexion."""
        if self.ts is not None:
            try:
                self.ts.quit()
            except Exception:
                pass
            self.ts = None

    # -- Envoi de messages ---------------------------------------------------

    def send_private_message(self, clid: int, message: str) -> None:
        """Envoie un message prive a un client."""
        try:
            self.ts.sendtextmessage(
                targetmode=1,  # 1 = client prive
                target=clid,
                msg=message,
            )
        except ts3.query.TS3QueryError as exc:
            logger.error("Impossible d'envoyer un message au client %s: %s", clid, exc)

    # -- Recuperation d'infos client -----------------------------------------

    def get_client_unique_id(self, clid: int) -> str | None:
        """Retourne l'identifiant unique (client_unique_identifier) d'un client."""
        try:
            resp = self.ts.clientinfo(clid=clid)
            return resp.parsed[0].get("client_unique_identifier")
        except ts3.query.TS3QueryError:
            return None

    def get_client_server_groups(self, cldbid: int) -> list[int]:
        """Retourne la liste des IDs de groupes serveur d'un client (via cldbid)."""
        try:
            resp = self.ts.servergroupsbyclientid(cldbid=cldbid)
            return [int(g["sgid"]) for g in resp.parsed]
        except ts3.query.TS3QueryError:
            return []

    def get_client_dbid(self, clid: int) -> int | None:
        """Retourne le database ID d'un client connecte."""
        try:
            resp = self.ts.clientinfo(clid=clid)
            return int(resp.parsed[0].get("client_database_id", 0)) or None
        except ts3.query.TS3QueryError:
            return None

    # -- Gestion des commandes -----------------------------------------------

    def handle_vote_command(self, clid: int, unique_id: str) -> None:
        """Commande !vote - Envoie le lien de vote."""
        vote_url = self.config["vote_url"]
        self.send_private_message(
            clid,
            f"[b]Nexarena Vote[/b]\n"
            f"Votez pour notre serveur ici : [url]{vote_url}[/url]\n"
            f"Apres avoir vote, utilisez [b]{self.prefix}checkvote <votre_pseudo>[/b] pour recevoir votre recompense.",
        )
        logger.info("Lien de vote envoye au client %s (uid: %s)", clid, unique_id)

    def handle_checkvote_command(self, clid: int, unique_id: str, username: str) -> None:
        """Commande !checkvote <username> - Verifie le vote et attribue le groupe."""
        # Cooldown
        if self.cooldown.is_on_cooldown(unique_id):
            remaining = self.cooldown.remaining(unique_id)
            self.send_private_message(
                clid,
                f"[color=red]Veuillez patienter {remaining}s avant de reutiliser cette commande.[/color]",
            )
            return

        self.cooldown.mark(unique_id)

        # Appel API
        self.send_private_message(clid, "Verification de votre vote en cours...")
        result = self.api.check_vote(username)

        if result is None:
            self.send_private_message(
                clid,
                "[color=red]Erreur lors de la verification. Veuillez reessayer plus tard.[/color]",
            )
            return

        if not result.get("voted", False):
            vote_url = self.config["vote_url"]
            self.send_private_message(
                clid,
                f"[color=red]Aucun vote recent trouve pour [b]{username}[/b].[/color]\n"
                f"Votez ici : [url]{vote_url}[/url]",
            )
            return

        # Vote confirme - attribuer le groupe
        voted_at = result.get("voted_at", "")
        reward_group_id = self.config["reward_group_id"]

        if reward_group_id and int(reward_group_id) > 0:
            cldbid = self.get_client_dbid(clid)
            if cldbid:
                current_groups = self.get_client_server_groups(cldbid)
                if int(reward_group_id) in current_groups:
                    self.send_private_message(
                        clid,
                        f"[color=green]Vote confirme pour [b]{username}[/b] ![/color] (le {voted_at})\n"
                        f"Vous possedez deja le groupe recompense.",
                    )
                else:
                    try:
                        self.ts.servergroupaddclient(
                            sgid=reward_group_id,
                            cldbid=cldbid,
                        )
                        self.send_private_message(
                            clid,
                            f"[color=green]Vote confirme pour [b]{username}[/b] ![/color] (le {voted_at})\n"
                            f"Le groupe recompense vous a ete attribue. Merci d'avoir vote !",
                        )
                        logger.info(
                            "Groupe %s attribue au client %s (dbid: %s, pseudo: %s)",
                            reward_group_id, clid, cldbid, username,
                        )
                    except ts3.query.TS3QueryError as exc:
                        logger.error("Erreur attribution groupe %s au client dbid %s: %s", reward_group_id, cldbid, exc)
                        self.send_private_message(
                            clid,
                            f"[color=green]Vote confirme pour [b]{username}[/b] ![/color]\n"
                            f"[color=red]Erreur lors de l'attribution du groupe. Contactez un administrateur.[/color]",
                        )
            else:
                self.send_private_message(
                    clid,
                    f"[color=green]Vote confirme pour [b]{username}[/b] ![/color]\n"
                    f"[color=red]Impossible de determiner votre identifiant. Contactez un administrateur.[/color]",
                )
        else:
            # Pas de groupe configure, juste confirmer
            self.send_private_message(
                clid,
                f"[color=green]Vote confirme pour [b]{username}[/b] ![/color] (le {voted_at})\n"
                f"Merci d'avoir vote !",
            )

    def process_event(self, event: ts3.response.TS3Event) -> None:
        """Traite un evenement ServerQuery."""
        for data in event.parsed:
            # On ne traite que les messages prives (targetmode=1)
            target_mode = data.get("targetmode", "")
            if str(target_mode) != "1":
                continue

            invoker_id = int(data.get("invokerid", 0))
            invoker_name = data.get("invokername", "")
            invoker_uid = data.get("invokeruid", "")
            message = data.get("msg", "").strip()

            # Ignorer nos propres messages
            if not message or not invoker_id:
                continue

            logger.debug("Message de %s (uid: %s): %s", invoker_name, invoker_uid, message)

            # Parser la commande
            lower_msg = message.lower()

            if lower_msg == f"{self.prefix}vote":
                self.handle_vote_command(invoker_id, invoker_uid)

            elif lower_msg.startswith(f"{self.prefix}checkvote"):
                parts = message.split(maxsplit=1)
                if len(parts) < 2 or not parts[1].strip():
                    self.send_private_message(
                        invoker_id,
                        f"[color=red]Utilisation : {self.prefix}checkvote <votre_pseudo>[/color]",
                    )
                else:
                    username = parts[1].strip()
                    self.handle_checkvote_command(invoker_id, invoker_uid, username)

    # -- Boucle principale ---------------------------------------------------

    def run(self) -> None:
        """Boucle principale avec reconnexion automatique."""
        logger.info("Demarrage du bot Nexarena Vote TeamSpeak...")

        while self._running:
            try:
                self.connect()
                self._listen()
            except ts3.query.TS3TransportError:
                logger.warning("Connexion perdue avec le serveur TeamSpeak.")
            except KeyboardInterrupt:
                logger.info("Arret demande par l'utilisateur.")
                self._running = False
            except Exception as exc:
                logger.error("Erreur inattendue: %s", exc, exc_info=True)
            finally:
                self.disconnect()

            if self._running:
                logger.info("Reconnexion dans %ss...", self.reconnect_delay)
                try:
                    time.sleep(self.reconnect_delay)
                except KeyboardInterrupt:
                    logger.info("Arret demande par l'utilisateur.")
                    self._running = False

        logger.info("Bot arrete.")

    def _listen(self) -> None:
        """Ecoute les evenements en continu."""
        while self._running:
            # ts3 library: wait_for_event bloque jusqu'a recevoir un evenement
            # timeout permet de verifier periodiquement si on doit s'arreter
            self.ts.send_keepalive()
            try:
                event = self.ts.wait_for_event(timeout=30)
                self.process_event(event)
            except ts3.query.TS3TimeoutError:
                # Pas d'evenement pendant le timeout, on continue (keepalive)
                continue


# ---------------------------------------------------------------------------
# Point d'entree
# ---------------------------------------------------------------------------
def main():
    config = load_config()
    bot = NexarenaTeamSpeakBot(config)
    bot.run()


if __name__ == "__main__":
    main()
