<?php

return [
    'otp' => [
        'ttl_seconds' => (int) env('WAYMATE_OTP_TTL', 300),
        'max_attempts' => (int) env('WAYMATE_OTP_MAX_ATTEMPTS', 5),
        'resend_cooldown_seconds' => (int) env('WAYMATE_OTP_RESEND_COOLDOWN', 60),
        'max_requests_per_window' => (int) env('WAYMATE_OTP_MAX_REQUESTS', 5),
        'request_window_seconds' => (int) env('WAYMATE_OTP_REQUEST_WINDOW', 600),
    ],

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'timeout_seconds' => (int) env('SMS_TIMEOUT_SECONDS', 15),
        'allow_log_in_production' => (bool) env('SMS_ALLOW_LOG_IN_PRODUCTION', false),

        'bulksmsbd' => [
            'url' => env('BULKSMSBD_URL', 'https://bulksmsbd.net/api/smsapi'),
            'api_key' => env('BULKSMSBD_API_KEY'),
            'sender_id' => env('BULKSMSBD_SENDER_ID'),
        ],

        'sslwireless' => [
            'url' => env('SSLWIRELESS_URL', 'https://smsplus.sslwireless.com/api/v3/send-sms'),
            'api_token' => env('SSLWIRELESS_API_TOKEN'),
            'sid' => env('SSLWIRELESS_SID'),
        ],

        'twilio' => [
            'account_sid' => env('TWILIO_ACCOUNT_SID'),
            'auth_token' => env('TWILIO_AUTH_TOKEN'),
            'from' => env('TWILIO_FROM'),
        ],
    ],

    'matching' => [
        'corridor_m' => (int) env('WAYMATE_MATCH_CORRIDOR_M', 1200),
        'min_overlap_m' => (int) env('WAYMATE_MATCH_MIN_OVERLAP_M', 500),
        'min_cos_theta' => (float) env('WAYMATE_MATCH_MIN_COS', 0.5),
        'score_threshold' => (int) env('WAYMATE_MATCH_SCORE_THRESHOLD', 55),
        'lead_time_min' => (int) env('WAYMATE_MATCH_LEAD_TIME_MIN', 15),
        'horizon_days' => (int) env('WAYMATE_MATCH_HORIZON_DAYS', 7),
        'offer_window_min' => (int) env('WAYMATE_MATCH_OFFER_WINDOW_MIN', 60),
        'request_window_min' => (int) env('WAYMATE_MATCH_REQUEST_WINDOW_MIN', 90),
        'search_limit' => (int) env('WAYMATE_MATCH_SEARCH_LIMIT', 100),
    ],

    'fare' => [
        'max_fare_version' => (int) env('WAYMATE_FARE_MAX_VERSION', 3),
        'adjustment_vote_window_min' => (int) env('WAYMATE_FARE_VOTE_WINDOW_MIN', 10),
        'adjustment_approval_ratio' => (float) env('WAYMATE_FARE_APPROVAL_RATIO', 0.6),
    ],

    'join' => [
        'request_ttl_hours' => (int) env('WAYMATE_JOIN_REQUEST_TTL_HOURS', 24),
        'offer_ttl_hours' => (int) env('WAYMATE_JOIN_OFFER_TTL_HOURS', 12),
    ],

    'maps' => [
        'google_key' => env('GOOGLE_MAPS_API_KEY'),
        'cache_ttl_days' => (int) env('WAYMATE_MAP_CACHE_TTL_DAYS', 30),
    ],

    'geocoding' => [
        'provider' => env('WAYMATE_GEOCODING_PROVIDER', 'nominatim'),
        'user_agent' => env('WAYMATE_GEOCODING_UA', 'WayMate/1.0 (dev@waymate.local)'),
    ],
];
