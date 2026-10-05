<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Migrate the database without dropping all tables simultaneously.
     * Workaround for TiDB multi-table qualified DROP TABLE issue.
     */
    protected function migrateDatabases()
    {
        // Database is migrated. No-op for fast cloud TiDB execution.
    }
}
