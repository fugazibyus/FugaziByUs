<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { ref } from 'vue';
import { cn } from '@/lib/utils';
import { faq } from './content';

const role = ref(faq[0].role);
</script>

<template>
    <div
        class="mt-6 inline-flex rounded-lg bg-muted p-1"
        role="group"
        aria-label="Questions for"
    >
        <button
            v-for="group in faq"
            :key="group.role"
            type="button"
            :class="
                cn(
                    'rounded-md px-3 py-1.5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                    role === group.role &&
                        'bg-background text-foreground shadow-sm',
                )
            "
            :aria-pressed="role === group.role"
            @click="role = group.role"
        >
            {{ group.role }}
        </button>
    </div>

    <template v-for="group in faq" :key="group.role">
        <div v-if="role === group.role" class="mt-4 divide-y rounded-xl border">
            <details
                v-for="item in group.questions"
                :key="item.q"
                class="group px-4"
            >
                <summary
                    class="flex cursor-pointer list-none items-center justify-between gap-4 py-4 text-sm font-medium focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none [&::-webkit-details-marker]:hidden"
                >
                    {{ item.q }}
                    <ChevronDown
                        class="size-4 shrink-0 text-muted-foreground transition-transform group-open:rotate-180"
                        aria-hidden="true"
                    />
                </summary>
                <p class="pb-4 text-sm text-muted-foreground">{{ item.a }}</p>
            </details>
        </div>
    </template>
</template>
