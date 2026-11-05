<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    BookOpen,
    Bot,
    GitBranch,
    Layers,
    ShieldCheck,
    Tag,
    Terminal,
    Users,
} from '@lucide/vue';
import { ref } from 'vue';
import { cn } from '@/lib/utils';

/*
 * First version of the AI Workflow handbook. Phase 9 expands every section, renders
 * the agent prompts live from /agents/*.md and adds the workflow diagram.
 */
const sections = [
    { id: 'overview', title: 'Overview', icon: BookOpen },
    { id: 'agents', title: 'Agents', icon: Bot },
    { id: 'commands', title: 'Commands', icon: Terminal },
    { id: 'guardrails', title: 'Guardrails', icon: ShieldCheck },
    { id: 'branching', title: 'Branching', icon: GitBranch },
    { id: 'labels', title: 'Labels', icon: Tag },
    { id: 'stack', title: 'Stack', icon: Layers },
    { id: 'roles', title: 'By role', icon: Users },
] as const;

type SectionId = (typeof sections)[number]['id'];
const active = ref<SectionId>('overview');

const agents = [
    [
        'Triage',
        'Labels, sizes and routes every new issue; asks for missing details.',
    ],
    [
        'Researcher',
        'Investigates questions and writes up findings with sources.',
    ],
    [
        'Developer',
        'Implements features and fixes on an ai/* branch, with tests, and opens a PR.',
    ],
    [
        'Debugger',
        'Fixes failing CI on AI branches (two attempts, then hands to a human).',
    ],
    [
        'Reviewer',
        'Reviews every AI PR and gives a verdict the Merge Gate can use.',
    ],
    [
        'Release',
        'Prepares releases, checks staging and production, and posts summaries.',
    ],
];

const commands = [
    [
        'GitHub comment',
        '/ai triage · /ai develop · /ai fix · /ai review · /ai research · /ai merge · /ai release · /ai deploy · /ai stop',
    ],
    [
        'Slack',
        '/fugazi bug · /fugazi feature · /fugazi research · /fugazi status · /fugazi release · /fugazi deploy · /fugazi pause',
    ],
    [
        'Claude Code (local)',
        '/triage · /develop · /review · /debug · /research · /release',
    ],
    [
        'Terminal',
        'make ai-run agent=developer issue=12 · make deploy env=staging',
    ],
];

const guardrails = [
    'AI merges only through the Merge Gate — deterministic policy checks, not a prompt.',
    'Production deploys only through the Deploy Gate, with automatic rollback.',
    'Issues from non-developers stop for human review unless a developer opts them in.',
    'Workflows, agent prompts, infrastructure and dependencies are protected paths.',
    'Every agent session has a hard dollar budget, plus a daily spending cap.',
    'Secrets never enter the agent sandbox; agents have no production access.',
    'One switch (/fugazi pause) returns everything to humans-only.',
];

const branches = [
    ['main', 'Production. Only release PRs and hotfixes.'],
    ['develop', 'Staging. Features and fixes merge here.'],
    ['feature/<issue>-slug, fix/…, chore/…', 'Human branches.'],
    [
        'ai/feature/<issue>-slug, ai/fix/…',
        'AI branches — auto-reviewed, auto-fixed, can auto-merge.',
    ],
    ['hotfix/<slug>', 'Urgent production fixes from main.'],
];

const labels = [
    ['type:*', 'bug · feature · research · chore'],
    ['flow:ai / flow:human', 'Who handles the issue'],
    ['ai:go · ai:stop', 'Start or stop the AI on an issue'],
    [
        'ai:automerge · ai:no-automerge',
        'Let the AI merge, or force a human merge',
    ],
    ['deploy:hold', 'Block automatic production deploys'],
    ['ai:needs-human', 'The AI handed this back to a developer'],
];

const stack = [
    [
        'App',
        'Laravel 13 · PHP 8.4 · Inertia 3 · Vue 3 · TypeScript · Tailwind 4',
    ],
    ['Data', 'MySQL 8.4'],
    ['Runtime', 'Docker: app, nginx, db (+ queue, scheduler)'],
    ['AI', 'Claude Managed Agents · Claude Code · Anthropic CLI'],
    [
        'Delivery',
        'GitHub + Actions · DigitalOcean Droplets + Container Registry',
    ],
    ['Comms', 'Slack app: intake, alerts and DMs'],
];

const roles = [
    [
        'CEO',
        'Watch the live numbers on the homepage: spend, runs and PRs. Every change is traceable, budgets are capped, and one switch pauses the AI.',
    ],
    [
        'Developer',
        'Use the same agents locally in Claude Code, or call them from GitHub comments. Take over any ai/* branch by pushing to it.',
    ],
    [
        'Designer',
        'Describe a UI change in Slack or a GitHub issue. The AI builds it on staging, ready for you to review.',
    ],
];
</script>

