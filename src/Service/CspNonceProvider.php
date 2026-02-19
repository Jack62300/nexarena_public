<?php

namespace App\Service;

/**
 * Generates a single CSP nonce per HTTP request (lazy, shared singleton).
 * Injected into both SecurityHeadersListener (builds the CSP header)
 * and CspNonceExtension (exposes csp_nonce() in Twig templates).
 */
class CspNonceProvider
{
    private ?string $nonce = null;

    public function getNonce(): string
    {
        if ($this->nonce === null) {
            $this->nonce = base64_encode(random_bytes(16));
        }

        return $this->nonce;
    }
}
