<?php

namespace Database\Seeders\Concerns;

use RuntimeException;

/**
 * Demo data never goes into the real association database by accident.
 *
 * It runs on local machines, and on a server only when that server's .env
 * says DEMO_DATA_ALLOWED=true (for example a presentation or test host).
 */
trait GuardsDemoData
{
    protected function ensureDemoDataAllowed(): void
    {
        if (app()->isProduction() && ! config('app.demo_data_allowed')) {
            throw new RuntimeException(
                'Demo data is blocked on production. On a test or presentation host only, set DEMO_DATA_ALLOWED=true in .env.'
            );
        }
    }
}
