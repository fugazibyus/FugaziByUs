<script setup lang="ts">
import {
    Bot,
    CircleDollarSign,
    Coins,
    GitMerge,
    GitPullRequest,
    Timer,
} from '@lucide/vue';
import { computed } from 'vue';
import { Skeleton } from '@/components/ui/skeleton';
import type { AiStats } from '@/types';

const props = defineProps<{ stats?: AiStats }>();

const compact = new Intl.NumberFormat('en', { notation: 'compact' });
const money = new Intl.NumberFormat('en', {
    style: 'currency',
    currency: 'USD',
});

const tiles = computed(() => {
    const s = props.stats;

    if (!s) {
        return [];
    }

    const totalTokens = s.tokens.input + s.tokens.output + s.tokens.cached;

    return [
        {
            label: 'Spent on AI',
            value: money.format(s.spendUsd),
            hint: 'list price, all agents',
            icon: CircleDollarSign,
        },
        {
            label: 'Tokens',
            value: compact.format(totalTokens),
            hint: `${compact.format(s.tokens.cached)} served from cache`,
            icon: Coins,
        },
        {
            label: 'Agent runs',
            value: compact.format(s.runs.total),
            hint: s.runs.total
                ? `${Math.round((s.runs.succeeded / s.runs.total) * 100)}% succeeded`
                : 'none yet',
            icon: Bot,
        },
        {
            label: 'PRs opened by AI',
            value: compact.format(s.pullRequests.opened),
            hint: `${s.pullRequests.merged} merged`,
            icon: GitPullRequest,
        },
        {
            label: 'Auto-merged',
            value: compact.format(s.pullRequests.autoMerged),
            hint: 'via the Merge Gate',
            icon: GitMerge,
        },
        {
            label: 'Issue → PR',
            value:
                s.avgIssueToPrMinutes === null
                    ? '—'
                    : `${s.avgIssueToPrMinutes} min`,
            hint: 'average time',
            icon: Timer,
        },
    ];
});

const agents = computed(() =>
    Object.entries(props.stats?.runs.byAgent ?? {}).map(([name, count]) => ({
        name,
        count,
    })),
);
const maxAgentRuns = computed(() =>
    Math.max(1, ...agents.value.map((a) => a.count)),
);
</script>

<template>
    <section
        class="overflow-hidden rounded-2xl border bg-card shadow-sm"
        aria-labelledby="ai-stats-title"
        data-test="ai-stats-widget"
    >
        <header
            class="flex flex-wrap items-center justify-between gap-2 border-b px-6 py-4"
        >
            <div>
                <h2 id="ai-stats-title" class="font-semibold tracking-tight">
                    AI at work
                </h2>
                <p class="text-sm text-muted-foreground">
                    Live numbers from the agents building this site.
                </p>
            </div>
            <span
                class="inline-flex items-center gap-2 rounded-full bg-accent px-3 py-1 text-xs font-medium text-accent-foreground"
            >
                <span class="relative flex size-2">
                    <span
                        class="absolute inline-flex size-full animate-ping rounded-full bg-success opacity-60"
                    />
                    <span
                        class="relative inline-flex size-2 rounded-full bg-success"
                    />
                </span>
                Live
            </span>
        </header>

        <div
            v-if="!stats"
            class="grid grid-cols-2 gap-px bg-border sm:grid-cols-3"
        >
            <div v-for="n in 6" :key="n" class="space-y-3 bg-card p-5">
                <Skeleton class="h-4 w-24" />
                <Skeleton class="h-7 w-16" />
            </div>
        </div>

        <template v-else>
            <dl class="grid grid-cols-2 gap-px bg-border sm:grid-cols-3">
                <div
                    v-for="tile in tiles"
                    :key="tile.label"
                    class="bg-card p-5"
                >
                    <dt
                        class="flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        <component
                            :is="tile.icon"
                            class="size-4 text-primary"
                        />
                        {{ tile.label }}
                    </dt>
                    <dd class="mt-2 text-2xl font-semibold tabular-nums">
                        {{ tile.value }}
                    </dd>
                    <dd class="mt-1 text-xs text-muted-foreground">
                        {{ tile.hint }}
                    </dd>
                </div>
            </dl>

            <div class="grid gap-6 border-t p-6 md:grid-cols-2">
                <div>
                    <h3 class="mb-3 text-sm font-medium">Runs by agent</h3>
                    <ul class="space-y-2">
                        <li
                            v-for="agent in agents"
                            :key="agent.name"
                            class="grid grid-cols-[6rem_1fr_2rem] items-center gap-3 text-sm"
                        >
                            <span class="text-muted-foreground capitalize">{{
                                agent.name
                            }}</span>
                            <span
                                class="h-2 overflow-hidden rounded-full bg-muted"
                            >
                                <span
                                    class="block h-full rounded-full bg-primary transition-all"
                                    :style="{
                                        width: `${(agent.count / maxAgentRuns) * 100}%`,
                                    }"
                                />
                            </span>
                            <span class="text-right tabular-nums">{{
                                agent.count
                            }}</span>
                        </li>
                    </ul>
                </div>
                <div>
                    <h3 class="mb-3 text-sm font-medium">Latest runs</h3>
                    <ul v-if="stats.recentRuns.length" class="divide-y text-sm">
                        <li
                            v-for="run in stats.recentRuns"
                            :key="run.finishedAt + run.title"
                            class="flex items-center justify-between gap-3 py-2"
                        >
                            <span class="truncate">
                                <span class="font-medium capitalize">{{
                                    run.agent
                                }}</span>
                                · {{ run.title }}
                            </span>
                            <span
                                class="shrink-0 text-muted-foreground tabular-nums"
                                >{{ money.format(run.costUsd) }}</span
                            >
                        </li>
                    </ul>
                    <p
                        v-else
                        class="rounded-xl border border-dashed p-4 text-sm text-muted-foreground"
                    >
                        No agent runs yet. Numbers appear here as soon as the
                        first issue goes through the AI workflow.
                    </p>
                </div>
            </div>
        </template>
    </section>
</template>
