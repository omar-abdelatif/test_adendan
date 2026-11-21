<?php

namespace App\Modules\Shared\Http\Concerns;

use DomainException;
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
        return back()->withInput()->with(['message' => $message, 'alert-type' => 'error'])->withErrors([$message]);
    }

    /**
     * Runs a use case and reports the result as a toast. Business-rule errors
     * (domain exceptions carry an Arabic message for staff) become an error toast;
     * anything else is a bug and is left to the exception handler.
     */
    protected function attempt(callable $useCase, string $successMessage): RedirectResponse
    {
        try {
            $useCase();
        } catch (DomainException $e) {
            return $this->failure($e->getMessage());
        }

        return $this->success($successMessage);
    }
}
