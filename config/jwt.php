<?php
return [
    'key' => env('JWT_KEY', ''),
    'secret' => env('JWT_SECRET', ''),
    'ttl' => env('JWT_TTL', 3600),
];
