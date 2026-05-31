<?php
// AI Configuration
return [
    'openai_api_key' => getenv('OPENAI_API_KEY') ?: '',
    'use_openai' => !empty(getenv('OPENAI_API_KEY')),
    'max_tokens' => 500,
    'temperature' => 0.7,
    'supported_languages' => ['en', 'sw'],
    'hospital_info' => [
        'name' => 'Nyeri Level 4 Hospital',
        'phone' => '+254700000000',
        'email' => 'info@nyerihospital.go.ke',
        'location' => 'Nyeri Town, Kenya'
    ]
];
?>
