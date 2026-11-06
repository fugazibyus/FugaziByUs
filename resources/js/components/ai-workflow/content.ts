/*
 * Text for the AI Workflow handbook. It mirrors README.md and docs/PLAN.md; when a rule
 * changes there, change it here too. The agents themselves come from agents/*.md via the
 * controller, so their names, models, budgets and prompts are never copied by hand.
 */

export type Row = { term: string; detail: string };

export type Actor = 'person' | 'ai' | 'gate' | 'system';

export const actorLabels: Record<Actor, string> = {
    person: 'Person',
    ai: 'AI agent',
    gate: 'Policy gate',
    system: 'Automation',
};

export const overviewFacts = [
    { value: '6', label: 'specialist agents, each with one job' },
    { value: '2', label: 'policy gates decide what merges and ships' },
    { value: '$25', label: 'daily spending cap, plus a budget per run' },
    { value: '1', label: 'switch hands merging and shipping back to people' },
];

export const workflowSteps: {
    title: string;
    actor: Actor;
    detail: string;
    branch?: { yes: string; no: string };
}[] = [
    {
        title: 'Someone reports work',
        actor: 'person',
        detail: 'A GitHub issue form (Bug, Feature or Research), or “Create issue” on any Slack message. Every request becomes a GitHub issue.',
    },
    {
        title: 'Triage',
        actor: 'ai',
        detail: 'Within minutes the triage agent adds type, priority and size, writes acceptance criteria and decides whether the AI or a developer takes it. If something is unclear it asks in the issue and runs again when the reporter replies.',
    },
    {
        title: 'Build',
        actor: 'ai',
        detail: 'The developer agent (or the researcher, for questions) branches from develop as ai/<type>/<issue>-slug, writes the change and its tests, and opens a pull request.',
    },
    {
        title: 'Test and fix',
        actor: 'system',
        detail: 'CI runs style checks, static analysis, PHPUnit on MySQL and the build. If anything fails, the debugger agent reads the logs and fixes the branch, at most twice, then hands it to a developer.',
    },
    {
        title: 'Review',
        actor: 'ai',
        detail: 'The reviewer agent reads the diff for correctness, security, tests and UI quality, and records a verdict: approve or request changes, with a risk level.',
    },
    {
        title: 'Merge Gate',
        actor: 'gate',
        detail: 'Plain, tested code (not a prompt) checks the pull request against the merge policy.',
        branch: {
            yes: 'Eligible: the gate merges into develop.',
            no: 'Not eligible: it explains why and a developer reviews and merges.',
        },
    },
    {
        title: 'Staging',
        actor: 'system',
        detail: 'Every merge into develop builds the images once and deploys them to staging, with an automatic rollback if the new version is unhealthy.',
    },
    {
        title: 'Release',
        actor: 'ai',
        detail: 'Monday to Thursday morning the release agent opens a develop → main pull request with plain-language release notes and the risks.',
    },
    {
        title: 'Deploy Gate',
        actor: 'gate',
        detail: 'Checks the release against the deploy policy: business hours, staging has run this exact commit for 30 minutes and is healthy, nothing on hold.',
        branch: {
            yes: 'Eligible: the gate merges the release.',
            no: 'Not eligible: a developer decides.',
        },
    },
    {
        title: 'Production',
        actor: 'system',
        detail: 'Production reuses the exact images staging ran. Slack posts the result in #fugazi-deploys, and failures roll back on their own.',
    },
];

/** When each agent starts. Keyed by the folder name in agents/. */
export const agentTriggers: Record<string, string> = {
    triage: 'An issue is opened, or the reporter answers its questions',
    researcher: 'A research issue is approved, or /ai research',
    developer: 'A flow:ai issue is approved, or /ai develop',
    debugger: 'CI fails on an ai/* pull request, or /ai fix',
    reviewer: 'CI passes on an ai/* pull request, or /ai review',
    release: 'Monday–Thursday 08:30 Sydney time, or /ai release',
};

