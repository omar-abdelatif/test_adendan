<?php

/*
 * Association rules that used to be hard-coded in controllers.
 * Change them here (or via .env) instead of in code.
 */
return [

    'fees' => [
        // Paid once on joining, on top of the prorated subscription.
        'joining' => (int) env('FEE_JOINING', 50),

        // Annual donation collected from every new member.
        'annual_donation' => (int) env('FEE_ANNUAL_DONATION', 40),

        // Donation dues every new member starts with. The item names must match
        // the existing dues rows exactly (including the trailing space below),
        // because online payments find the due by item name.
        'new_member_dues' => [
            ['item' => 'تبرع سيارة التكريم', 'amount' => (int) env('FEE_HONOR_CAR', 100)],
            ['item' => 'تبرع صيانة المقرات ', 'amount' => (int) env('FEE_HQ_MAINTENANCE', 200)],
        ],
    ],

    'sms' => [
        // Days after expiry before an SMS subscription is switched off.
        'grace_days' => (int) env('SMS_GRACE_DAYS', 15),
    ],

];
