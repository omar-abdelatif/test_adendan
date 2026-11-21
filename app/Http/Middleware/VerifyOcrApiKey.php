<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only the OCR engine may push national ID data. It sends the shared key
 * from OCR_API_KEY in the X-Api-Key header. With no key configured, every
 * request is refused, so a missing setting never leaves the endpoint open.
 */
class VerifyOcrApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.ocr.api_key');
        $given = (string) $request->header('X-Api-Key');

        if ($expected === '' || ! hash_equals($expected, $given)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
