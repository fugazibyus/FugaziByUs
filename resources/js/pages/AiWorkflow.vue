<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    BookOpen,
    Bot,
    CircleHelp,
    Code,
    GitBranch,
    Layers,
    ShieldCheck,
    Tag,
    Terminal,
    Workflow,
} from '@lucide/vue';
import { nextTick, onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue';
import AgentCard from '@/components/ai-workflow/AgentCard.vue';
import {
    branches,
    commandGroups,
    developerGuide,
    gitCheatsheet,
    guardrails,
    labelGroups,
    overviewFacts,
    stack,
    switches,
} from '@/components/ai-workflow/content';
import DefinitionList from '@/components/ai-workflow/DefinitionList.vue';
import RoleFaq from '@/components/ai-workflow/RoleFaq.vue';
import WorkflowSteps from '@/components/ai-workflow/WorkflowSteps.vue';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import type { AgentSummary } from '@/types';

defineProps<{ agents: AgentSummary[] }>();

const sections = [
    { id: 'overview', title: 'Overview', icon: BookOpen },
    { id: 'workflow', title: 'Workflow', icon: Workflow },
    { id: 'agents', title: 'Agents', icon: Bot },
    { id: 'commands', title: 'Commands', icon: Terminal },
    { id: 'guardrails', title: 'Guardrails', icon: ShieldCheck },
    { id: 'branching', title: 'Branching & Git', icon: GitBranch },
    { id: 'labels', title: 'Labels', icon: Tag },
    { id: 'developers', title: 'Developer guide', icon: Code },
    { id: 'stack', title: 'Stack', icon: Layers },
    { id: 'faq', title: 'FAQ by role', icon: CircleHelp },
] as const;

type SectionId = (typeof sections)[number]['id'];

const active = ref<SectionId>('overview');
const heading = useTemplateRef<HTMLElement>('heading');
const nav = useTemplateRef<HTMLElement>('nav');

function isSection(id: string): id is SectionId {
    return sections.some((section) => section.id === id);
}

// Each section has its own link (/ai-workflow#agents), so it can be shared.
function followHash(): void {
    const hash = window.location.hash.slice(1);

    if (isSection(hash)) {
        active.value = hash;
        nextTick(revealActiveLink);
    }
}

// On phones the section list scrolls sideways: keep the chosen section in view.
function revealActiveLink(): void {
    const link = nav.value?.querySelector('[aria-current]');

    if (nav.value && link) {
        const offset =
            link.getBoundingClientRect().left -
            nav.value.getBoundingClientRect().left;
        nav.value.scrollLeft += offset - 16;
    }
}

onMounted(() => {
    followHash();
    window.addEventListener('hashchange', followHash);
});

onBeforeUnmount(() => window.removeEventListener('hashchange', followHash));

async function show(id: SectionId): Promise<void> {
    active.value = id;
    history.replaceState(history.state, '', `#${id}`);
    await nextTick();
    // Move keyboard and screen reader focus to the new section's heading, and bring it
    // into view if the reader had scrolled past it.
    heading.value?.focus({ preventScroll: true });

    if (heading.value && heading.value.getBoundingClientRect().top < 96) {
        heading.value.scrollIntoView({ block: 'start' });
    }
}

const activeTitle = (id: SectionId) =>
    sections.find((section) => section.id === id)?.title;
</script>

<template>
    <Head title="AI Workflow" />

    <section class="bg-hero-glow">
        <div class="mx-auto max-w-6xl px-4 pt-16 pb-10 sm:px-6">
            <h1 class="text-4xl font-bold tracking-tight">The AI workflow</h1>
            <p class="mt-4 max-w-2xl text-lg text-muted-foreground">
                How FugaziByUs gets built: who does what, the rules that keep it
                safe, and how to work with the agents, whatever your role.
            </p>
        </div>
    </section>

    <div
        class="mx-auto grid max-w-6xl gap-8 px-4 pb-24 sm:px-6 lg:grid-cols-[14rem_1fr]"
    >
        <nav
            ref="nav"
            class="-mx-4 flex gap-1 overflow-x-auto px-4 pb-2 sm:mx-0 sm:px-0 lg:sticky lg:top-24 lg:flex-col lg:self-start"
            aria-label="Handbook sections"
        >
            <a
                v-for="section in sections"
                :key="section.id"
                :href="`#${section.id}`"
                :class="
                    cn(
                        'flex shrink-0 items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                        active === section.id &&
                            'bg-accent text-accent-foreground',
                    )
                "
                :aria-current="active === section.id ? 'true' : undefined"
                :data-test="`section-${section.id}`"
                @click.prevent="show(section.id)"
            >
                <component
                    :is="section.icon"
                    class="size-4"
                    aria-hidden="true"
                />
                {{ section.title }}
            </a>
        </nav>

        <article class="min-w-0 rounded-2xl border bg-card p-5 sm:p-8">
            <h2
                ref="heading"
                tabindex="-1"
                class="scroll-mt-24 text-2xl font-bold tracking-tight focus:outline-none"
            >
                {{ activeTitle(active) }}
            </h2>

            <template v-if="active === 'overview'">
                <p class="mt-3 text-muted-foreground">
                    Requests come in from GitHub or Slack. Specialist AI agents
                    triage them, build them, test them and review them. Two
                    policy gates decide whether the AI can merge and deploy on
                    its own, or whether a developer steps in. Every step is
                    logged, budgeted and reported in Slack.
                </p>
                <dl class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div
                        v-for="fact in overviewFacts"
                        :key="fact.label"
                        class="flex flex-col-reverse rounded-xl border p-4"
                    >
                        <dt class="mt-1 text-sm text-muted-foreground">
                            {{ fact.label }}
                        </dt>
                        <dd class="text-3xl font-bold tracking-tight">
                            {{ fact.value }}
                        </dd>
                    </div>
                </dl>
                <p class="mt-6 text-sm text-muted-foreground">
                    Every page of this site went through this process. The live
                    spend, runs and pull requests are on the
                    <Link
                        :href="home()"
                        class="font-medium text-primary hover:underline"
                        >homepage</Link
                    >. Read on for the
                    <a
                        href="#workflow"
                        class="font-medium text-primary hover:underline"
                        @click.prevent="show('workflow')"
                        >step-by-step workflow</a
                    >, or jump to the
                    <a
                        href="#faq"
                        class="font-medium text-primary hover:underline"
                        @click.prevent="show('faq')"
                        >questions for your role</a
                    >.
                </p>
            </template>

            <template v-else-if="active === 'workflow'">
                <p class="mt-3 text-muted-foreground">
                    From a request to production. People can step in at any
                    point; the purple steps are the gates that decide when they
                    must.
                </p>
                <WorkflowSteps />
            </template>

            <template v-else-if="active === 'agents'">
                <p class="mt-3 text-muted-foreground">
                    Each agent is one file in the repository: settings at the
                    top, the prompt below. This list is read from those files,
                    so it always matches what runs. Changing an agent is a pull
                    request, like any other code.
                </p>
                <div class="mt-6 grid gap-4">
                    <AgentCard
                        v-for="agent in agents"
                        :key="agent.key"
                        :agent="agent"
                    />
                </div>
            </template>

            <template v-else-if="active === 'commands'">
                <section
                    v-for="group in commandGroups"
                    :key="group.title"
                    class="mt-8 first-of-type:mt-6"
                >
                    <h3 class="text-lg font-semibold">{{ group.title }}</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ group.intro }}
                    </p>
                    <DefinitionList
                        :rows="group.rows"
                        code
                        term-class="sm:grid-cols-[18rem_1fr]"
                        class="mt-2"
                    />
                </section>
            </template>

            <template v-else-if="active === 'guardrails'">
                <p class="mt-3 text-muted-foreground">
                    Why the AI can be trusted to merge and deploy, and how to
                    take that trust back.
                </p>
                <ul class="mt-6 grid gap-3 sm:grid-cols-2">
                    <li
                        v-for="rule in guardrails"
                        :key="rule.title"
                        class="flex gap-3 rounded-xl border p-4"
                    >
                        <ShieldCheck
                            class="mt-0.5 size-5 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        <div>
                            <h3 class="text-sm font-medium">
                                {{ rule.title }}
                            </h3>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ rule.detail }}
                            </p>
                        </div>
                    </li>
                </ul>
                <h3 class="mt-10 text-lg font-semibold">The switches</h3>
                <p class="mt-1 text-sm text-muted-foreground">
                    Repository variables in GitHub. AI · pause and resume in
                    Actions turns AI merging and production deploys off in one
                    go, from a phone if needed.
                </p>
                <DefinitionList
                    :rows="switches"
                    code
                    term-class="sm:grid-cols-[16rem_1fr]"
                    class="mt-2"
                />
            </template>

            <template v-else-if="active === 'branching'">
                <p class="mt-3 text-muted-foreground">
                    The branch name tells the automation whether a person or the
                    AI owns the work. Nobody pushes to main or develop directly.
                </p>
                <DefinitionList
                    :rows="branches"
                    code
                    term-class="sm:grid-cols-[16rem_1fr]"
                    class="mt-4"
                />
                <h3 class="mt-10 text-lg font-semibold">Git cheatsheet</h3>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div
                        v-for="recipe in gitCheatsheet"
                        :key="recipe.title"
                        class="rounded-xl border p-4"
                    >
                        <h4 class="text-sm font-medium">{{ recipe.title }}</h4>
                        <pre
                            class="mt-2 overflow-x-auto rounded-lg bg-muted p-3 font-mono text-xs leading-relaxed"
                        ><code>{{ recipe.commands.join('\n') }}</code></pre>
                    </div>
                </div>
                <p class="mt-4 text-sm text-muted-foreground">
                    Commit messages: a short imperative summary under 72
                    characters, a blank line, then why. One issue per branch,
                    one concern per pull request.
                </p>
            </template>

            <template v-else-if="active === 'labels'">
                <p class="mt-3 text-muted-foreground">
                    Labels show where an issue is and steer the AI. Most are set
                    automatically; the controls are for developers.
                </p>
                <section
                    v-for="group in labelGroups"
                    :key="group.title"
                    class="mt-8 first-of-type:mt-6"
                >
                    <h3 class="text-lg font-semibold">{{ group.title }}</h3>
                    <DefinitionList
                        :rows="group.rows"
                        code
                        term-class="sm:grid-cols-[18rem_1fr]"
                        class="mt-2"
                    />
                </section>
            </template>

            <template v-else-if="active === 'developers'">
                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <section
                        v-for="part in developerGuide"
                        :key="part.title"
                        class="rounded-xl border p-4"
                    >
                        <h3 class="font-medium">{{ part.title }}</h3>
                        <ul
                            class="mt-2 list-disc space-y-1.5 pl-5 text-sm text-muted-foreground"
                        >
                            <li v-for="item in part.items" :key="item">
                                {{ item }}
                            </li>
                        </ul>
                    </section>
                </div>
                <p class="mt-6 text-sm text-muted-foreground">
                    The house rules every agent and developer follows are in
                    <code class="font-mono">CLAUDE.md</code> at the root of the
                    repository.
                </p>
            </template>

            <template v-else-if="active === 'stack'">
                <DefinitionList :rows="stack" class="mt-4" />
            </template>

            <template v-else>
                <p class="mt-3 text-muted-foreground">
                    The questions people ask most, by role.
                </p>
                <RoleFaq />
            </template>
        </article>
    </div>
</template>
