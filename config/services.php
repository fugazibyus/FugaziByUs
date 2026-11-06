<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Claude Managed Agents webhooks (Console → Webhooks). Endpoint: POST /webhooks/anthropic
    'anthropic' => [
        'webhook_signing_key' => env('ANTHROPIC_WEBHOOK_SIGNING_KEY'),
    ],

    // The "Fugazi AI" Slack app (slack/manifest.yml). Without a bot token, alerts are skipped.
    'slack' => [
        'bot_token' => env('SLACK_BOT_TOKEN'),
        'signing_secret' => env('SLACK_SIGNING_SECRET'),
    ],

    // GitHub sends repository events to POST /webhooks/github, signed with this secret.
    'github' => [
        'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
        // Signs developer requests from Slack that GitHub Actions acts on (release, deploy, resume).
        // The same value is the repository secret AI_DISPATCH_SECRET.
        'dispatch_secret' => env('AI_DISPATCH_SECRET'),
    ],

];
