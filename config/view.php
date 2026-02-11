<?php

return [
    'paths' => [
        resource_path('views'),
    ],

    // Use a fixed writable path; avoid realpath() returning false when directory is missing.
    'compiled' => env(
        'VIEW_COMPILED_PATH',
        storage_path('framework/views')
    ),
];

