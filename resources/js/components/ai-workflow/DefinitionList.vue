<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { Row } from './content';

withDefaults(
    defineProps<{
        rows: Row[];
        /** Show terms as code (commands, branches, labels). */
        code?: boolean;
        /** Width of the term column from the `sm` breakpoint. */
        termClass?: string;
    }>(),
    { code: false, termClass: 'sm:grid-cols-[14rem_1fr]' },
);
</script>

<template>
    <dl class="divide-y">
        <div
            v-for="row in rows"
            :key="row.term"
            :class="cn('grid gap-1 py-3', termClass)"
        >
            <dt
                :class="
                    code
                        ? 'font-mono text-sm break-words'
                        : 'text-sm font-medium'
                "
            >
                {{ row.term }}
            </dt>
            <dd class="text-sm text-muted-foreground">{{ row.detail }}</dd>
        </div>
    </dl>
</template>