export const commandGroups: {
    title: string;
    intro: string;
    rows: Row[];
}[] = [
    {
        title: 'GitHub comments',
        intro: 'Any repository collaborator can start a comment with these. Text after the command goes to the agent as extra instructions.',
        rows: [
            { term: '/ai triage', detail: 'Issue: run triage again' },
            {
                term: '/ai develop [notes]',
                detail: 'Issue: start the developer (the researcher for research issues)',
            },
            {
                term: '/ai research [notes]',
                detail: 'Issue: start the researcher',
            },
            {
                term: '/ai fix [hint]',
                detail: 'Pull request: start the debugger',
            },
            {
                term: '/ai review [focus]',
                detail: 'Pull request: start the reviewer',
            },
            {
                term: '/ai release',
                detail: 'Open or refresh the release pull request',
            },
            {
                term: '/ai stop',
                detail: 'Stop every agent working on this issue or pull request',
            },
            {
                term: '/ai merge',
                detail: 'Developers: ask the Merge Gate to merge now',
            },
            {
                term: '/ai deploy production',
                detail: 'Developers, on the release pull request: ask the Deploy Gate to ship now',
            },
            { term: '/ai help', detail: 'List the commands' },
        ],
    },
    {
        title: 'Slack',
        intro: 'Slack and GitHub are connected directly. New issues and AI progress arrive in the #fugazi-* channels.',
        rows: [
            {
                term: '⋮ → Create issue',
                detail: 'Turn any Slack message into a GitHub issue',
            },
            {
                term: '/github open fugazibyus/FugaziByUs',
                detail: 'Open a new issue from Slack',
            },
            {
                term: '/github subscribe …',
                detail: 'Follow issues, pull requests or deploys in a channel',
            },
        ],
    },
    {
        title: 'Claude Code (on your own computer)',
        intro: 'The same six agents, running on your working copy instead of in the cloud.',
        rows: [
            { term: '/triage 12', detail: 'Triage issue #12' },
            { term: '/develop 12', detail: 'Build issue #12 on a branch' },
            { term: '/review 34', detail: 'Review pull request #34' },
            {
                term: '/debug <error>',
                detail: 'Diagnose and fix a failing check',
            },
            { term: '/research <question>', detail: 'Get a sourced answer' },
            { term: '/release', detail: 'Draft release notes' },
        ],
    },
    {
        title: 'Terminal',
        intro: 'For developers, from the project folder.',
        rows: [
            {
                term: 'make ai-run agent=developer issue=12',
                detail: 'Start a cloud agent on an issue',
            },
            {
                term: 'make ai-stop issue=12',
                detail: 'Kill switch for one issue (or pr=34, all=1)',
            },
            {
                term: 'make ai-agents',
                detail: 'List agents, models, budgets and IDs',
            },
            {
                term: 'make ai-plan · make ai-apply',
                detail: 'Preview and push agent changes to Anthropic',
            },
            {
                term: 'make ai-demo · make ai-demo-clear',
                detail: 'Fill or clear the homepage widget with demo data',
            },
        ],
    },
];

export const guardrails: { title: string; detail: string }[] = [
    {
        title: 'The AI merges only through the Merge Gate',
        detail: 'Tested code, not a prompt. It needs an ai/* branch, green CI, a reviewer approval with low or medium risk on the latest commit, a small enough change, no sensitive files and no database migrations.',
    },
    {
        title: 'Production changes only through the Deploy Gate',
        detail: 'Business hours Monday to Thursday, staging has run the exact commit for at least 30 minutes, every pull request in the release was merged by a developer or the Merge Gate, and nothing is on hold.',
    },
    {
        title: 'Non-developers’ requests stop for a person',
        detail: 'Issues raised by someone outside the developer team never merge automatically unless a developer opts them in.',
    },
    {
        title: 'Protected files',
        detail: 'CI workflows, agent prompts, infrastructure, deploy scripts, dependencies and secrets files fail the Guardrails check on AI branches unless a developer allows it.',
    },
    {
        title: 'Hard spending limits',
        detail: 'Each run has a dollar budget, a daily circuit breaker stops new runs at the cap, and the Anthropic workspace has its own limit.',
    },
    {
        title: 'Bounded loops',
        detail: 'The debugger gets two attempts, at most three agents run at once, and /ai stop ends a run immediately.',
    },
    {
        title: 'No secrets and no production access',
        detail: 'Agents work in a sandbox with the repository and a one-hour GitHub token. They never see server credentials.',
    },
    {
        title: 'Untrusted text stays data',
        detail: 'Issue, pull request and Slack text is handed to agents as data. Instructions hidden in it are ignored and reported.',
    },
    {
        title: 'Everything is traceable',
        detail: 'Each agent session is linked from its pull request, logged with its cost, and every gate decision is written down.',
    },
];

