<?php

namespace App\Service;

use App\Entity\Setting;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;

class SettingsService
{
    /** @var array<string, Setting>|null */
    private ?array $cache = null;

    public function __construct(
        private SettingRepository $repository,
        private EntityManagerInterface $em,
        private EncryptionService $encryptionService,
    ) {
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $this->loadCache();

        $setting = $this->cache[$key] ?? null;
        if (!$setting) {
            return $default;
        }

        $value = $setting->getValue();
        if ($value === null) {
            return $default;
        }

        // Auto-decrypt secrets
        if ($setting->getType() === Setting::TYPE_SECRET && $this->encryptionService->isEncrypted($value)) {
            return $this->encryptionService->decrypt($value);
        }

        return $value;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);
        if ($value === null) {
            return $default;
        }

        return $value === '1' || $value === 'true';
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key);
        if ($value === null) {
            return $default;
        }

        return (int) $value;
    }

    public function set(string $key, ?string $value): void
    {
        $setting = $this->repository->findByKey($key);

        if (!$setting) {
            // Upsert: create the row if it doesn't exist yet (e.g. init-settings not run)
            $setting = new Setting();
            $setting->setKey($key);
            $this->em->persist($setting);
        }

        // Auto-encrypt secrets
        if ($setting->getType() === Setting::TYPE_SECRET && $value !== null && $value !== '') {
            $value = $this->encryptionService->encrypt($value);
        }

        $setting->setValue($value);
        $this->em->flush();
        $this->cache = null; // Invalidate in-memory cache
    }

    public function getSetting(string $key): ?Setting
    {
        $this->loadCache();

        return $this->cache[$key] ?? null;
    }

    /**
     * @return array<string, Setting>
     */
    public function getAll(): array
    {
        $this->loadCache();

        return $this->cache ?? [];
    }

    private function loadCache(): void
    {
        if ($this->cache !== null) {
            return;
        }

        $this->cache = [];
        $settings = $this->repository->findAll();
        foreach ($settings as $setting) {
            $key = $setting->getKey();
            if ($key !== null) {
                $this->cache[$key] = $setting;
            }
        }
    }
}
