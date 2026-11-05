<?php

namespace App\Providers;

use Anthropic\Client;
use App\Ai\AgentRegistry;
use App\Ai\AnthropicManagedAgentsGateway;
use App\Ai\Contracts\ManagedAgentsGateway;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AgentRegistry::class, fn () => new AgentRegistry(
            agentsPath: (string) config('ai.agents_path'),
            lockFile: (string) config('ai.lock_file'),
        ));

        // The Anthropic client resolves credentials itself: ANTHROPIC_API_KEY, or the
        // profile from `ant auth login` when no key is set.
        $this->app->bind(ManagedAgentsGateway::class, fn () => new AnthropicManagedAgentsGateway(
            new Client,
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Same strength rules everywhere so local/staging behave like production.
        // The breached-password check (a network call) only runs in production.
        Password::defaults(function (): Password {
            $rule = Password::min(12)->max(128)->letters()->mixedCase()->numbers()->symbols();

            return app()->isProduction() ? $rule->uncompromised() : $rule;
        });
    }
}
