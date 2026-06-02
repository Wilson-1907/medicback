<?php
// AI Configuration (Groq)
return [
    'groq_api_key' => getenv('GROQ_API_KEY') ?: '',
    'use_groq' => !empty(getenv('GROQ_API_KEY')),
    'model' => getenv('GROQ_MODEL') ?: 'llama-3.3-70b-versatile',
    'max_tokens' => 500,
    'temperature' => 0.7,
    'supported_languages' => ['en', 'sw'],
    'hospital_info' => [
        'name' => 'Nyeri Town Health Center',
        'phone' => '+254700000000',
        'email' => 'info@nyerihospital.go.ke',
        'location' => 'Nyeri Town, Kenya'
    ]
];
