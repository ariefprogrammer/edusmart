<?php

return [
    // Role yang WAJIB OTP. Wali & siswa sengaja tidak dimasukkan.
    'required_roles' => [
        'super_admin',
        'owner',
        'finance_pusat',
        'koordinator_cabang',
        'admin_cabang',
        'guru',
    ],

    'code_length' => 6,
    'expires_in_minutes' => 10,
    'max_attempts' => 5,
    'resend_cooldown_seconds' => 60,
    'max_sends_per_hour' => 5,

    // Dipakai nanti di langkah trusted device
    'trusted_device_days' => 30,
    'trusted_device_cookie' => 'trusted_device',

    'trusted_device_days_by_role' => [
        'super_admin' => 7,
        'owner' => 7,
        'finance_pusat' => 7,
    ],
];