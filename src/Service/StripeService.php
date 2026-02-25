<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

class StripeService
{
    private const API_BASE = 'https://api.stripe.com/v1';

    public function __construct(
        private SettingsService $settings,
        private LoggerInterface $logger,
        private string $projectDir,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->settings->getBool('stripe_enabled', false);
    }

    public function getPublishableKey(): string
    {
        return $this->settings->get('stripe_publishable_key', '') ?? '';
    }

    private function getSecretKey(): string
    {
        return $this->settings->get('stripe_secret_key', '') ?? '';
    }

    private function getWebhookSecret(): string
    {
        return $this->settings->get('stripe_webhook_secret', '') ?? '';
    }

    private function getCaInfo(): string
    {
        $projectCa = $this->projectDir . '/config/cacert.pem';
        if (is_file($projectCa)) {
            return $projectCa;
        }

        $iniCa = ini_get('curl.cainfo');
        if ($iniCa && is_file($iniCa)) {
            return $iniCa;
        }

        return '';
    }

    private function curlRequest(string $method, string $path, array $fields = []): ?array
    {
        $secretKey = $this->getSecretKey();
        if (!$secretKey) {
            $this->logger->error('Stripe: clef secrete manquante dans les settings.');
            return null;
        }

        $url = self::API_BASE . $path;

        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERPWD        => $secretKey . ':',
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ];

        $caInfo = $this->getCaInfo();
        if ($caInfo) {
            $opts[CURLOPT_CAINFO] = $caInfo;
        }

        if ($method === 'POST' && $fields) {
            $opts[CURLOPT_POSTFIELDS] = http_build_query($fields);
        }

        curl_setopt_array($ch, $opts);

        $response = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        if ($curlError) {
            $this->logger->error('Stripe curl error [{errno}]: {error}', [
                'errno' => $curlErrno,
                'error' => $curlError,
                'url'   => $url,
            ]);
            return null;
        }

        if (!is_string($response) || $response === '') {
            $this->logger->error('Stripe: reponse vide ou invalide.', [
                'httpCode' => $httpCode,
                'path'     => $path,
            ]);
            return null;
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            $this->logger->error('Stripe: JSON invalide.', [
                'httpCode' => $httpCode,
                'path'     => $path,
                'response' => mb_substr($response, 0, 200),
            ]);
            return null;
        }

        if ($httpCode >= 400) {
            $this->logger->error('Stripe HTTP error.', [
                'httpCode' => $httpCode,
                'path'     => $path,
                'error'    => $data['error']['message'] ?? mb_substr($response, 0, 300),
                'code'     => $data['error']['code'] ?? '',
            ]);
            return null;
        }

        return $data;
    }

    /**
     * Create a Stripe Checkout Session (hosted payment page).
     * Amount in cents (e.g. 1000 = €10.00).
     *
     * @return array{id: string, url: string, payment_status: string}|null
     */
    public function createCheckoutSession(
        int    $amountCents,
        string $currency,
        string $productName,
        int    $planId,
        int    $userId,
        string $successUrl,
        string $cancelUrl,
    ): ?array {
        $fields = [
            'mode'                                              => 'payment',
            'line_items[0][quantity]'                          => 1,
            'line_items[0][price_data][currency]'              => strtolower($currency),
            'line_items[0][price_data][unit_amount]'           => $amountCents,
            'line_items[0][price_data][product_data][name]'    => mb_substr($productName, 0, 250),
            'success_url'                                       => $successUrl,
            'cancel_url'                                        => $cancelUrl,
            'metadata[plan_id]'                                => $planId,
            'metadata[user_id]'                                => $userId,
            'payment_method_types[0]'                          => 'card',
        ];

        $data = $this->curlRequest('POST', '/checkout/sessions', $fields);

        if (!$data || !isset($data['id'], $data['url'])) {
            return null;
        }

        $this->logger->info('Stripe: checkout session created.', [
            'sessionId'   => $data['id'],
            'amountCents' => $amountCents,
            'currency'    => $currency,
        ]);

        return $data;
    }

    /**
     * Retrieve a Checkout Session by ID.
     *
     * @return array{id: string, payment_status: string, status: string, metadata: array}|null
     */
    public function retrieveCheckoutSession(string $sessionId): ?array
    {
        if (!preg_match('/^cs_[a-zA-Z0-9_]+$/', $sessionId)) {
            $this->logger->warning('Stripe: session ID invalide.', ['sessionId' => $sessionId]);
            return null;
        }

        return $this->curlRequest('GET', '/checkout/sessions/' . urlencode($sessionId));
    }

    /**
     * Verify the Stripe webhook signature.
     * Header format: t=timestamp,v1=signature[,v0=...]
     */
    public function verifyWebhookSignature(string $body, string $sigHeader): bool
    {
        $webhookSecret = $this->getWebhookSecret();
        if (!$webhookSecret) {
            $this->logger->warning('Stripe webhook: aucun secret configure, verification ignoree.');
            return true;
        }

        // Parse t= and v1= from header
        $timestamp = null;
        $v1        = null;
        foreach (explode(',', $sigHeader) as $part) {
            $kv = explode('=', $part, 2);
            if (count($kv) === 2) {
                if ($kv[0] === 't') {
                    $timestamp = $kv[1];
                } elseif ($kv[0] === 'v1') {
                    $v1 = $kv[1];
                }
            }
        }

        if ($timestamp === null || $v1 === null) {
            return false;
        }

        // Optionally check timestamp freshness (within 5 minutes)
        if (abs(time() - (int) $timestamp) > 300) {
            $this->logger->warning('Stripe webhook: timestamp trop ancien.', ['timestamp' => $timestamp]);
            return false;
        }

        $signedPayload = $timestamp . '.' . $body;
        $expected      = hash_hmac('sha256', $signedPayload, $webhookSecret);

        return hash_equals($expected, (string) $v1);
    }
}
