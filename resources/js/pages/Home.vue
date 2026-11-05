<script setup lang="ts">
import { Deferred, Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Bot,
    GitPullRequestArrow,
    MessageSquareText,
    Rocket,
    ShieldCheck,
    Sparkles,
    Workflow,
} from '@lucide/vue';
import { computed } from 'vue';
import AiStatsWidget from '@/components/home/AiStatsWidget.vue';
import { Button } from '@/components/ui/button';
import { useAuthModal } from '@/composables/useAuthModal';
import { aiWorkflow } from '@/routes';
import type { AiStats } from '@/types';

defineProps<{ aiStats?: AiStats }>();

const user = computed(() => usePage().props.auth.user);
const { open } = useAuthModal();

const steps = [
    {
        icon: MessageSquareText,
        title: 'Ask',
        text: 'Anyone reports a bug, idea or research question — in GitHub or straight from Slack.',
    },
    {
        icon: Bot,
        title: 'Build',
        text: 'AI agents triage it, write the code and tests, and open a pull request.',
    },
    {
        icon: ShieldCheck,
        title: 'Check',
        text: 'Automated tests and an AI reviewer check every change. Policy gates decide if a human is needed.',
    },
    {
        icon: Rocket,
        title: 'Ship',
        text: 'Approved changes deploy to staging, then production — with automatic rollback.',
    },
];

const features = [
    {
        icon: Workflow,
        title: 'Six specialist agents',
        text: 'Triage, research, develop, debug, review and release — each with an editable, version-controlled prompt.',
    },
    {
        icon: GitPullRequestArrow,
        title: 'Humans and AI, side by side',
        text: 'Branch names decide who works on what. Developers can take over any AI branch at any time.',
    },
    {
        icon: ShieldCheck,
        title: 'Guardrails built in',
        text: 'Spending caps, protected files, merge and deploy gates, and a one-click kill switch.',
    },
    {
        icon: Sparkles,
        title: 'Same agents, everywhere',
        text: 'Run the exact same agents in the cloud, from Slack, or on a developer’s laptop.',
    },
];
</script>

<template>
    <Head title="AI-built, human-approved" />

    <!-- Hero -->
    <section class="relative overflow-hidden bg-hero-glow">
        <div
            class="mx-auto max-w-6xl px-4 pt-20 pb-16 text-center sm:px-6 sm:pt-28"
        >
            <span
                class="inline-flex items-center gap-2 rounded-full border bg-background/70 px-3 py-1 text-xs font-medium text-muted-foreground"
            >
                <Sparkles class="size-3.5 text-primary" />
                Idea → production, end to end with AI
            </span>
            <h1
                class="mx-auto mt-6 max-w-3xl text-4xl font-bold tracking-tight text-balance sm:text-6xl"
            >
                Software that
                <span
                    class="bg-gradient-to-r from-violet-600 to-indigo-500 bg-clip-text text-transparent dark:from-violet-400 dark:to-indigo-300"
                    >builds itself</span
                >, with humans in charge.
            </h1>
            <p
                class="mx-auto mt-6 max-w-2xl text-lg text-pretty text-muted-foreground"
            >
                FugaziByUs is a live demo of a complete AI development workflow.
                Every feature on this site went from request to production
                through AI agents, tests and policy gates.
            </p>
            <div class="mt-10 flex flex-wrap items-center justify-center gap-3">
                <Button size="lg" as-child>
                    <Link :href="aiWorkflow()">
                        See how it works <ArrowRight class="size-4" />
                    </Link>
                </Button>
                <Button
                    v-if="!user"
                    size="lg"
                    variant="outline"
                    @click="open('register')"
                >
                    Create an account
                </Button>
            </div>
        </div>
    </section>

    <!-- Live AI stats -->
    <section class="mx-auto max-w-6xl px-4 sm:px-6">
        <Deferred data="aiStats">
            <template #fallback>
                <AiStatsWidget />
            </template>
            <AiStatsWidget :stats="aiStats" />
        </Deferred>
    </section>

    <!-- How it works -->
    <section class="mx-auto max-w-6xl px-4 py-24 sm:px-6">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="text-3xl font-bold tracking-tight">How it works</h2>
            <p class="mt-3 text-muted-foreground">
                Four steps, from a one-line request to a shipped feature.
            </p>
        </div>
        <ol class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <li
                v-for="(step, index) in steps"
                :key="step.title"
                class="relative rounded-2xl border bg-card p-6"
            >
                <span
                    class="absolute top-6 right-6 text-sm font-semibold text-muted-foreground/60 tabular-nums"
                    >0{{ index + 1 }}</span
                >
                <span
                    class="inline-flex size-10 items-center justify-center rounded-xl bg-accent text-accent-foreground"
                >
                    <component :is="step.icon" class="size-5" />
                </span>
                <h3 class="mt-4 font-semibold">{{ step.title }}</h3>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{ step.text }}
                </p>
            </li>
        </ol>
    </section>

    <!-- Features -->
    <section class="border-y bg-muted/40">
        <div
            class="mx-auto grid max-w-6xl gap-12 px-4 py-24 sm:px-6 lg:grid-cols-3"
        >
            <div>
                <h2 class="text-3xl font-bold tracking-tight">
                    Built for the whole team
                </h2>
                <p class="mt-3 text-muted-foreground">
                    Leaders see cost and control. Developers get a faster loop.
                    Designers see ideas become real UI in hours, not sprints.
                </p>
            </div>
            <div class="grid gap-6 sm:grid-cols-2 lg:col-span-2">
                <div
                    v-for="feature in features"
                    :key="feature.title"
                    class="rounded-2xl border bg-card p-6"
                >
                    <component :is="feature.icon" class="size-5 text-primary" />
                    <h3 class="mt-4 font-semibold">{{ feature.title }}</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ feature.text }}
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="mx-auto max-w-6xl px-4 py-24 text-center sm:px-6">
        <h2 class="text-3xl font-bold tracking-tight">
            Curious how the agents work?
        </h2>
        <p class="mx-auto mt-3 max-w-xl text-muted-foreground">
            The AI Workflow page explains the agents, commands, guardrails and
            how to work with them — whatever your role.
        </p>
        <Button size="lg" class="mt-8" as-child>
            <Link :href="aiWorkflow()">
                Explore the AI workflow <ArrowRight class="size-4" />
            </Link>
        </Button>
    </section>
</template>
