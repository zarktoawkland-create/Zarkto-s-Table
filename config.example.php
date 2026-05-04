<?php
return [
    'db' => [
        'host' => 'localhost',
        'user' => 'your_database_user',
        'password' => 'your_database_password',
        'database' => 'your_database_name',
    ],
    // Keep this empty for same-domain hosting. Add exact origins only when
    // the page and PHP APIs are on different domains.
    'allowed_origins' => [
        // 'https://example.com',
    ],
    'room' => [
        'ttl_hours' => 12,
        'max_payload_bytes' => 8 * 1024 * 1024,
        'poll_limit' => 100,
    ],
    'library' => [
        'max_content_bytes' => 4 * 1024 * 1024,
        'list_limit' => 60,
    ],
    'cloud' => [
        'max_sync_bytes' => 16 * 1024 * 1024,
    ],
];
