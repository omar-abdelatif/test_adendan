<?php

namespace App\Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * GET /api/pay/callback: where Paymob sends the member's browser after paying.
 * Its query string comes from the member's phone and can be faked, so this page
 * records nothing. The app closes the WebView when it sees Paymob's parameters,
 * then asks GET /api/payments/{id} for the real result.
 */
class PaymentReturnController extends Controller
{
    public function __invoke(): Response
    {
        $html = <<<'HTML'
            <!doctype html>
            <html lang="ar" dir="rtl">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title>جاري تأكيد الدفع</title>
                <style>
                    body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
                           font-family: system-ui, "Segoe UI", Tahoma, sans-serif; background: #f7f3ea; color: #0f4c5c; }
                    p { font-size: 1.25rem; padding: 1.5rem; text-align: center; }
                </style>
            </head>
            <body><p>جاري تأكيد الدفع… ارجع للتطبيق</p></body>
            </html>
            HTML;

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
