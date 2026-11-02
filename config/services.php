<?php

return [

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
     * SMS gateway for parent/student notifications (attendance, fee
     * reminders). Placeholder until a specific national SMS provider is
     * selected in a later phase.
     */
    'sms_gateway' => [
        'url' => env('SMS_GATEWAY_URL'),
        'api_key' => env('SMS_GATEWAY_API_KEY'),
        'sender_id' => env('SMS_GATEWAY_SENDER_ID', 'SCHOOL'),
    ],

];
