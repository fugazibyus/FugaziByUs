<script setup lang="ts">
import { Check, Circle } from '@lucide/vue';
import { computed } from 'vue';

// Mirrors the server rules in AppServiceProvider (Password::defaults).
const props = defineProps<{ password: string }>();

const checks = computed(() => [
    { label: '12+ characters', ok: props.password.length >= 12 },
    {
        label: 'Upper & lower case',
        ok: /[a-z]/.test(props.password) && /[A-Z]/.test(props.password),
    },
    { label: 'A number', ok: /\d/.test(props.password) },
    { label: 'A symbol', ok: /[^A-Za-z0-9]/.test(props.password) },
]);
</script>

<template>
    <ul
        class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs"
        aria-label="Password requirements"
    >
        <li
            v-for="check in checks"
            :key="check.label"
            class="flex items-center gap-1.5 transition-colors"
            :class="
                check.ok
                    ? 'text-emerald-600 dark:text-emerald-400'
                    : 'text-muted-foreground'
            "
        >
            <Check v-if="check.ok" class="size-3.5" />
            <Circle v-else class="size-3.5" />
            {{ check.label }}
        </li>
    </ul>
</template>
