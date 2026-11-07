<?php

// Platformanın biznes və təhlükəsizlik konfiqurasiyası. Billing qaydaları (grace, öhdəlik və s.)
// verilənlər bazasındakı `billing_rules` cədvəlindədir (admin dəyişir, audit log-a yazılır).
return [
    'locales' => ['az', 'en', 'ru'],
    'display_timezone' => 'Asia/Baku',

    'auth' => [
        'session_limit_default' => 2,           // eyni vaxtda aktiv cihaz (billing_rules.session_limit ilə əvəz olunur)
        'email_token_ttl_hours' => 24,
        'password_reset_ttl_minutes' => 60,
        'resend_limit_per_hour' => 3,
        'password_min_length' => 10,
        'staff_idle_minutes' => 120,            // admin/təlimçi sessiyası 2 saat hərəkətsizlikdən sonra bitir
        'recent_auth_minutes' => 15,            // həssas əməliyyatlar üçün təkrar parol
    ],

    'demo' => [
        'duration_hours' => 24,
        'default_open_modules' => 2,
        'min_fill_seconds' => 3,
    ],

    'video' => [
        'token_ttl_minutes' => 10,
        'heartbeat_seconds' => 15,
        'anomaly_tokens_per_hour' => 60,
    ],

    'mail' => [
        'max_per_minute' => (int) env('MAIL_MAX_PER_MINUTE', 20),
        'max_attempts' => 6,
    ],

    // Netlify imzalı proxy (x-nf-sign, HS256). Production-da məcburidir.
    'proxy' => [
        'require_signature' => (bool) env('PROXY_REQUIRE_SIGNATURE', true),
        'secret' => env('PLATFORM_PROXY_SECRET'),
    ],

    'landing_url' => env('LANDING_URL', 'https://marketingschool.az'),
];