export const switches: Row[] = [
    { term: 'AI_AGENTS', detail: 'on · off. Off stops every agent.' },
    {
        term: 'AI_AUTOMERGE_MODE',
        detail: 'off · devs-only · all. Who the Merge Gate may merge for.',
    },
    {
        term: 'AI_AUTODEPLOY_STAGING',
        detail: 'on · off. Automatic staging deploys.',
    },
    {
        term: 'AI_AUTODEPLOY_PRODUCTION',
        detail: 'off · business-hours · on. When the Deploy Gate may ship.',
    },
    {
        term: 'AI_MAX_AUTOMERGE_SIZE',
        detail: 's · m · l. The largest change that merges on its own.',
    },
    {
        term: 'AI_DAILY_BUDGET_USD',
        detail: 'Dollars per day before new runs are refused.',
    },
];

export const branches: Row[] = [
    {
        term: 'main',
        detail: 'Production. Only release pull requests and hotfixes.',
    },
    { term: 'develop', detail: 'Staging. Features and fixes merge here.' },
    {
        term: 'feature/12-short-name',
        detail: 'People. Also fix/…, chore/…, docs/…, from develop.',
    },
    {
        term: 'ai/feature/12-short-name',
        detail: 'AI agents. Also ai/fix/…, ai/research/…. Reviewed, fixed and possibly merged automatically.',
    },
    {
        term: 'hotfix/short-name',
        detail: 'Urgent production fixes, from main into main, then back into develop.',
    },
];

export const gitCheatsheet: { title: string; commands: string[] }[] = [
    {
        title: 'Start work on issue #12',
        commands: [
            'git switch develop && git pull',
            'git switch -c feature/12-short-name',
        ],
    },
    {
        title: 'Commit and open a pull request',
        commands: [
            'git add -p',
            'git commit',
            'git push -u origin HEAD',
            'gh pr create --base develop',
        ],
    },
    {
        title: 'Take over an AI branch',
        commands: ['gh pr checkout 34', 'git push'],
    },
    {
        title: 'Catch up with develop',
        commands: ['git fetch origin', 'git merge origin/develop'],
    },
];

export const labelGroups: { title: string; rows: Row[] }[] = [
    {
        title: 'Where an issue is',
        rows: [
            { term: 'needs-triage', detail: 'Waiting for the triage agent' },
            {
                term: 'ai:needs-info',
                detail: 'Triage asked the reporter a question',
            },
            { term: 'ai:in-progress', detail: 'An agent is working on it' },
            { term: 'ai:pr-open', detail: 'A pull request is open' },
            { term: 'ready-for-review', detail: 'Waiting for a developer' },
            { term: 'ai:automerged', detail: 'Merged by the Merge Gate' },
            {
                term: 'ai:needs-human · ai:failed',
                detail: 'The AI stopped; a developer takes over',
            },
        ],
    },
    {
        title: 'What kind of work',
        rows: [
            {
                term: 'type:bug · type:feature · type:research · type:chore',
                detail: 'Set by the issue form or triage',
            },
            { term: 'flow:ai · flow:human', detail: 'Who builds it' },
            {
                term: 'size:s · size:m · size:l',
                detail: 'How big triage thinks it is',
            },
            {
                term: 'priority:low · priority:medium · priority:high',
                detail: 'How urgent it is',
            },
        ],
    },
    {
        title: 'Controls for developers',
        rows: [
            {
                term: 'ai:go',
                detail: 'Let the AI start on a large or outside issue',
            },
            {
                term: 'ai:automerge',
                detail: 'Let the Merge Gate merge a non-developer’s request',
            },
            {
                term: 'ai:no-automerge',
                detail: 'Always merge this one by hand',
            },
            { term: 'ai:stop', detail: 'Stop the agents and keep them off' },
            {
                term: 'deploy:hold',
                detail: 'Keep this out of automatic production deploys',
            },
            {
                term: 'allow-sensitive-paths',
                detail: 'Allow an AI change to protected files',
            },
        ],
    },
];

