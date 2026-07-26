<?php

return [
    'secret' => env('JWT_SECRET', ''),
    'ttl'    => env('JWT_TTL', 3600),
];
