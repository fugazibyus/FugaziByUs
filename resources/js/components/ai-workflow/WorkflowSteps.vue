<script setup lang="ts">
import { Bot, Check, Cog, ShieldCheck, User, X } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import type { Actor } from './content';
import { actorLabels, workflowSteps } from './content';

const actorIcons = { person: User, ai: Bot, gate: ShieldCheck, system: Cog };
</script>

<template>
    <ol class="relative mt-6 space-y-4">
        <li
            v-for="(step, i) in workflowSteps"
            :key="step.title"
            class="relative grid grid-cols-[2.25rem_1fr] gap-4"
        >
            <!-- The line joining one step to the next. -->
            <span
                v-if="i < workflowSteps.length - 1"
                class="absolute top-10 bottom-[-1rem] left-[1.125rem] w-px bg-border"
                aria-hidden="true"
            />
            <span
                :class="[
                    'relative inline-flex size-9 items-center justify-center rounded-full border text-sm font-semibold',
                    step.actor === 'gate'
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'bg-card',
                ]"
                aria-hidden="true"
            >
                {{ i + 1 }}
            </span>
            <div
                :class="[
                    'rounded-xl border p-4',
                    step.actor === 'gate' && 'border-primary/40 bg-primary/5',
                ]"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="font-medium">
                        <span class="sr-only">Step {{ i + 1 }}: </span
                        >{{ step.title }}
                    </h3>
                    <Badge variant="outline" class="text-muted-foreground">
                        <component
                            :is="actorIcons[step.actor as Actor]"
                            aria-hidden="true"
                        />
                        {{ actorLabels[step.actor as Actor] }}
                    </Badge>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ step.detail }}
                </p>
                <ul v-if="step.branch" class="mt-3 grid gap-2 sm:grid-cols-2">
                    <li
                        class="flex gap-2 rounded-lg bg-muted px-3 py-2 text-sm"
                    >
                        <Check
                            class="mt-0.5 size-4 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        {{ step.branch.yes }}
                    </li>
                    <li
                        class="flex gap-2 rounded-lg bg-muted px-3 py-2 text-sm"
                    >
                        <X
                            class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        {{ step.branch.no }}
                    </li>
                </ul>
            </div>
        </li>
    </ol>
</template>
