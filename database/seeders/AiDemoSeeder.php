<?php

namespace Database\Seeders;

use App\Models\AiPullRequest;
use App\Models\AiRun;
use App\Support\AiStats;
use Illuminate\Database\Seeder;

/**
 * A month of believable agent activity for demos, so the homepage widget isn't empty
 * before the real workflow has run much. Rows are marked `demo` and `make ai-demo-clear`
 * removes them. Refuses to run in production.
 */
class AiDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('The AI demo data is for local and staging demos only.');

            return;
        }

        $counts = ['triage' => 24, 'developer' => 14, 'reviewer' => 16, 'debugger' => 5, 'researcher' => 3, 'release' => 4];

        foreach ($counts as $agent => $count) {
            AiRun::factory()->demo()->agent($agent)->count($count)->create();
        }

        AiRun::factory()->demo()->agent('developer')->failed()->count(2)->create();
        AiRun::factory()->demo()->agent('debugger')->failed()->create();
        AiPullRequest::factory()->demo()->count(11)->create();
        AiPullRequest::factory()->demo()->open()->count(2)->create();

        AiStats::forget();
    }
}
