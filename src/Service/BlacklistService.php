<?php

namespace App\Service;

use App\Entity\BlacklistEntry;
use App\Repository\BlacklistEntryRepository;

class BlacklistService
{
    public function __construct(
        private readonly BlacklistEntryRepository $repo,
    ) {}

    /**
     * Returns true if the username contains any blacklisted keyword (case-insensitive).
     */
    public function isUsernameBlacklisted(string $username): bool
    {
        $entries = $this->repo->findByType(BlacklistEntry::TYPE_USERNAME);
        $lowerUsername = strtolower($username);

        foreach ($entries as $entry) {
            if (str_contains($lowerUsername, strtolower($entry->getValue()))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns true if the email domain is blacklisted (exact match, case-insensitive).
     */
    public function isEmailDomainBlacklisted(string $email): bool
    {
        $atPos = strrpos($email, '@');
        if ($atPos === false) {
            return false;
        }

        $domain = strtolower(substr($email, $atPos + 1));

        return $this->repo->isValueBlacklisted(BlacklistEntry::TYPE_EMAIL_DOMAIN, $domain);
    }

    public function getUsernameEntries(): array
    {
        return $this->repo->findByType(BlacklistEntry::TYPE_USERNAME);
    }

    public function getEmailDomainEntries(): array
    {
        return $this->repo->findByType(BlacklistEntry::TYPE_EMAIL_DOMAIN);
    }
}
