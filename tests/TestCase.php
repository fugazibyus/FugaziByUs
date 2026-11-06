<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tests wipe the database they run against. They may only use in-memory SQLite
     * (the default from phpunit.xml) or a MySQL database whose name ends in "_test"
     * (CI sets DB_DATABASE=fugazi_test). Anything else – like the local dev
     * database – stops the run before a single table is touched.
     */
    protected function setUp(): void
    {
        $connection = getenv('DB_CONNECTION') ?: ($_ENV['DB_CONNECTION'] ?? 'sqlite');
        $database = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? ':memory:');

        if ($connection !== 'sqlite' && ! str_ends_with((string) $database, '_test')) {
            throw new RuntimeException(
                "Refusing to run tests against [{$connection}:{$database}]. Use a database whose name ends in _test."
            );
        }

        parent::setUp();

        // Feature tests check server responses, not compiled assets, so they don't
        // need a Vite build (CI has none; locally the dev server provides it).
        $this->withoutVite();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
