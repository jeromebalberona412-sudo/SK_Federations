<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\PreventsProductionDatabaseAccess;

abstract class TestCase extends BaseTestCase
{
    use PreventsProductionDatabaseAccess;
}
