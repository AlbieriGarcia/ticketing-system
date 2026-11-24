<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    // Protección: RefreshDatabase borra todo, así que nunca debe ejecutarse fuera de una base de pruebas
    protected function setUpTraits()
    {
        $database = config('database.connections.'.config('database.default').'.database');

        if (! str_ends_with($database, '_test')) {
            throw new RuntimeException("Refusing to run tests against non-test database [{$database}].");
        }

        return parent::setUpTraits();
    }
}
