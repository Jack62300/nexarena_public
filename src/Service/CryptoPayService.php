<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

class CryptoPayService
{
    private const API_BASE = 'https://pay.crypto.com/api';

    public function __construct(
        private SettingsService $settings,
        private LoggerInterface $logger,
        private string $projectDir,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->settings->getBool('crypto_pay_enabled', false);
    }

    public function getPublishableKey(): string
    {
        return $this->settings->get('crypto_pay_publishable_key', '') ?? '';
    }

    private function getSecretKey(): string
    {
        return $this->settings->get('crypto_pay_secret_key', '') ?? '';
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

    private function curlRequest(string $url, array $opts, string $context): ?string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        $caInfo = $this->getCaInfo();
        if ($caInfo) {
            $opts[CURLOPT_CAINFO] = $caInfo;
        }

        $opts[CURLOPT_RETURNTRANSFER] = true;
        $opts[CURLOPT_SSL_VERIFYPEER] = true;
        $opts[CURLOPT_SSL_VERIFYHOST] = 2;
        $opts[CURLOPT_TIMEOUT] = 30;
        $opts[CURLOPT_FOLLOWLOCATION] = false;

        curl_setopt_array($ch, $opts);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        if ($curlError) {
            $this->logger->error("CryptoPay {$context} curl error [{$curlErrno}]: {$curlError}", [
                'url' => $url,
            ]);
            return null;
        }

        $this->logger->debug("CryptoPay {$context} response.", [
            'httpCode' => $httpCode,
            'responseLength' => strlen((string) $response),
        ]);

        if ($httpCode >= 400) {
            $this->logger->error("CryptoPay {$context} HTTP error.", [
                'httpCode' => $httpCode,
                'response' => mb_substr((string) $response, 0, 500),
            ]);
        }

        return is_string($response) ? $response : null;
    }

    /**
     * Create a payment on Crypto.com Pay.
     * Amount must be in the smallest currency unit (cents: 1000 = €10.00).
     *
     * @return array{id: string, status: string, payment_url: string, amount: int, currency: string}|null
     */
    public function createPayment(
        int    $amountCents,
        string $currency,
        string $description,
        string $orderId,
        int    $planId,
        int    $userId,
        string $returnUrl,
        string $cancelUrl,
    ): ?array {
        $secretKey = $this->getSecretKey();
        if (!$secretKey) {
            $this->logger->error('CryptoPay: clef secrete manquante dans les settings.');
            return null;
        }

        $fields = [
            'amount'      => $amountCents,
            'currency'    => strtoupper($currency),
            'description' => mb_substr($description, 0, 255),
            'order_id'    => $orderId,
            'return_url'  => $returnUrl,
            'cancel_url'  => $cancelUrl,
            'metadata[plan_id]'  => $planId,
            'metadata[user_id]'  => $userId,
        ];

        $response = $this->curlRequest(self::API_BASE . '/payments', [
            CURLOPT_POST        => true,
            CURLOPT_POSTFIELDS  => http_build_query($fields),
            CURLOPT_USERPWD     => $secretKey . ':',
            CURLOPT_HTTPHEADER  => ['Content-Type: application/x-www-form-urlencoded'],
        ], 'createPayment');

        if (!$response) {
            return null;
        }

        $data = json_decode($response, true);

        if (!isset($data['id'], $data['payment_url'])) {
            $this->logger->error('CryptoPay createPayment: champs manquants dans la reponse.', [
                'response' => mb_substr($response, 0, 500),
                'amountCents' => $amountCents,
                'currency'    => $currency,
            ]);
            return null;
        }

        $this->logger->info('CryptoPay payment created.', [
            'paymentId'  => $data['id'],
            'amountCents' => $amountCents,
        ]);

        return $data;
    }

    /**
     * Retrieve a payment by ID.
     *
     * @return array{id: string, status: string, amount: int, currency: string, metadata: array}|null
     */
    public function getPayment(string $paymentId): ?array
    {
        $secretKey = $this->getSecretKey();
        if (!$secretKey) {
            return null;
        }

        $url = self::API_BASE . '/payments/' . urlencode($paymentId);

        $response = $this->curlRequest($url, [
            CURLOPT_USERPWD => $secretKey . ':',
        ], 'getPayment');

        if (!$response) {
            return null;
        }

        $data = json_decode($response, true);

        if (!isset($data['id'])) {
            $this->logger->error('CryptoPay getPayment: reponse invalide.', [
                'paymentId' => $paymentId,
                'response'  => mb_substr($response, 0, 500),
            ]);
            return null;
        }

        return $data;
    }

    /**
     * Verify the HMAC-SHA256 webhook signature.
     * Crypto.com Pay sends the signature in the X-Signature header.
     */
    public function verifyWebhookSignature(string $body, string $signature): bool
    {
        $webhookSecret = $this->settings->get('crypto_pay_webhook_secret', '');
        if (!$webhookSecret) {
            // No secret configured — skip verification (not recommended in production)
            $this->logger->warning('CryptoPay webhook: aucun secret configure, verification ignoree.');
            return true;
        }

        $expected = hash_hmac('sha256', $body, $webhookSecret);

        return hash_equals($expected, strtolower((string) $signature));
    }
}
