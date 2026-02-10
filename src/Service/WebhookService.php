<?php

namespace App\Service;

use App\Entity\Server;
use App\Entity\Vote;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WebhookService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private SettingsService $settings,
    ) {
    }

    public function sendVoteWebhook(Server $server, Vote $vote): void
    {
        $url = $server->getWebhookUrl();
        if (!$url) {
            return;
        }

        $payload = [
            'event' => 'vote',
            'server_id' => $server->getId(),
            'server_name' => $server->getName(),
            'username' => $vote->getVoterUsername(),
            'voted_at' => $vote->getVotedAt()->format('c'),
        ];

        if ($this->settings->getBool('webhook_send_ip', true)) {
            $payload['ip'] = $vote->getVoterIp();
        }

        if ($this->settings->getBool('webhook_send_email', false) && $vote->getUser()) {
            $payload['email'] = $vote->getUser()->getEmail();
        }

        $headers = ['Content-Type' => 'application/json'];

        $secret = $this->settings->get('webhook_secret', '');
        if ($secret) {
            $signature = hash_hmac('sha256', json_encode($payload), $secret);
            $headers['X-Webhook-Signature'] = $signature;
        }

        try {
            $this->httpClient->request('POST', $url, [
                'json' => $payload,
                'headers' => $headers,
                'timeout' => 5,
            ]);
        } catch (\Throwable) {
            // Fire-and-forget: log silently
        }
    }
}
