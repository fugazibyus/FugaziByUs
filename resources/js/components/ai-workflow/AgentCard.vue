<script setup lang="ts">
import { ChevronDown, Globe } from '@lucide/vue';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import type { AgentSummary } from '@/types';
import { agentTriggers } from './content';

const props = defineProps<{ agent: AgentSummary }>();

const open = ref(false);
const title = props.agent.name.replace(/^FugaziByUs\s+/, '');
const budget = new Intl.NumberFormat('en', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 0,
}).format(props.agent.budgetUsd);
</script>

<template>
    <article
        class="rounded-xl border p-4 sm:p-5"
        :data-test="`agent-${agent.key}`"
    >
        <div class="flex flex-wrap items-start justify-between gap-2">
            <h3 class="text-lg font-semibold">{{ title }}</h3>
            <div class="flex flex-wrap gap-1.5">
                <Badge variant="secondary">{{ agent.model }}</Badge>
                <Badge variant="outline">{{ agent.effort }} effort</Badge>
                <Badge variant="outline">{{ budget }} per run</Badge>
                <Badge v-if="agent.usesWeb" variant="outline">
                    <Globe aria-hidden="true" /> Web
                </Badge>
            </div>
        </div>
        <p class="mt-2 text-sm">{{ agent.description }}</p>
        <p
            v-if="agentTriggers[agent.key]"
            class="mt-1 text-sm text-muted-foreground"
        >
            <span class="font-medium text-foreground">Starts when:</span>
            {{ agentTriggers[agent.key] }}
        </p>

        <Collapsible v-model:open="open" class="mt-4">
            <CollapsibleTrigger
                class="inline-flex items-center gap-1.5 rounded-md text-sm font-medium text-primary hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            >
                <ChevronDown
                    :class="[
                        'size-4 transition-transform',
                        open && 'rotate-180',
                    ]"
                    aria-hidden="true"
                />
                {{ open ? 'Hide' : 'Read' }} the prompt
            </CollapsibleTrigger>
            <CollapsibleContent>
                <p class="mt-3 text-xs text-muted-foreground">
                    Exactly what the agent reads, from
                    <code class="font-mono">{{ agent.path }}</code
                    >.
                </p>
                <pre
                    class="mt-2 max-h-[28rem] overflow-auto rounded-lg bg-muted p-4 font-mono text-xs leading-relaxed whitespace-pre-wrap"
                    tabindex="0"
                    :aria-label="`${title} prompt`"
                    >{{ agent.prompt }}</pre>
            </CollapsibleContent>
        </Collapsible>
    </article>
</template>
