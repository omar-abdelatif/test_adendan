<?php

/*
 * Paymob online payments. Read through config('paymob.*') only.
 *
 * The older variable names (PAYMOB_SK, PAYMOB_PK) are still read as a fallback
 * so servers that already have them keep working.
 */

$ids = fn (?string $list): array => array_values(array_filter(
    array_map('trim', explode(',', (string) $list)),
    fn (string $id) => $id !== '',
));

return [
    'base_url' => rtrim((string) (env('PAYMOB_BASE_URL') ?: 'https://accept.paymob.com'), '/'),

    // Intention API (Authorization: Token ...) and the Unified Checkout URL.
    'secret_key' => env('PAYMOB_SECRET_KEY') ?: env('PAYMOB_SK'),
    'public_key' => env('PAYMOB_PUBLIC_KEY') ?: env('PAYMOB_PK'),

    // Only for the legacy auth/order/payment-key flow (not used by the server checkout).
    'api_key' => env('PAYMOB_API_KEY'),

    // Paymob dashboard > Settings > HMAC. Signs the "transaction processed" callback.
    'hmac_secret' => env('PAYMOB_HMAC_SECRET'),

    'card_integration_id' => env('PAYMOB_CARD_INTEGRATION_ID'),
    'wallet_integration_id' => env('PAYMOB_WALLET_INTEGRATION_ID'),
    'iframe_id' => env('PAYMOB_IFRAME_ID') ?: '902083',

    // Callbacks from any other integration (for example the sandbox ones) record nothing.
    'live_integration_ids' => $ids(env('PAYMOB_LIVE_INTEGRATION_IDS')),

    // Billing email sent to Paymob. Members have none on file, and Paymob rejects "NA".
    'billing_email' => env('PAYMOB_BILLING_EMAIL') ?: 'members@adendan.com',

    // Transfer fee the member pays on top of the amount: fixed part plus a
    // percentage (275 basis points = 2.75%). Only the amount is credited.
    'fee_fixed_piasters' => (int) (env('PAYMOB_FEE_FIXED_PIASTERS') ?: 300),
    'fee_basis_points' => (int) (env('PAYMOB_FEE_BASIS_POINTS') ?: 275),

    // How long a checkout stays open before the expiry job marks it expired.
    'checkout_ttl_minutes' => 60,
];
