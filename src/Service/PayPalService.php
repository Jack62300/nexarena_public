<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

class PayPalService
{
    public function __construct(
        private SettingsService $settings,
        private LoggerInterface $logger,
        private string $projectDir,
    ) {
    }

    private function getBaseUrl(): string
    {
        return $this->settings->getBool('paypal_sandbox_mode', true)
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    private function getCaInfo(): string
    {
        // Use the bundled cacert.pem in the project
        $projectCa = $this->projectDir . '/config/cacert.pem';
        if (is_file($projectCa)) {
            return $projectCa;
        }

        // Fallback to php.ini curl.cainfo
        $iniCa = ini_get('curl.cainfo');
        if ($iniCa && is_file($iniCa)) {
            return $iniCa;
        }

        return '';
    }

    /**
     * Execute a curl request and return [response, httpCode] or [null, 0] on error.
     */
    private function curlRequest(string $url, array $opts, string $context): ?string
    {
        $ch = curl_init($url);

        // Always set CA info for SSL
        $caInfo = $this->getCaInfo();
        if ($caInfo) {
            $opts[CURLOPT_CAINFO] = $caInfo;
        }

        $opts[CURLOPT_RETURNTRANSFER] = true;
        $opts[CURLOPT_SSL_VERIFYPEER] = true;
        $opts[CURLOPT_SSL_VERIFYHOST] = 2;
        $opts[CURLOPT_TIMEOUT] = 30;

        curl_setopt_array($ch, $opts);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        if ($curlError) {
            $this->logger->error("PayPal {$context} curl error [{$curlErrno}]: {$curlError}", [
                'url' => $url,
                'caInfo' => $caInfo,
            ]);
            return null;
        }

        $this->logger->debug("PayPal {$context} response.", [
            'httpCode' => $httpCode,
            'responseLength' => strlen((string) $response),
        ]);

        return $response ?: null;
    }

    private function getAccessToken(): ?string
    {
        $clientId = $this->settings->get('paypal_client_id', '');
        $clientSecret = $this->settings->get('paypal_client_secret', '');

        if (!$clientId || !$clientSecret) {
            $this->logger->error('PayPal: client_id ou client_secret manquant dans les settings.');
            return null;
        }

        $url = $this->getBaseUrl() . '/v1/oauth2/token';
        $response = $this->curlRequest($url, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_USERPWD => $clientId . ':' . $clientSecret,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ], 'getAccessToken');

        if (!$response) {
            return null;
        }

        $data = json_decode($response, true);
        if (!isset($data['access_token'])) {
            $this->logger->error('PayPal getAccessToken: no access_token in response.', [
                'response' => mb_substr($response, 0, 500),
            ]);
            return null;
        }

        return $data['access_token'];
    }

    public function createOrder(string $amount, string $currency, string $description): ?array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return null;
        }

        // PayPal requires exactly 2 decimal places
        $amount = number_format((float) $amount, 2, '.', '');

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => $amount,
                    ],
                    'description' => $description,
                ],
            ],
        ];

        $url = $this->getBaseUrl() . '/v2/checkout/orders';
        $response = $this->curlRequest($url, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ],
        ], 'createOrder');

        if (!$response) {
            return null;
        }

        $data = json_decode($response, true);
        if (!isset($data['id'])) {
            $this->logger->error('PayPal createOrder: no id in response.', [
                'response' => mb_substr($response, 0, 500),
                'amount' => $amount,
                'currency' => $currency,
            ]);
            return null;
        }

        $this->logger->info('PayPal order created.', ['orderId' => $data['id']]);

        return $data;
    }

    public function captureOrder(string $orderId): ?array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return null;
        }

        $url = $this->getBaseUrl() . '/v2/checkout/orders/' . urlencode($orderId) . '/capture';
        $response = $this->curlRequest($url, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '{}',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ],
        ], 'captureOrder');

        if (!$response) {
            return null;
        }

        $data = json_decode($response, true);

        $this->logger->info('PayPal captureOrder result.', [
            'orderId' => $orderId,
            'status' => $data['status'] ?? 'unknown',
        ]);

        return $data;
    }

    public function verifyWebhookSignature(array $headers, string $body): bool
    {
        $webhookId = $this->settings->get('paypal_webhook_id', '');
        if (!$webhookId) {
            return false;
        }

        $token = $this->getAccessToken();
        if (!$token) {
            return false;
        }

        $payload = [
            'auth_algo' => $headers['PAYPAL-AUTH-ALGO'] ?? '',
            'cert_url' => $headers['PAYPAL-CERT-URL'] ?? '',
            'transmission_id' => $headers['PAYPAL-TRANSMISSION-ID'] ?? '',
            'transmission_sig' => $headers['PAYPAL-TRANSMISSION-SIG'] ?? '',
            'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'] ?? '',
            'webhook_id' => $webhookId,
            'webhook_event' => json_decode($body, true),
        ];

        $url = $this->getBaseUrl() . '/v1/notifications/verify-webhook-signature';
        $response = $this->curlRequest($url, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ],
        ], 'verifyWebhook');

        if (!$response) {
            return false;
        }

        $data = json_decode($response, true);

        return ($data['verification_status'] ?? '') === 'SUCCESS';
    }

    public function getClientId(): string
    {
        return $this->settings->get('paypal_client_id', '');
    }

    public function isSandbox(): bool
    {
        return $this->settings->getBool('paypal_sandbox_mode', true);
    }

    public function getCurrency(): string
    {
        return $this->settings->get('payment_currency', 'EUR');
    }
}
