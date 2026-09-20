<?php

return [
    // Empty LICENSE_JWT_SECRET= in .env stays empty (not a fake default).
    // Admin Settings → License connection is the source of truth; this is a fallback.
    // firebase/php-jwt HS256 requires at least 32 characters.
    'jwt_secret' => (string) env('LICENSE_JWT_SECRET', ''),
    'jwt_ttl_hours' => (int) env('LICENSE_JWT_TTL_HOURS', 48),
];