<template>
    <Head title="AI Workflow" />

    <section class="bg-hero-glow">
        <div class="mx-auto max-w-6xl px-4 pt-16 pb-10 sm:px-6">
            <h1 class="text-4xl font-bold tracking-tight">The AI workflow</h1>
            <p class="mt-4 max-w-2xl text-lg text-muted-foreground">
                Everything you need to know about how FugaziByUs is built: the
                agents, the commands, the guardrails, and how to work with them.
            </p>
        </div>
    </section>

    <div
        class="mx-auto grid max-w-6xl gap-8 px-4 pb-24 sm:px-6 lg:grid-cols-[14rem_1fr]"
    >
        <nav
            class="flex gap-1 overflow-x-auto pb-2 lg:sticky lg:top-24 lg:flex-col lg:self-start"
            aria-label="Handbook sections"
        >
            <button
                v-for="section in sections"
                :key="section.id"
                type="button"
                :class="
                    cn(
                        'flex shrink-0 items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground',
                        active === section.id &&
                            'bg-accent text-accent-foreground',
                    )
                "
                :aria-current="active === section.id ? 'page' : undefined"
                @click="active = section.id"
            >
                <component :is="section.icon" class="size-4" />
                {{ section.title }}
            </button>
        </nav>

        <article class="min-w-0 rounded-2xl border bg-card p-6 sm:p-8">
            <template v-if="active === 'overview'">
                <h2 class="text-2xl font-semibold">Overview</h2>
                <p class="mt-3 text-muted-foreground">
                    Requests come in from GitHub or Slack. Specialist AI agents
                    triage them, build them, test them and review them. Policy
                    gates decide whether the AI can merge and deploy on its own,
                    or whether a developer needs to approve. Every step is
                    logged, budgeted and reported in Slack.
                </p>
                <ol class="mt-6 grid gap-3 sm:grid-cols-2">
                    <li
                        v-for="(step, i) in [
                            'Report',
                            'Triage',
                            'Develop',
                            'Test & review',
                            'Merge Gate',
                            'Staging',
                            'Deploy Gate',
                            'Production',
                        ]"
                        :key="step"
                        class="flex items-center gap-3 rounded-xl border px-4 py-3 text-sm"
                    >
                        <span
                            class="inline-flex size-7 items-center justify-center rounded-full bg-accent text-xs font-semibold text-accent-foreground"
                            >{{ i + 1 }}</span
                        >
                        {{ step }}
                    </li>
                </ol>
            </template>

            <template v-else-if="active === 'agents'">
                <h2 class="text-2xl font-semibold">Agents</h2>
                <p class="mt-3 text-muted-foreground">
                    Each agent's prompt is a file in the repository. Changing it
                    goes through a pull request, like any other code.
                </p>
                <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div
                        v-for="[name, text] in agents"
                        :key="name"
                        class="rounded-xl border p-4"
                    >
                        <dt class="font-medium">{{ name }}</dt>
                        <dd class="mt-1 text-sm text-muted-foreground">
                            {{ text }}
                        </dd>
                    </div>
                </dl>
            </template>

            <template v-else-if="active === 'commands'">
                <h2 class="text-2xl font-semibold">Commands</h2>
                <dl class="mt-6 divide-y">
                    <div
                        v-for="[where, list] in commands"
                        :key="where"
                        class="grid gap-1 py-4 sm:grid-cols-[12rem_1fr]"
                    >
                        <dt class="font-medium">{{ where }}</dt>
                        <dd class="font-mono text-sm text-muted-foreground">
                            {{ list }}
                        </dd>
                    </div>
                </dl>
            </template>

            <template v-else-if="active === 'guardrails'">
                <h2 class="text-2xl font-semibold">Guardrails</h2>
                <ul class="mt-6 space-y-3">
                    <li
                        v-for="rule in guardrails"
                        :key="rule"
                        class="flex gap-3 text-sm"
                    >
                        <ShieldCheck
                            class="mt-0.5 size-4 shrink-0 text-primary"
                        />
                        {{ rule }}
                    </li>
                </ul>
            </template>

            <template v-else-if="active === 'branching'">
                <h2 class="text-2xl font-semibold">Branching</h2>
                <p class="mt-3 text-muted-foreground">
                    The branch name tells the automation whether a human or the
                    AI owns the work.
                </p>
                <dl class="mt-6 divide-y">
                    <div
                        v-for="[name, text] in branches"
                        :key="name"
                        class="grid gap-1 py-4 sm:grid-cols-[18rem_1fr]"
                    >
                        <dt class="font-mono text-sm">{{ name }}</dt>
                        <dd class="text-sm text-muted-foreground">
                            {{ text }}
                        </dd>
                    </div>
                </dl>
            </template>

            <template v-else-if="active === 'labels'">
                <h2 class="text-2xl font-semibold">Labels</h2>
                <dl class="mt-6 divide-y">
                    <div
                        v-for="[name, text] in labels"
                        :key="name"
                        class="grid gap-1 py-4 sm:grid-cols-[16rem_1fr]"
                    >
                        <dt class="font-mono text-sm">{{ name }}</dt>
                        <dd class="text-sm text-muted-foreground">
                            {{ text }}
                        </dd>
                    </div>
                </dl>
            </template>

            <template v-else-if="active === 'stack'">
                <h2 class="text-2xl font-semibold">Stack</h2>
                <dl class="mt-6 divide-y">
                    <div
                        v-for="[name, text] in stack"
                        :key="name"
                        class="grid gap-1 py-4 sm:grid-cols-[10rem_1fr]"
                    >
                        <dt class="font-medium">{{ name }}</dt>
                        <dd class="text-sm text-muted-foreground">
                            {{ text }}
                        </dd>
                    </div>
                </dl>
            </template>

            <template v-else>
                <h2 class="text-2xl font-semibold">How to work with it</h2>
                <div class="mt-6 grid gap-4">
                    <div
                        v-for="[role, text] in roles"
                        :key="role"
                        class="rounded-xl border p-4"
                    >
                        <h3 class="font-medium">{{ role }}</h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ text }}
                        </p>
                    </div>
                </div>
            </template>
        </article>
    </div>
</template>
