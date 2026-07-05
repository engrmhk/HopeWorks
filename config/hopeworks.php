<?php

return [
    'control_plane_url' => env('CONTROL_PLANE_URL', 'http://localhost:8000'),
    'control_plane_api_key' => env('CONTROL_PLANE_API_KEY'),
    'jwt_secret' => env('LICENSE_JWT_SECRET', env('APP_KEY')),
];
