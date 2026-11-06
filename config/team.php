<?php

/*
| Who's who across GitHub and Slack. Used to send Slack DMs to the right person and to
| decide who may use the developer-only Slack commands (release, deploy, pause, resume).
|
| - github:  GitHub username
| - slack:   Slack member ID (Slack → profile → ⋮ → Copy member ID), e.g. U012AB3CD
| - dev:     a developer: may use developer commands; their issues count as developer issues
| - on_duty: gets the "a PR needs a human review" DMs
|
| Slack-only colleagues (a CEO reporting a bug, say) don't need to be listed: the app
| remembers who reported each Slack issue and DMs them updates.
*/

return [

    'members' => [
        [
            'name' => 'Samuel Goodwin',
            'github' => 'sgoodwin10',
            'slack' => env('TEAM_SLACK_SGOODWIN10'),
            'dev' => true,
            'on_duty' => true,
        ],
    ],

];
