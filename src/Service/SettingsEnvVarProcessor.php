<?php

namespace App\Service;

use App\Entity\Setting;
use App\Repository\SettingRepository;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

class SettingsEnvVarProcessor implements EnvVarProcessorInterface
{
    private const ENV_TO_SETTING = [
        'GOOGLE_CLIENT_ID' => 'google_client_id',
        'GOOGLE_CLIENT_SECRET' => 'google_client_secret',
        'DISCORD_CLIENT_ID' => 'discord_client_id',
        'DISCORD_CLIENT_SECRET' => 'discord_client_secret',
        'TWITCH_CLIENT_ID' => 'twitch_client_id',
        'TWITCH_CLIENT_SECRET' => 'twitch_client_secret',
        'STEAM_API_KEY' => 'steam_api_key',
        'IPGEOLOCATION_API_KEY' => 'ipgeolocation_api_key',
    ];

    public function __construct(
        private SettingRepository $settingRepo,
        private EncryptionService $encryptionService,
    ) {
    }

    public function getEnv(string $prefix, string $name, \Closure $getEnv): mixed
    {
        $settingKey = self::ENV_TO_SETTING[$name] ?? null;

        if ($settingKey) {
            try {
                $setting = $this->settingRepo->findByKey($settingKey);
                if ($setting && $setting->getValue() !== '') {
                    $value = $setting->getValue();
                    // Decrypt if encrypted
                    if ($setting->getType() === Setting::TYPE_SECRET && $this->encryptionService->isEncrypted($value)) {
                        return $this->encryptionService->decrypt($value);
                    }
                    return $value;
                }
            } catch (\Throwable) {
                // DB not available yet (first boot, migrations not run) — fall through to env
            }
        }

        try {
            return $getEnv($name);
        } catch (\Throwable) {
            return '';
        }
    }

    public static function getProvidedTypes(): array
    {
        return ['setting' => 'string'];
    }
}
