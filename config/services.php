<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'tencent_sms' => [
        'enabled'      => env('TENCENT_SMS_ENABLED', false),
        'secret_id'    => env('TENCENT_SMS_SECRET_ID'),
        'secret_key'   => env('TENCENT_SMS_SECRET_KEY'),
        'app_id'       => env('TENCENT_SMS_APP_ID'),
        'sign'         => env('TENCENT_SMS_SIGN'),
        'template_id'  => env('TENCENT_SMS_TEMPLATE_ID'),
        'region'       => env('TENCENT_SMS_REGION', 'ap-guangzhou'),
        'reminder_tpl' => env('TENCENT_SMS_REMINDER_TEMPLATE_ID'),
    ],

    'wecom' => [
        'enabled'        => env('WECOM_ENABLED', false),
        'corp_id'        => env('WECOM_CORP_ID'),
        'app_secret'     => env('WECOM_APP_SECRET'),
        'agent_id'       => env('WECOM_AGENT_ID'),
        'sender_user_id' => env('WECOM_SENDER_USER_ID'),
        'cache_ttl'      => env('WECOM_TOKEN_CACHE_SECONDS', 5400),
    ],

];
