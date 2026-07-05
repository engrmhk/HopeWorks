<?php

return [
    'jwt_secret' => env('LICENSE_JWT_SECRET', 'control-plane-dev-secret-change-in-production'),
    'jwt_ttl_hours' => (int) env('LICENSE_JWT_TTL_HOURS', 48),
];
