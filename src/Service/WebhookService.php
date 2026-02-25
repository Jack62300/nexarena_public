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
