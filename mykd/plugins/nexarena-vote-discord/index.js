const {
    Client,
    GatewayIntentBits,
    SlashCommandBuilder,
    EmbedBuilder,
    REST,
    Routes,
    PermissionFlagsBits,
    ActionRowBuilder,
    ButtonBuilder,
    ButtonStyle,
} = require('discord.js');

const config = require('./config.json');

// ─── Validation de la configuration ─────────────────────────────────────────

const REQUIRED_CONFIG = [
    'bot_token',
    'client_id',
    'guild_id',
    'nexarena_api_url',
    'nexarena_server_token',
    'vote_url',
    'reward_role_id',
    'embed_color',
];

for (const key of REQUIRED_CONFIG) {
    if (!config[key] || config[key].startsWith('YOUR_')) {
        console.error(`[Nexarena] Erreur: "${key}" n'est pas configure dans config.json`);
        process.exit(1);
    }
}

// ─── Constantes ─────────────────────────────────────────────────────────────

const EMBED_COLOR = parseInt(config.embed_color.replace('#', ''), 16);
const API_BASE = config.nexarena_api_url.replace(/\/+$/, '');
const API_ENDPOINT = `${API_BASE}/api/v1/servers/${config.nexarena_server_token}/vote/discord`;
const API_TIMEOUT = 10_000;

// ─── Slash commands ─────────────────────────────────────────────────────────

const commands = [
    new SlashCommandBuilder()
        .setName('checkvote')
        .setDescription('Verifie si vous avez vote et attribue la recompense'),

    new SlashCommandBuilder()
        .setName('vote')
        .setDescription('Affiche le lien pour voter sur Nexarena'),
];

// ─── Client Discord ─────────────────────────────────────────────────────────

const client = new Client({
    intents: [GatewayIntentBits.Guilds],
});

// ─── Enregistrement des slash commands ──────────────────────────────────────

async function registerCommands() {
    const rest = new REST({ version: '10' }).setToken(config.bot_token);

    try {
        console.log('[Nexarena] Enregistrement des slash commands...');

        await rest.put(
            Routes.applicationGuildCommands(config.client_id, config.guild_id),
            { body: commands.map((cmd) => cmd.toJSON()) }
        );

        console.log('[Nexarena] Slash commands enregistrees avec succes.');
    } catch (error) {
        console.error('[Nexarena] Erreur lors de l\'enregistrement des commands:', error);
    }
}

// ─── Appel API Nexarena ─────────────────────────────────────────────────────

async function checkVoteApi(discordId) {
    const url = `${API_ENDPOINT}/${discordId}`;

    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), API_TIMEOUT);

    try {
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'User-Agent': 'NexarenaDiscordBot/1.0',
            },
            signal: controller.signal,
        });

        if (!response.ok) {
            const text = await response.text().catch(() => '');
            throw new Error(`API HTTP ${response.status}: ${text}`);
        }

        return await response.json();
    } finally {
        clearTimeout(timeout);
    }
}

// ─── Commande /checkvote ────────────────────────────────────────────────────

