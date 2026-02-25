<?php

namespace App\Service;

class VirusTotalService
{
    public function __construct(
        private SettingsService $settings,
    ) {
    }

    public function scanFile(string $filePath): ?string
    {
        $apiKey = $this->settings->get('virustotal_api_key');
        if (!$apiKey) {
            return null;
        }

        $ch = curl_init();
        if ($ch === false) {
            return null;
        }
        $cfile = new \CURLFile($filePath, 'application/octet-stream', basename($filePath));

        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://www.virustotal.com/api/v3/files',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['file' => $cfile],
            CURLOPT_HTTPHEADER => [
                'x-apikey: ' . $apiKey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !is_string($response) || $response === '') {
            return null;
        }

        $data = json_decode($response, true);

        return $data['data']['id'] ?? null;
    }

    public function getAnalysisResult(string $analysisId): string
    {
        $apiKey = $this->settings->get('virustotal_api_key');
        if (!$apiKey) {
            return 'error';
        }

        $ch = curl_init();
        if ($ch === false) {
            return 'error';
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://www.virustotal.com/api/v3/analyses/' . urlencode($analysisId),
            CURLOPT_HTTPHEADER => [
                'x-apikey: ' . $apiKey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !is_string($response) || $response === '') {
            return 'error';
        }

        $data = json_decode($response, true);
        $status = $data['data']['attributes']['status'] ?? null;

        if ($status !== 'completed') {
            return 'pending';
        }

        $stats = $data['data']['attributes']['stats'] ?? [];
        $malicious = ($stats['malicious'] ?? 0) + ($stats['suspicious'] ?? 0);

        return $malicious > 0 ? 'flagged' : 'clean';
    }
}
