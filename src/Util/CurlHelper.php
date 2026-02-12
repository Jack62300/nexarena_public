<?php

namespace App\Util;

class CurlHelper
{
    public static function applySsl(\CurlHandle $ch): void
    {
        curl_setopt_array($ch, [
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
    }

    public static function createSecure(string $url, int $timeout = 10): \CurlHandle
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Nexarena/1.0',
        ]);

        return $ch;
    }
}
