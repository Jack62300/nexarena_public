<?php

namespace App\Service;

use App\Entity\AdminWebhook;
use App\Entity\Server;
use App\Entity\Vote;
use App\Repository\AdminWebhookRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WebhookService
{
    private array $webhookCache = [];

    public function __construct(
        private HttpClientInterface $httpClient,
        private SettingsService $settings,
        private AdminWebhookRepository $adminWebhookRepo,
        private NetworkValidationService $networkValidation,
    ) {
    }

    // ──────────────────────────────────────────────
    // Server-level vote webhook (existing)
    // ──────────────────────────────────────────────

    public function sendVoteWebhook(Server $server, Vote $vote): void
    {
        $url = $server->getWebhookUrl();
        if (!$url) {
            return;
        }

        // Re-validate and resolve hostname to mitigate DNS rebinding.
        if (!$this->networkValidation->isValidWebhookUrl($url)) {
            return;
        }
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        $resolvedIps = gethostbynamel($host);
        if (!$resolvedIps) {
            return;
        }
        $pinnedIp = $resolvedIps[0];

        $isDiscord = $this->isDiscordWebhookUrl($url);

        if ($isDiscord) {
            $payload = $this->buildDiscordVotePayload($server, $vote);
            $headers = ['Content-Type' => 'application/json'];
        } else {
            $payload = [
                'event' => 'vote',
                'server_id' => $server->getId(),
                'server_name' => $server->getName(),
                'username' => $vote->getVoterUsername(),
                'voted_at' => $vote->getVotedAt()?->format('c') ?? '',
            ];

            if ($this->settings->getBool('webhook_send_ip', true)) {
                $payload['ip'] = $vote->getVoterIp();
            }

            $voteUser = $vote->getUser();
            if ($this->settings->getBool('webhook_send_email', false) && $voteUser) {
                $payload['email'] = $voteUser->getEmail();
            }

            $headers = ['Content-Type' => 'application/json'];

            $secret = $this->settings->get('webhook_secret', '');
            if ($secret) {
                $signature = hash_hmac('sha256', (string) json_encode($payload), $secret);
                $headers['X-Webhook-Signature'] = $signature;
            }
        }

        try {
            $this->httpClient->request('POST', $url, [
                'json' => $payload,
                'headers' => $headers,
                'timeout' => 5,
                'resolve' => [$host => $pinnedIp],
            ]);
        } catch (\Throwable) {
        }
    }

    private function isDiscordWebhookUrl(string $url): bool
    {
        return (bool) preg_match('#^https://(discord\.com|discordapp\.com)/api/webhooks/#i', $url);
    }

    private function buildDiscordVotePayload(Server $server, Vote $vote): array
    {
        $username = $vote->getVoterUsername() ?: 'Anonyme';
        $provider = $vote->getVoteProvider() ? ucfirst($vote->getVoteProvider()) : 'Inconnu';
        $votedAt = $vote->getVotedAt()?->format('c') ?? (new \DateTimeImmutable())->format('c');

        $config = $server->getWebhookEmbedConfig();
        if ($config) {
            return $this->buildCustomDiscordPayload($config, $this->buildVariableMap($server, $username, $provider), $votedAt);
        }

        $fields = [
            ['name' => 'Serveur', 'value' => $server->getName(), 'inline' => true],
            ['name' => 'Votant', 'value' => $username, 'inline' => true],
            ['name' => 'Methode', 'value' => $provider, 'inline' => true],
            ['name' => 'Votes ce mois', 'value' => (string) $server->getMonthlyVotes(), 'inline' => true],
            ['name' => 'Votes total', 'value' => (string) $server->getTotalVotes(), 'inline' => true],
        ];

        return [
            'embeds' => [[
                'title' => 'Nouveau vote !',
                'color' => self::COLORS['green'],
                'fields' => $fields,
                'timestamp' => $votedAt,
                'footer' => ['text' => 'Nexarena — Vote Webhook'],
            ]],
        ];
    }

    private function buildCustomDiscordPayload(array $config, array $variables, string $timestamp): array
    {
        $embed = [
            'title' => $this->replaceVariables($config['title'] ?? 'Nouveau vote !', $variables),
            'color' => isset($config['color']) ? $this->hexToDecimal($config['color']) : self::COLORS['green'],
            'timestamp' => $timestamp,
        ];

        if (!empty($config['description'])) {
            $embed['description'] = $this->replaceVariables($config['description'], $variables);
        }

        if (!empty($config['thumbnail_url'])) {
            $embed['thumbnail'] = ['url' => $config['thumbnail_url']];
        }

        if (!empty($config['footer_text'])) {
            $embed['footer'] = ['text' => $this->replaceVariables($config['footer_text'], $variables)];
        } else {
            $embed['footer'] = ['text' => 'Nexarena — Vote Webhook'];
        }

        if (!empty($config['fields']) && is_array($config['fields'])) {
            $embed['fields'] = [];
            foreach ($config['fields'] as $field) {
                if (!empty($field['name']) && isset($field['value'])) {
                    $embed['fields'][] = [
                        'name' => $this->replaceVariables($field['name'], $variables),
                        'value' => $this->replaceVariables($field['value'], $variables),
                        'inline' => !empty($field['inline']),
                    ];
                }
            }
        }

        return ['embeds' => [$embed]];
    }

    private function buildVariableMap(Server $server, string $username, string $provider): array
    {
        return [
            '{username}' => $username,
            '{server_name}' => $server->getName(),
            '{votes_month}' => (string) $server->getMonthlyVotes(),
            '{votes_total}' => (string) $server->getTotalVotes(),
            '{provider}' => $provider,
            '{date}' => (new \DateTimeImmutable())->format('d/m/Y H:i'),
        ];
    }

    private function replaceVariables(string $text, array $variables): string
    {
        return str_replace(array_keys($variables), array_values($variables), $text);
    }

    private function hexToDecimal(string $hex): int
    {
        $hex = ltrim($hex, '#');
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return self::COLORS['green'];
        }
        return (int) hexdec($hex);
    }

    /**
     * Send a test notification to the server's own webhook URL.
     */
    public function sendTestServerWebhook(Server $server): bool
    {
        $url = $server->getWebhookUrl();
        if (!$url) {
            return false;
        }

        if (!$this->networkValidation->isValidWebhookUrl($url)) {
            return false;
        }

        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        $resolvedIps = gethostbynamel($host);
        if (!$resolvedIps) {
            return false;
        }
        $pinnedIp = $resolvedIps[0];

        if ($this->isDiscordWebhookUrl($url)) {
            $config = $server->getWebhookEmbedConfig();
            if ($config) {
                $variables = $this->buildVariableMap($server, 'TestUser', 'Test');
                $payload = $this->buildCustomDiscordPayload($config, $variables, (new \DateTimeImmutable())->format('c'));
            } else {
                $payload = [
                    'embeds' => [[
                        'title' => 'Test Webhook — ' . $server->getName(),
                        'description' => 'Ce message confirme que le webhook de votre serveur **' . $server->getName() . '** est correctement configure et fonctionnel.',
                        'color' => self::COLORS['blue'],
                        'fields' => [
                            ['name' => 'Serveur', 'value' => $server->getName(), 'inline' => true],
                            ['name' => 'Statut', 'value' => 'Fonctionnel', 'inline' => true],
                        ],
                        'timestamp' => (new \DateTimeImmutable())->format('c'),
                        'footer' => ['text' => 'Nexarena — Test Webhook'],
                    ]],
                ];
            }
        } else {
            $payload = [
                'event' => 'test',
                'server_id' => $server->getId(),
                'server_name' => $server->getName(),
                'message' => 'Webhook test successful',
                'timestamp' => (new \DateTimeImmutable())->format('c'),
            ];
        }

        try {
            $response = $this->httpClient->request('POST', $url, [
                'json' => $payload,
                'headers' => ['Content-Type' => 'application/json'],
                'timeout' => 10,
                'resolve' => [$host => $pinnedIp],
            ]);

            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (\Throwable) {
            return false;
        }
    }

    // ──────────────────────────────────────────────
    // Admin Discord webhook dispatch
    // ──────────────────────────────────────────────

    private const COLORS = [
        'blue' => 3447003,
        'green' => 3066993,
        'red' => 15158332,
        'orange' => 15105570,
        'purple' => 10181046,
    ];

    private const EVENT_COLORS = [
        'server.created' => 'blue',
        'server.approved' => 'green',
        'server.deleted' => 'red',
        'user.registered' => 'green',
        'user.oauth_created' => 'green',
        'vote.cast' => 'green',
        'payment.completed' => 'green',
        'payment.refunded' => 'red',
        'premium.feature_unlocked' => 'orange',
        'premium.boost_booked' => 'orange',
        'admin.tokens_credited' => 'purple',
        'recruitment.submitted' => 'blue',
        'recruitment.approved' => 'green',
        'recruitment.application' => 'blue',
        'comment.created' => 'blue',
        'comment.flagged' => 'red',
        'comment.flag_dismissed' => 'green',
        'comment.deleted' => 'orange',
        'recruitment.revision_requested' => 'orange',
        'recruitment.rejected' => 'red',
        'recruitment.application_accepted' => 'green',
        'recruitment.application_rejected' => 'red',
    ];

    /**
     * Dispatch a Discord embed to the admin webhook configured for this event type.
     *
     * @param array{title: string, fields?: array, description?: string} $embedData
     */
    public function dispatch(string $eventType, array $embedData): void
    {
        $webhook = $this->resolveWebhook($eventType);
        $webhookUrl = $webhook?->getWebhookUrl();
        if (!$webhook || !$webhook->isEnabled() || !$webhookUrl) {
            return;
        }

        $payload = $this->formatDiscordEmbed($eventType, $embedData);

        // Discord webhook limit is 8 MB; reject oversized payloads early.
        if (strlen((string) json_encode($payload)) > 8_000_000) {
            return;
        }

        try {
            $this->httpClient->request('POST', $webhookUrl, [
                'json' => $payload,
                'timeout' => 5,
            ]);
        } catch (\Throwable) {
        }
    }

    public function sendTestWebhook(AdminWebhook $webhook): bool
    {
        $webhookUrl = $webhook->getWebhookUrl();
        if (!$webhookUrl) {
            return false;
        }

        $payload = $this->formatDiscordEmbed($webhook->getEventType(), [
            'title' => 'Test - ' . $webhook->getLabel(),
            'description' => 'Ceci est un test du webhook **' . $webhook->getLabel() . '**.',
            'fields' => [
                ['name' => 'Type', 'value' => $webhook->getEventType(), 'inline' => true],
                ['name' => 'Statut', 'value' => 'Fonctionnel', 'inline' => true],
            ],
        ]);

        try {
            $response = $this->httpClient->request('POST', $webhookUrl, [
                'json' => $payload,
                'timeout' => 10,
            ]);

            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (\Throwable) {
            return false;
        }
    }

    private function formatDiscordEmbed(string $eventType, array $data): array
    {
        $colorKey = self::EVENT_COLORS[$eventType] ?? 'blue';
        $color = self::COLORS[$colorKey] ?? 3447003;

        $embed = [
            'title' => $data['title'] ?? $eventType,
            'color' => $color,
            'timestamp' => (new \DateTimeImmutable())->format('c'),
            'footer' => ['text' => 'Nexarena'],
        ];

        if (!empty($data['description'])) {
            $embed['description'] = $data['description'];
        }

        if (!empty($data['fields'])) {
            $embed['fields'] = $data['fields'];
        }

        return ['embeds' => [$embed]];
    }

    private function resolveWebhook(string $eventType): ?AdminWebhook
    {
        if (isset($this->webhookCache[$eventType])) {
            return $this->webhookCache[$eventType];
        }

        $webhook = $this->adminWebhookRepo->findByEventType($eventType);
        $this->webhookCache[$eventType] = $webhook;

        return $webhook;
    }
}
