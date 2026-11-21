<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase wipes the database: never let it touch anything but the test one.
        $database = config('database.connections.' . config('database.default') . '.database');
        if ($database !== 'adendan_test') {
            throw new RuntimeException("Refusing to run tests against database [{$database}].");
        }
    }
}
