<?php

namespace App\Exceptions;

use RuntimeException;

class LicenseJwtSecretTooShortException extends RuntimeException
{
    public function __construct(int $actualLength = 0)
    {
        parent::__construct(
            'Control Plane JWT secret is missing or shorter than 32 characters'
            .($actualLength > 0 ? " (currently {$actualLength})." : '.')
            .' Generate or paste it in Control Plane admin (Settings → License connection, or Church connection), then paste the same value on the church System page.'
            .' The Instance API key is not the JWT secret.'
        );
    }
}
