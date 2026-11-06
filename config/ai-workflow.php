<?php

/*
| Where Slack alerts go (docs/PLAN.md §7). Each event lists channels (by key in `channels`)
| and who gets a DM:
|   reporter  the person who reported the issue (Slack reporter, or their GitHub login mapped in config/team.php)
|   on_duty   developers marked on_duty in config/team.php
|   devs      every developer in config/team.php
|
| Messages about the same issue in the same channel are threaded together.
*/

return [

    // Channel names (without #) or IDs. Invite the Fugazi AI app to each one.
    'channels' => [
        'bugs' => env('SLACK_CHANNEL_BUGS', 'fugazi-bugs'),
        'features' => env('SLACK_CHANNEL_FEATURES', 'fugazi-features'),
        'research' => env('SLACK_CHANNEL_RESEARCH', 'fugazi-research'),
        'activity' => env('SLACK_CHANNEL_ACTIVITY', 'fugazi-ai-activity'),
        'reviews' => env('SLACK_CHANNEL_REVIEWS', 'fugazi-reviews'),
        'deploys' => env('SLACK_CHANNEL_DEPLOYS', 'fugazi-deploys'),
        'alerts' => env('SLACK_CHANNEL_ALERTS', 'fugazi-alerts'),
        'contact' => env('SLACK_CHANNEL_CONTACT', 'fugazi-contact'),
    ],

    'routes' => [
        'issue.bug' => ['channels' => ['bugs']],
        'issue.feature' => ['channels' => ['features']],
        'issue.research' => ['channels' => ['research']],
        'issue.done' => ['channels' => [], 'dm' => ['reporter']],

        'run.started' => ['channels' => ['activity']],
        'run.finished' => ['channels' => ['activity']],
        'run.failed' => ['channels' => ['activity', 'alerts'], 'dm' => ['on_duty']],

        'pr.opened' => ['channels' => ['activity']],
        'pr.needs_human' => ['channels' => ['reviews'], 'dm' => ['on_duty']],
        'pr.automerged' => ['channels' => ['activity'], 'dm' => ['reporter']],

        'ai.needs_human' => ['channels' => ['alerts'], 'dm' => ['on_duty']],
        'ai.budget' => ['channels' => ['alerts'], 'dm' => ['devs']],

        'release.ready' => ['channels' => ['deploys']],
        'release.shipping' => ['channels' => ['deploys']],
        'release.needs_approval' => ['channels' => ['deploys'], 'dm' => ['devs']],

        'contact.received' => ['channels' => ['contact']],
    ],

];
