<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/composables/useAppearance';
import type { Appearance } from '@/types';

const { appearance, updateAppearance } = useAppearance();

const order: Appearance[] = ['light', 'dark', 'system'];
const labels: Record<Appearance, string> = {
    light: 'Light theme',
    dark: 'Dark theme',
    system: 'System theme',
};

const next = computed(
    () => order[(order.indexOf(appearance.value) + 1) % order.length],
);
</script>

<template>
    <Button
        variant="ghost"
        size="icon"
        :aria-label="`${labels[appearance]} – switch to ${labels[next].toLowerCase()}`"
        :title="labels[appearance]"
        @click="updateAppearance(next)"
    >
        <Sun v-if="appearance === 'light'" class="size-4" />
        <Moon v-else-if="appearance === 'dark'" class="size-4" />
        <Monitor v-else class="size-4" />
    </Button>
</template>
