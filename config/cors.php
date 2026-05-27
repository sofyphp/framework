<?php

return [
    'allowed_origins'      => ['*'],
    'allowed_methods'      => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_headers'      => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept'],
    'exposed_headers'      => [],
    'max_age'              => 86400,
    'supports_credentials' => false,
];
