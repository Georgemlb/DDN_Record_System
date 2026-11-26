<?php

return [
    'branch' => [
        'name' => env('DDN_BRANCH_NAME', 'DDN Travel & Ticketing Services'),
        'tagline' => env(
            'DDN_BRANCH_TAGLINE',
            'Trusted airline ticketing, ferry booking, and document translation in Toril, Davao City.',
        ),
        'address' => env(
            'DDN_BRANCH_ADDRESS',
            "Stall 2 & 3, Ermely's Complex, Agton St., Toril, Davao City",
        ),
        'phone' => env('DDN_BRANCH_PHONE', '09064280203 / 09083702184'),
        'telephone' => env('DDN_BRANCH_TELEPHONE', '082-3214125'),
        'email' => env('DDN_BRANCH_EMAIL', 'ddnticketing@gmail.com'),
        'facebook_name' => env('DDN_BRANCH_FACEBOOK_NAME', 'Ddn Ticketing'),
        'facebook_url' => env('DDN_BRANCH_FACEBOOK_URL', ''),
        'business_hours' => env('DDN_BRANCH_HOURS', '9:00 AM - 5:00 PM'),
    ],

    'admin' => [
        'name' => env('DDN_ADMIN_NAME', 'Tech Admin'),
        'username' => env('DDN_ADMIN_USERNAME', 'admin'),
        'email' => env('DDN_ADMIN_EMAIL', 'admin@ddn.com'),
        'password' => env('DDN_ADMIN_PASSWORD'),
    ],
];
