package fr.nexarena.vote.commands;

import fr.nexarena.vote.NexarenaVote;
import org.bukkit.Bukkit;
import org.bukkit.command.Command;
import org.bukkit.command.CommandExecutor;
import org.bukkit.command.CommandSender;
import org.bukkit.entity.Player;

import java.util.List;

public class CheckVoteCommand implements CommandExecutor {

    private final NexarenaVote plugin;

    public CheckVoteCommand(NexarenaVote plugin) {
        this.plugin = plugin;
    }

    @Override
    public boolean onCommand(CommandSender sender, Command command, String label, String[] args) {
        // Handle config reload subcommand for admins
        if (args.length == 1 && args[0].equalsIgnoreCase("reload")) {
            if (!sender.hasPermission("nexarena.admin")) {
                sender.sendMessage(plugin.getMessage("error"));
                return true;
            }
            plugin.reloadPluginConfig();
            sender.sendMessage(org.bukkit.ChatColor.translateAlternateColorCodes('&',
                    "&a&l[Nexarena] &fConfiguration rechargee avec succes."));
            return true;
        }

        if (!(sender instanceof Player)) {
            sender.sendMessage("This command can only be used by players.");
            return true;
        }

        Player player = (Player) sender;
        String playerName = player.getName();

        // Check per-command cooldown (prevent API spam)
        if (plugin.isOnCheckCooldown(player.getUniqueId())) {
            player.sendMessage(plugin.getMessage("cooldown"));
            return true;
        }

        // Check if already claimed
        if (plugin.hasClaimed(playerName)) {
            player.sendMessage(plugin.getMessage("already-claimed"));
            return true;
        }

        // Set cooldown before making the request
        plugin.setCheckCooldown(player.getUniqueId());

        // Run the API check asynchronously to avoid blocking the main thread
        Bukkit.getScheduler().runTaskAsynchronously(plugin, () -> {
            boolean voted;
            try {
                voted = plugin.checkVoteApi(playerName);
            } catch (Exception e) {
                // Switch back to main thread to send error message
                Bukkit.getScheduler().runTask(plugin, () -> {
                    if (player.isOnline()) {
                        player.sendMessage(plugin.getMessage("error"));
                    }
                });
                return;
            }

            // Switch back to the main thread for Bukkit API calls (commands, messages)
            Bukkit.getScheduler().runTask(plugin, () -> {
                if (!player.isOnline()) {
                    return;
                }

                if (!voted) {
                    player.sendMessage(plugin.getMessage("not-voted"));
                    return;
                }

                // Double-check claimed status (could have changed during async call)
                if (plugin.hasClaimed(playerName)) {
                    player.sendMessage(plugin.getMessage("already-claimed"));
                    return;
                }

                // Mark as claimed before executing rewards
                plugin.markClaimed(playerName);

                // Execute reward commands as console
                List<String> rewardCommands = plugin.getConfig().getStringList("reward-commands");
                for (String cmd : rewardCommands) {
                    String resolvedCmd = cmd.replace("{player}", playerName);
                    try {
                        Bukkit.dispatchCommand(Bukkit.getConsoleSender(), resolvedCmd);
                    } catch (Exception e) {
                        plugin.getLogger().warning("Failed to execute reward command: " + resolvedCmd);
                    }
                }

                player.sendMessage(plugin.getMessage("reward-received"));
                plugin.getLogger().info("Vote reward given to " + playerName);
            });
        });

        return true;
    }
}
