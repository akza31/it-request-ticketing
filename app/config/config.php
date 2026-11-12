<?php
return [
    'app_name' => 'IT Program Request & Ticketing',
    'base_url' => 'http://localhost/it-request/public',
    'timezone' => 'Asia/Jakarta',
    'db' => [
        'path' => dirname(__DIR__, 2) . '/storage/database/it_request_ticketing.sqlite',
    ],
    'storage_path' => dirname(__DIR__, 2) . '/storage',
    'telegram' => [
        'bot_token' => '',
        'it_staff_chat_id' => '',
        'it_head_chat_id' => '',
    ],
];
