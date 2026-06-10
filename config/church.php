<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Church Ministry Platform Configuration
    |--------------------------------------------------------------------------
    */

    'default_currency' => env('CHURCH_CURRENCY', 'KES'),

    'service_types' => [
        'sunday_morning'  => 'Sunday Morning Service',
        'sunday_evening'  => 'Sunday Evening Service',
        'wednesday'       => 'Wednesday Service',
        'friday'          => 'Friday Service',
        'saturday'        => 'Saturday Service',
        'special'         => 'Special Service',
        'crusade'         => 'Crusade / Revival',
        'prayer_meeting'  => 'Prayer Meeting',
        'conference'      => 'Conference / Seminar',
        'other'           => 'Other',
    ],

    'transaction_categories' => [
        'offering'  => 'Offering',
        'tithe'     => 'Tithe',
        'donation'  => 'Donation',
        'project'   => 'Project Contribution',
        'building'  => 'Building Fund',
        'welfare'   => 'Welfare / Benevolence',
        'pledge'    => 'Pledge',
        'harvesting'=> 'Harvesting Offering',
        'thanksgiving'=>'Thanksgiving Offering',
        'other'     => 'Other',
    ],

    'analytics' => [
        'cache_ttl'      => (int) env('ANALYTICS_CACHE_TTL', 3600),
        'default_period' => 30,
        'max_period'     => 365,
    ],

    'rate_limits' => [
        'auth'      => ['attempts' => 5,  'per_minutes' => 1],
        'api'       => ['attempts' => 120, 'per_minutes' => 1],
        'analytics' => ['attempts' => 30,  'per_minutes' => 1],
        'export'    => ['attempts' => 10,  'per_minutes' => 1],
    ],

    'pagination' => [
        'default_per_page' => 15,
        'max_per_page'     => 100,
    ],

    'roles' => [
        'ministry_admin' => [
            'level'        => 1,
            'display_name' => 'Ministry Administrator',
        ],
        'zone_admin' => [
            'level'        => 2,
            'display_name' => 'Zone Administrator',
        ],
        'church_admin' => [
            'level'        => 3,
            'display_name' => 'Church Administrator',
        ],
    ],

    'default_admin' => [
        'name'     => env('DEFAULT_ADMIN_NAME', 'Ministry Administrator'),
        'email'    => env('DEFAULT_ADMIN_EMAIL', 'admin@ministry.ke'),
        'password' => env('DEFAULT_ADMIN_PASSWORD', 'Admin@Kenya2024'),
    ],
];
