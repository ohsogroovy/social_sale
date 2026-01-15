<?php

return [
    'app_id' => env('FACEBOOK_APP_ID'),
    'app_secret' => env('FACEBOOK_APP_SECRET'),
    'scopes' => [
        'pages_show_list',
        'pages_messaging',
        'pages_manage_metadata ',
        'pages_read_engagement',
        'pages_read_user_content',
        'pages_manage_posts',
        'pages_manage_engagement',
    ],
];
