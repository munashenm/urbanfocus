<?php

namespace Tests;

use App\Services\IndexNowService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        IndexNowService::resetState();
    }
}
