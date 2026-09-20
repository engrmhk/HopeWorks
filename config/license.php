<?php

return [
    // Empty LICENSE_JWT_SECRET= in .env must stay empty (not a fake default).
    // firebase/php-jwt HS256 requires at least 32 characters.
    'jwt_secret' => (string) env('LICENSE_JWT_SECRET', ''),
    'jwt_ttl_hours' => (int) env('LICENSE_JWT_TTL_HOURS', 48),
];
