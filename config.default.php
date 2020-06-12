<?php

return [
    'cache_path' => '/opt/s3_origin_shield/cache',
    'pull_zones' => [
        'kitties.ca-central-1.amazonaws.com' => [
            'credentials' => [
                'key'    => 'my-api-key',
                'secret' => 'my-api-secret',
            ],
            'region' => 'ca-central-1',
            'version' => 'latest',
            'bucket' => 'kitties',
        ],
        'doggies.eu-central-1.amazonaws.com' => [
            'credentials' => [
                'key'    => 'my-api-key',
                'secret' => 'my-api-secret',
            ],
            'region' => 'eu-central-1',
            'version' => 'latest',
            'bucket' => 'doggies',
        ],
    ],
];
