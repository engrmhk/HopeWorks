<?php

namespace App\Exceptions;

use RuntimeException;

class LicenseJwtSecretTooShortException extends RuntimeException
{
    public function __construct(int $actualLength = 0)
    {
        parent::__construct(
            'Control Plane LICENSE_JWT_SECRET is missing or shorter than 32 characters'
            .($actualLength > 0 ? " (currently {$actualLength})." : '.')
            .' Set the same 32+ character secret on Control Plane .env and on the church System page (License JWT secret), then retry Sync Now.'
            .' The Instance API key is not the JWT secret.'
        );
    }
}
