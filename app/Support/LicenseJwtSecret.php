<?php

namespace App\Support;

use App\Exceptions\LicenseJwtSecretTooShortException;
use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LicenseJwtSecret
{
    public const MIN_BYTES = 32;

    public const CACHE_KEY = 'hopeworks.license_jwt_secret';

    public static function configured(): string
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): string {
            $fromDb = self::fromDatabase();

            if (strlen($fromDb) >= self::MIN_BYTES) {
                return $fromDb;
            }

            return trim((string) config('license.jwt_secret', ''));
        });
    }

    public static function isReady(): bool
    {
        return strlen(self::configured()) >= self::MIN_BYTES;
    }

    public static function signingKey(): string
    {
        $secret = self::configured();

        if (strlen($secret) < self::MIN_BYTES) {
            throw new LicenseJwtSecretTooShortException(strlen($secret));
        }

        return $secret;
    }

    public static function generate(): string
    {
        $secret = Str::random(48);

        self::save($secret);

        return $secret;
    }

    public static function save(string $secret): void
    {
        $secret = trim($secret);

        if (strlen($secret) < self::MIN_BYTES) {
            throw new LicenseJwtSecretTooShortException(strlen($secret));
        }

        $row = PlatformSetting::current();
        $row->license_jwt_secret = $secret;
        $row->save();

        Cache::forget(self::CACHE_KEY);
    }

    public static function operatorMessage(): string
    {
        if (self::isReady()) {
            return 'Shared JWT secret is saved ('.strlen(self::configured()).' characters). Copy it into every church Settings → System → License JWT secret.';
        }

        return 'No JWT secret yet. Click Generate JWT secret here (or paste the one from the church System page) and save. Do not use the Instance API key.';
    }

    protected static function fromDatabase(): string
    {
        try {
            if (! Schema::hasTable('platform_settings')) {
                return '';
            }

            return trim((string) (PlatformSetting::query()->first()?->license_jwt_secret ?? ''));
        } catch (\Throwable) {
            return '';
        }
    }
}
