<?php

return [

      
                                                                               
                          
                                                                               
     
                                                                            
                                                                         
                                                                      
                                                                    
     
      

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

    'compras' => [
        'webhook_url' => env('COMPRAS_WEBHOOK_URL'),
        'webhook_token' => env('COMPRAS_WEBHOOK_TOKEN'),
        'timeout_seconds' => env('COMPRAS_WEBHOOK_TIMEOUT', 5),
        'admin_token' => env('COMPRAS_ADMIN_TOKEN'),
    ],

    'crm' => [
                                                               
                                                                                            
        'lookup_url' => env('CRM_LOOKUP_URL'),
        'token' => env('CRM_TOKEN'),
        'timeout_seconds' => env('CRM_TIMEOUT', 5),
    ],

];
