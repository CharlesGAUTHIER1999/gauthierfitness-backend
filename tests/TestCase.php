<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Rate limiters are backed by the cache; without this, throttled routes
        // (e.g. POST /api/payment/intent) can fail with 429 depending on test order.
        Cache::flush();
    }
}