export const developerGuide: { title: string; items: string[] }[] = [
    {
        title: 'Get running',
        items: [
            'Install Docker Desktop, clone the repository and run make setup. The site opens on localhost.',
            'make test runs the PHP tests, make check runs everything CI runs, and make lint fixes style.',
            'docs/SETUP-LOCAL.md has the details and troubleshooting.',
        ],
    },
    {
        title: 'Working next to the agents',
        items: [
            'Pick up any issue yourself: assign it, or label it flow:human.',
            'Take over an AI pull request by checking out its branch and pushing. Comment /ai stop first if an agent is still working on it.',
            'Use the agents locally in Claude Code with /develop, /review and friends. They follow the same rules in CLAUDE.md and the shared skills.',
        ],
    },
    {
        title: 'Changing an agent',
        items: [
            'Edit agents/<name>/agent.md on a branch: the prompt, model, budget or tools.',
            'Run make ai-sync and open a pull request. Agent files need a developer’s review.',
            'After merge, the sync workflow pushes the change to Anthropic and opens a small lock-file pull request to merge.',
        ],
    },
    {
        title: 'Shipping and undoing',
        items: [
            'Staging deploys itself after every merge into develop. Production ships through the release pull request.',
            'Actions → Deploy → Run workflow redeploys any commit; on production it must already be on main.',
            'Every deploy checks itself and rolls back automatically. Break-glass rollback and logs are in docs/SETUP-HOSTED.md.',
        ],
    },
];

export const stack: Row[] = [
    {
        term: 'App',
        detail: 'Laravel 13 · PHP 8.4 · Inertia 3 · Vue 3 · TypeScript · Tailwind 4 · shadcn-vue',
    },
    { term: 'Data', detail: 'MySQL 8.4' },
    {
        term: 'Quality',
        detail: 'PHPUnit · PHPStan · Pint · Vite+ lint and format · vue-tsc · Playwright',
    },
    {
        term: 'AI',
        detail: 'Claude Managed Agents in the cloud, Claude Code locally, agents defined in agents/*.md',
    },
    {
        term: 'Delivery',
        detail: 'GitHub Actions · DigitalOcean Droplets and Container Registry · Let’s Encrypt',
    },
    {
        term: 'Comms',
        detail: 'Slack connected to GitHub, plus AI alerts and on-duty DMs',
    },
];

export const faq: { role: string; questions: { q: string; a: string }[] }[] = [
    {
        role: 'CEO',
        questions: [
            {
                q: 'What does this cost?',
                a: 'About $29 a month for hosting, plus AI usage, which the homepage widget shows live. Runs are capped individually and per day, so a bad day can’t run away.',
            },
            {
                q: 'Can the AI break production?',
                a: 'It can only ship through the Deploy Gate, which waits for a healthy staging soak, works business hours only and rolls back automatically if the new version doesn’t check out.',
            },
            {
                q: 'How do I know what it did?',
                a: 'Every change is a pull request with the agent’s session linked, the reviewer’s verdict and the gate’s decision. Releases come with plain-language notes in Slack.',
            },
            {
                q: 'How do we stop it?',
                a: 'AI · pause and resume in GitHub Actions, which works from a phone, stops the AI merging and deploying, so people decide everything that lands. Setting AI_AGENTS to off stops the agents altogether.',
            },
        ],
    },
    {
        role: 'Developer',
        questions: [
            {
                q: 'Will it merge things I haven’t seen?',
                a: 'Only small, reviewed, low-risk changes from developers’ own issues, with no migrations, dependencies or protected files. Add ai:no-automerge to anything you want to merge yourself.',
            },
            {
                q: 'Can I work on an AI branch?',
                a: 'Yes. Check it out and push. Comment /ai stop first if an agent is still working on it.',
            },
            {
                q: 'Where are the prompts?',
                a: 'In agents/*/agent.md, shown in the Agents section here. Changing them is a normal pull request.',
            },
            {
                q: 'What if CI keeps failing?',
                a: 'The debugger tries twice, then labels the pull request ai:needs-human so a developer picks it up.',
            },
        ],
    },
    {
        role: 'Designer',
        questions: [
            {
                q: 'How do I ask for a UI change?',
                a: 'Open a Feature issue in GitHub, or use “Create issue” on a Slack message. Describe what you want to see; screenshots help.',
            },
            {
                q: 'Where do I see the result?',
                a: 'On staging, a few minutes after it merges. Your request stops for a developer’s review before merging unless they opt it in.',
            },
            {
                q: 'Does it follow our design system?',
                a: 'Agents read the ui-guidelines skill: the brand tokens, shadcn-vue components, light and dark mode, mobile first and accessibility. The reviewer checks UI quality too.',
            },
            {
                q: 'Can I change the colours myself?',
                a: 'The design tokens live at the top of resources/css/app.css. Ask for a change in an issue and point at the token.',
            },
        ],
    },
];
