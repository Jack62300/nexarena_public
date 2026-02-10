package fr.nexarena.vote.commands;

import fr.nexarena.vote.NexarenaVote;
import net.md_5.bungee.api.chat.ClickEvent;
import net.md_5.bungee.api.chat.HoverEvent;
import net.md_5.bungee.api.chat.TextComponent;
import net.md_5.bungee.api.chat.hover.content.Text;
import org.bukkit.command.Command;
import org.bukkit.command.CommandExecutor;
import org.bukkit.command.CommandSender;
import org.bukkit.entity.Player;

public class VoteCommand implements CommandExecutor {

    private final NexarenaVote plugin;

    public VoteCommand(NexarenaVote plugin) {
        this.plugin = plugin;
    }

    @Override
    public boolean onCommand(CommandSender sender, Command command, String label, String[] args) {
        if (!(sender instanceof Player)) {
            sender.sendMessage("This command can only be used by players.");
            return true;
        }

        Player player = (Player) sender;
        String voteUrl = plugin.getVoteUrl(player.getName());

        // Send a clickable message using the Bungee chat API (available in Spigot)
        String messageText = plugin.getMessage("vote-link").replace("{url}", voteUrl);

        try {
            // Try to send a clickable component
            TextComponent component = new TextComponent(TextComponent.fromLegacyText(messageText));
            component.setClickEvent(new ClickEvent(ClickEvent.Action.OPEN_URL, voteUrl));
            component.setHoverEvent(new HoverEvent(HoverEvent.Action.SHOW_TEXT,
                    new Text("Cliquez pour voter !")));
            player.spigot().sendMessage(component);
        } catch (NoClassDefFoundError | NoSuchMethodError e) {
            // Fallback for servers without Bungee chat API
            player.sendMessage(messageText);
        }

        return true;
    }
}
