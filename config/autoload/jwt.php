<?php

declare(strict_types=1);

use function Hyperf\Support\env;

return [
    // JWT Secret Key (should be set in .env)
    'secret' => env('JWT_SECRET', 'hyperf-monitoring-secret-key'),
    
    // Token expiration time in seconds (default: 1 minute = 60 seconds)
    'expire' => (int) env('JWT_EXPIRE', 60),
    
    // Refresh token expiration time in seconds (default: 7 days = 604800 seconds)
    'refresh_expire' => (int) env('JWT_REFRESH_EXPIRE', 604800),
    
    // JWT Issuer
    'issuer' => env('JWT_ISSUER', 'hyperf-monitoring'),
    
    // JWT Audience
    'audience' => env('JWT_AUDIENCE', 'hyperf-monitoring-users'),
    
    // Algorithm (HS256, HS384, HS512, RS256, RS384, RS512, ES256, ES384, ES512)
    'algorithm' => env('JWT_ALGORITHM', 'HS256'),
];
