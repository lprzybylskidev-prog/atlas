<?php

declare(strict_types=1);

return [
    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'password_current_mismatch' => 'The provided password does not match your current password.',
    'mfa_code_invalid' => 'The provided MFA code was invalid.',
    'password_policy' => [
        'no_three_identical_consecutive_characters' => 'The password must not contain three identical consecutive characters.',
        'not_based_on_user_data' => 'The password must not be based on account details.',
        'not_recently_used' => 'The password was used recently. Choose a different password.',
    ],
    'rate_limited' => 'Too many attempts. Please wait before trying again.',
    'session_conflict' => 'This account is already active in another session. Continue here to terminate the previous session, or cancel this login.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
];
