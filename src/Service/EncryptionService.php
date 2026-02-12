<?php

namespace App\Service;

class EncryptionService
{
    private const PREFIX = 'enc:';
    private string $key;

    public function __construct(string $appSecret)
    {
        $this->key = sodium_crypto_generichash($appSecret, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            return '';
        }

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $this->key);

        return self::PREFIX . base64_encode($nonce . $ciphertext);
    }

    public function decrypt(string $stored): string
    {
        if (!$this->isEncrypted($stored)) {
            return $stored;
        }

        $raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            return '';
        }

        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);
        if ($plaintext === false) {
            return '';
        }

        return $plaintext;
    }

    public function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }
}
