<?php

namespace App\Http\Controllers\Slack;

use App\Http\Controllers\Controller;
use App\Jobs\CreateIssueFromSlack;
use App\Jobs\RunSlackAction;
use App\Models\AiPullRequest;
use App\Models\AiRun;
use App\Slack\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The `/fugazi` slash command. Slack wants an answer within three seconds, so anything that
 * talks to GitHub runs in a queued job and replies through Slack's response URL.
 */
class SlackCommandController extends Controller
{
    private const HELP = "*`/fugazi` commands*\n"
        ."• `/fugazi bug <what's wrong>` · `/fugazi feature <idea>` · `/fugazi research <question>`: create a GitHub issue for the AI workflow\n"
        ."• `/fugazi status`: today's AI spend, agents at work, open AI pull requests\n"
        ."• `/fugazi pause`: kill switch, turns AI merging and AI production deploys off\n"
        ."• Developers: `/fugazi release`, `/fugazi deploy production`, `/fugazi resume`\n"
        .'Or use *Send to Fugazi AI* from any message\'s ⋮ menu.';

    public function __invoke(Request $request, Team $team): JsonResponse
    {
        $user = (string) $request->input('user_id');
        $name = (string) $request->input('user_name');
        $responseUrl = (string) $request->input('response_url');
        [$command, $text] = array_pad(preg_split('/\s+/', trim((string) $request->input('text')), 2) ?: [], 2, '');
        $command = strtolower($command);
        $text = trim($text);

        switch ($command) {
            case 'bug':
            case 'feature':
            case 'research':
                if (mb_strlen($text) < 10) {
                    return $this->reply("Tell me a bit more, e.g. `/fugazi {$command} The contact form doesn't show an error when the email is wrong`.");
                }

                CreateIssueFromSlack::dispatch($command, $text, $user, $name, $responseUrl);

                return $this->reply("👍 Got it. Creating a GitHub {$command} for the AI workflow…");

            case 'status':
                return $this->reply($this->status());

            case 'pause':
                if ($team->bySlack($user) === null) {
                    return $this->reply('Only people listed in config/team.php can pause the AI.');
                }

                RunSlackAction::dispatch('pause', $user, $responseUrl);

                return $this->reply('⏸️ Pausing AI merges and AI production deploys…');

            case 'resume':
            case 'release':
            case 'deploy':
                if (! $team->isDev($user)) {
                    return $this->reply("Only developers can use `/fugazi {$command}`.");
                }

                if ($command === 'deploy' && strtolower($text) !== 'production') {
                    return $this->reply('Use `/fugazi deploy production`. Staging deploys by itself whenever develop changes.');
                }

                RunSlackAction::dispatch($command, $user, $responseUrl);

                return $this->reply('🚦 On it…');

            default:
                return $this->reply(self::HELP);
        }
    }

    private function status(): string
    {
        $today = AiRun::where('started_at', '>=', now()->startOfDay());
        $running = AiRun::where('status', 'running')->pluck('title')->all();

        return sprintf(
            "*FugaziByUs AI today*\n• Spend: $%.2f across %d run(s)\n• Working now: %s\n• Open AI pull requests: %d",
            (int) (clone $today)->sum('cost_cents') / 100,
            (clone $today)->count(),
            $running === [] ? 'nobody' : implode(', ', $running),
            AiPullRequest::where('state', 'open')->count(),
        );
    }

    private function reply(string $text): JsonResponse
    {
        return response()->json(['response_type' => 'ephemeral', 'text' => $text]);
    }
}
