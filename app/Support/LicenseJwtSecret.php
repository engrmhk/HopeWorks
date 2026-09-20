<?php

namespace App\Support;

use App\Exceptions\LicenseJwtSecretTooShortException;

class LicenseJwtSecret
{
    public const MIN_BYTES = 32;

    public static function configured(): string
    {
        return trim((string) config('license.jwt_secret', ''));
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

    public static function operatorMessage(): string
    {
        if (self::isReady()) {
            return 'LICENSE_JWT_SECRET is set ('.strlen(self::configured()).' characters). Use this same value on every church System page.';
        }

        return 'LICENSE_JWT_SECRET is missing or shorter than 32 characters. Heartbeat will fail until you set the same long secret on Control Plane .env and the church app.';
    }
}
