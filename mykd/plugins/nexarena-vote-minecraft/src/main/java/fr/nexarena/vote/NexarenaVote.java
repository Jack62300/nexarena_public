package fr.nexarena.vote;

import fr.nexarena.vote.commands.CheckVoteCommand;
import fr.nexarena.vote.commands.VoteCommand;
import org.bukkit.ChatColor;
import org.bukkit.plugin.java.JavaPlugin;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.Map;
import java.util.Set;
import java.util.UUID;
import java.util.concurrent.ConcurrentHashMap;
import java.util.logging.Level;

public final class NexarenaVote extends JavaPlugin {

    private final Set<String> claimedPlayers = ConcurrentHashMap.newKeySet();
    private final Map<UUID, Long> checkCooldowns = new ConcurrentHashMap<>();
    private long claimCooldownMs;
    private int checkCooldownSeconds;

    @Override
    public void onEnable() {
        saveDefaultConfig();
        loadSettings();
        scheduleCacheReset();

        getCommand("vote").setExecutor(new VoteCommand(this));
        getCommand("checkvote").setExecutor(new CheckVoteCommand(this));

        getLogger().info("NexarenaVote enabled successfully.");

        if ("CHANGE_ME".equals(getConfig().getString("server-token", ""))) {
            getLogger().warning("===========================================");
            getLogger().warning("  server-token is not configured!");
            getLogger().warning("  Edit plugins/NexarenaVote/config.yml");
            getLogger().warning("  and set your Nexarena server token.");
            getLogger().warning("===========================================");
        }
    }

    @Override
    public void onDisable() {
        claimedPlayers.clear();
        checkCooldowns.clear();
        getLogger().info("NexarenaVote disabled.");
    }

    /**
     * Load settings from config.
     */
    private void loadSettings() {
        claimCooldownMs = getConfig().getLong("claim-cooldown-minutes", 120) * 60L * 1000L;
        checkCooldownSeconds = getConfig().getInt("check-cooldown-seconds", 30);
    }

    /**
     * Schedule periodic cache reset for claimed players.
     */
    private void scheduleCacheReset() {
        long intervalTicks = (claimCooldownMs / 1000L) * 20L; // Convert ms to ticks
        getServer().getScheduler().runTaskTimer(this, () -> {
            claimedPlayers.clear();
            getLogger().info("Claimed players cache has been reset.");
        }, intervalTicks, intervalTicks);
    }

    /**
     * Reload the plugin configuration.
     */
    public void reloadPluginConfig() {
        reloadConfig();
        loadSettings();
        claimedPlayers.clear();
        checkCooldowns.clear();
        getLogger().info("Configuration reloaded.");
    }

    /**
     * Get the configured vote URL with player name substituted.
     */
    public String getVoteUrl(String playerName) {
        String url = getConfig().getString("vote-url", "https://nexarena.fr");
        return url.replace("{player}", playerName);
    }

    /**
     * Get a message from config, with color codes translated.
     */
    public String getMessage(String key) {
        String raw = getConfig().getString("messages." + key, "&cMessage not found: " + key);
        return ChatColor.translateAlternateColorCodes('&', raw);
    }

    /**
     * Check whether the player has already claimed their reward.
     */
    public boolean hasClaimed(String playerName) {
        return claimedPlayers.contains(playerName.toLowerCase());
    }

    /**
     * Mark a player as having claimed their reward.
     */
    public void markClaimed(String playerName) {
        claimedPlayers.add(playerName.toLowerCase());
    }

    /**
     * Check if a player is on check cooldown (to prevent API spam).
     * Returns true if they should wait.
     */
    public boolean isOnCheckCooldown(UUID playerId) {
        Long lastCheck = checkCooldowns.get(playerId);
        if (lastCheck == null) {
            return false;
        }
        return (System.currentTimeMillis() - lastCheck) < (checkCooldownSeconds * 1000L);
    }

    /**
     * Set the last check timestamp for a player.
     */
    public void setCheckCooldown(UUID playerId) {
        checkCooldowns.put(playerId, System.currentTimeMillis());
    }

    /**
     * Call the Nexarena API to check if a username has voted.
     * This method performs a blocking HTTP request and MUST be called from an async task.
     *
     * @param username The Minecraft username to check.
     * @return true if the player has voted, false otherwise. Returns false on error.
     */
    public boolean checkVoteApi(String username) {
        String baseUrl = getConfig().getString("api-url", "https://nexarena.fr").replaceAll("/+$", "");
        String token = getConfig().getString("server-token", "");
        String endpoint = baseUrl + "/api/v1/servers/" + token + "/vote/" + username;

        HttpURLConnection connection = null;
        try {
            URL url = new URL(endpoint);
            connection = (HttpURLConnection) url.openConnection();
            connection.setRequestMethod("GET");
            connection.setRequestProperty("Accept", "application/json");
            connection.setRequestProperty("User-Agent", "NexarenaVote-Bukkit/1.0.0");
            connection.setConnectTimeout(5000);
            connection.setReadTimeout(5000);

            int responseCode = connection.getResponseCode();
            if (responseCode != 200) {
                getLogger().warning("API returned HTTP " + responseCode + " for player " + username);
                return false;
            }

            BufferedReader reader = new BufferedReader(
                    new InputStreamReader(connection.getInputStream(), StandardCharsets.UTF_8)
            );
            StringBuilder response = new StringBuilder();
            String line;
            while ((line = reader.readLine()) != null) {
                response.append(line);
            }
            reader.close();

            // Simple JSON parsing without external library
            // Response format: {"voted":true,"username":"...","voted_at":"..."}
            String json = response.toString();
            return json.contains("\"voted\":true") || json.contains("\"voted\": true");

        } catch (Exception e) {
            getLogger().log(Level.WARNING, "Failed to check vote API for player " + username, e);
            return false;
        } finally {
            if (connection != null) {
                connection.disconnect();
            }
        }
    }
}
