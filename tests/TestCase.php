<?php

namespace Tests;

use App\Support\RequestContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * The whole suite runs in one PHP process, so the RequestContext SINGLETON
     * has to be dropped between tests. Without this, every test would inherit the
     * first test's correlation ID and the grouping assertions would pass for the
     * wrong reason.
     */
    protected function setUp(): void
    {
        parent::setUp();

        RequestContext::reset();
    }

    protected function tearDown(): void
    {
        RequestContext::reset();

        parent::tearDown();
    }
}
