<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per Claude Managed Agents session. Filled from Anthropic webhooks and the
     * `ai:sync-runs` safety net; feeds the homepage "AI at work" widget.
     */
    public function up(): void
    {
        Schema::create('ai_runs', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique();
            $table->string('agent', 32)->index();
            $table->unsignedInteger('agent_version')->nullable();
            $table->string('title')->nullable();
            $table->string('status', 20);           // running · idle · rescheduling · terminated
            $table->string('outcome', 40)->nullable(); // from the agent's JSON result, e.g. pr_opened
            $table->boolean('succeeded')->nullable(); // null while running or unknown
            $table->string('summary', 500)->nullable();
            $table->unsignedInteger('issue')->nullable()->index();
            $table->unsignedInteger('pull_request')->nullable()->index();
            $table->string('branch')->nullable();
            $table->string('trigger', 40)->nullable();
            $table->string('actor', 100)->nullable();
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('cache_read_tokens')->default(0);
            $table->unsignedBigInteger('cache_creation_tokens')->default(0);
            $table->unsignedInteger('cost_cents')->default(0); // list price, US cents
            $table->unsignedInteger('active_seconds')->default(0);
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('finished_at')->nullable();
            $table->boolean('demo')->default(false); // rows from the demo seeder
            $table->timestamps();
        });

        Schema::create('ai_pull_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('number')->unique();
            $table->string('title');
            $table->string('branch');
            $table->string('state', 10); // open · closed · merged
            $table->boolean('auto_merged')->default(false);
            $table->unsignedInteger('issue')->nullable();
            $table->timestamp('issue_opened_at')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('merged_at')->nullable();
            $table->boolean('demo')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_pull_requests');
        Schema::dropIfExists('ai_runs');
    }
};