async function handleCheckVote(interaction) {
    await interaction.deferReply({ ephemeral: true });

    const discordId = interaction.user.id;
    const member = interaction.member;

    // Appel API
    let data;
    try {
        data = await checkVoteApi(discordId);
    } catch (error) {
        console.error(`[Nexarena] Erreur API pour ${discordId}:`, error.message);

        const errorEmbed = new EmbedBuilder()
            .setColor(0xff4444)
            .setTitle('Erreur')
            .setDescription(
                'Impossible de verifier votre vote pour le moment. Veuillez reessayer plus tard.'
            )
            .setFooter({ text: 'Nexarena' })
            .setTimestamp();

        return interaction.editReply({ embeds: [errorEmbed] });
    }

    // Pas vote
    if (!data.voted) {
        const notVotedEmbed = new EmbedBuilder()
            .setColor(0xff9944)
            .setTitle('Vote non detecte')
            .setDescription(
                'Vous n\'avez pas encore vote aujourd\'hui.\nCliquez sur le bouton ci-dessous pour voter !'
            )
            .setFooter({ text: 'Nexarena' })
            .setTimestamp();

        const row = new ActionRowBuilder().addComponents(
            new ButtonBuilder()
                .setLabel('Voter maintenant')
                .setStyle(ButtonStyle.Link)
                .setURL(config.vote_url)
        );

        return interaction.editReply({ embeds: [notVotedEmbed], components: [row] });
    }

    // A vote - Attribution du role
    const rewardRole = interaction.guild.roles.cache.get(config.reward_role_id);

    if (!rewardRole) {
        console.error(`[Nexarena] Role ${config.reward_role_id} introuvable sur le serveur.`);

        const errorEmbed = new EmbedBuilder()
            .setColor(0xff4444)
            .setTitle('Erreur de configuration')
            .setDescription(
                'Le role de recompense est introuvable. Contactez un administrateur.'
            )
            .setFooter({ text: 'Nexarena' })
            .setTimestamp();

        return interaction.editReply({ embeds: [errorEmbed] });
    }

    // Verification des permissions du bot
    const botMember = interaction.guild.members.me;
    if (!botMember.permissions.has(PermissionFlagsBits.ManageRoles)) {
        console.error('[Nexarena] Le bot n\'a pas la permission ManageRoles.');

        const errorEmbed = new EmbedBuilder()
            .setColor(0xff4444)
            .setTitle('Permission manquante')
            .setDescription(
                'Le bot n\'a pas la permission de gerer les roles. Contactez un administrateur.'
            )
            .setFooter({ text: 'Nexarena' })
            .setTimestamp();

        return interaction.editReply({ embeds: [errorEmbed] });
    }

    // Verification de la hierarchie des roles
    if (rewardRole.position >= botMember.roles.highest.position) {
        console.error(
            `[Nexarena] Le role "${rewardRole.name}" est au-dessus du role du bot dans la hierarchie.`
        );

        const errorEmbed = new EmbedBuilder()
            .setColor(0xff4444)
            .setTitle('Erreur de hierarchie')
            .setDescription(
                'Le role de recompense est place au-dessus du bot dans la hierarchie. Contactez un administrateur.'
            )
            .setFooter({ text: 'Nexarena' })
            .setTimestamp();

        return interaction.editReply({ embeds: [errorEmbed] });
    }

    // Attribution du role
    let roleAssigned = false;
    try {
        if (!member.roles.cache.has(config.reward_role_id)) {
            await member.roles.add(rewardRole, 'Vote Nexarena verifie');
            roleAssigned = true;
        }
    } catch (error) {
        console.error(`[Nexarena] Erreur attribution role pour ${discordId}:`, error.message);

        const errorEmbed = new EmbedBuilder()
            .setColor(0xff4444)
            .setTitle('Erreur')
            .setDescription(
                'Votre vote a ete verifie, mais le role n\'a pas pu etre attribue. Contactez un administrateur.'
            )
            .setFooter({ text: 'Nexarena' })
            .setTimestamp();

        return interaction.editReply({ embeds: [errorEmbed] });
    }

    // Formatage de la date du vote
    let votedAtText = '';
    if (data.voted_at) {
        const votedDate = new Date(data.voted_at);
        if (!isNaN(votedDate.getTime())) {
            votedAtText = `\nDate du vote : <t:${Math.floor(votedDate.getTime() / 1000)}:R>`;
        }
    }

    // Embed de succes
    const successEmbed = new EmbedBuilder()
        .setColor(EMBED_COLOR)
        .setTitle('Vote verifie !')
        .setDescription(
            `Merci d'avoir vote sur **Nexarena** !${votedAtText}\n\n` +
            (roleAssigned
                ? `Le role ${rewardRole} vous a ete attribue.`
                : `Vous avez deja le role ${rewardRole}.`)
        )
        .setFooter({ text: 'Nexarena' })
        .setTimestamp();

    return interaction.editReply({ embeds: [successEmbed] });
}

// ─── Commande /vote ─────────────────────────────────────────────────────────

async function handleVote(interaction) {
    const voteEmbed = new EmbedBuilder()
        .setColor(EMBED_COLOR)
        .setTitle('Voter sur Nexarena')
        .setDescription(
            'Soutenez notre serveur en votant sur **Nexarena** !\n\n' +
            'Cliquez sur le bouton ci-dessous pour acceder a la page de vote.\n' +
            'Apres avoir vote, utilisez `/checkvote` pour recevoir votre recompense.'
        )
        .setFooter({ text: 'Nexarena' })
        .setTimestamp();

    const row = new ActionRowBuilder().addComponents(
        new ButtonBuilder()
            .setLabel('Voter maintenant')
            .setStyle(ButtonStyle.Link)
            .setURL(config.vote_url)
    );

    return interaction.reply({ embeds: [voteEmbed], components: [row] });
}

// ─── Events ─────────────────────────────────────────────────────────────────

client.once('ready', async () => {
    console.log(`[Nexarena] Bot connecte en tant que ${client.user.tag}`);
    console.log(`[Nexarena] Serveur: ${config.guild_id}`);
    console.log(`[Nexarena] API: ${API_BASE}`);

    await registerCommands();
});

client.on('interactionCreate', async (interaction) => {
    if (!interaction.isChatInputCommand()) return;

    try {
        switch (interaction.commandName) {
            case 'checkvote':
                await handleCheckVote(interaction);
                break;
            case 'vote':
                await handleVote(interaction);
                break;
        }
    } catch (error) {
        console.error(`[Nexarena] Erreur commande /${interaction.commandName}:`, error);

        const errorEmbed = new EmbedBuilder()
            .setColor(0xff4444)
            .setTitle('Erreur')
            .setDescription('Une erreur inattendue est survenue. Veuillez reessayer.')
            .setFooter({ text: 'Nexarena' })
            .setTimestamp();

        const replyMethod = interaction.deferred || interaction.replied
            ? 'editReply'
            : 'reply';

        await interaction[replyMethod]({ embeds: [errorEmbed], ephemeral: true }).catch(() => {});
    }
});

// ─── Gestion des erreurs globales ───────────────────────────────────────────

process.on('unhandledRejection', (error) => {
    console.error('[Nexarena] Unhandled rejection:', error);
});

process.on('uncaughtException', (error) => {
    console.error('[Nexarena] Uncaught exception:', error);
    process.exit(1);
});

// ─── Demarrage ──────────────────────────────────────────────────────────────

client.login(config.bot_token).catch((error) => {
    console.error('[Nexarena] Impossible de se connecter a Discord:', error.message);
    process.exit(1);
});
