<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Migrate the database without dropping all tables simultaneously.
     * Workaround for TiDB multi-table qualified DROP TABLE issue.
     */
    protected function migrateDatabases()
    {
        $this->artisan('migrate');
    }
}
