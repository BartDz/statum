<?php

/**
 * @var array<array{
 *     name: string,
 *     url: string,
 *     expected_status: int // optional, default is 200
 * }> $services
 */

return [
    [
        'name' => 'Portfolio',
        'url' => 'https://bart.dev'
    ],
    [
        'name' => 'API',
        'url' => 'https://api.bart.dev/health'
    ],
    [
        'name' => 'n8n',
        'url' => 'https://n8n.bart.dev'
    ],
];
