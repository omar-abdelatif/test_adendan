<?php

namespace App\Modules\Shared\Http\Concerns;

use Illuminate\Http\RedirectResponse;

/**
 * The dashboard shows a toast from the session keys `message` and `alert-type`.
 * These helpers replace the array every controller used to build by hand.
 */
trait RespondsWithAlerts
{
    protected function success(string $message): RedirectResponse
    {
        return back()->with(['message' => $message, 'alert-type' => 'success']);
    }

    protected function failure(string $message): RedirectResponse
    {
        return back()->with(['message' => $message, 'alert-type' => 'error'])->withErrors([$message]);
    }
}
