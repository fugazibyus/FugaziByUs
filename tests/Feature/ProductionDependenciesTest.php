<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The production image runs `composer install --no-dev`, so every package the app uses at runtime
 * has to be a production dependency. Dev-only packages are installed locally and in CI, which hides
 * the problem until the code runs on staging.
 */
class ProductionDependenciesTest extends TestCase
{
    public function test_the_yaml_parser_behind_the_ai_workflow_page_is_a_production_dependency(): void
    {
        $composer = json_decode(File::get(base_path('composer.json')), true);
        $lock = json_decode(File::get(base_path('composer.lock')), true);

        $this->assertArrayHasKey('symfony/yaml', $composer['require'], 'symfony/yaml must be in "require", not only pulled in by dev tools.');
        $this->assertContains('symfony/yaml', array_column($lock['packages'], 'name'), 'symfony/yaml must be in the lock file\'s "packages", not "packages-dev".');
    }
}
