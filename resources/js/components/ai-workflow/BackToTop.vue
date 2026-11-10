<script setup lang="ts">
import { ArrowUp } from '@lucide/vue';
import {
    usePreferredReducedMotion,
    useWindowScroll,
    useWindowSize,
} from '@vueuse/core';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';

// Where keyboard and screen reader focus lands once we're back at the top.
const props = defineProps<{ focusTarget: HTMLElement | null }>();

// VueUse removes its scroll and resize listeners when the component unmounts.
const { y } = useWindowScroll({ throttle: 100 });
const { height } = useWindowSize();
const motion = usePreferredReducedMotion();

const visible = computed(() => y.value > height.value);

function backToTop(): void {
    window.scrollTo({
        top: 0,
        behavior: motion.value === 'reduce' ? 'instant' : 'smooth',
    });
    props.focusTarget?.focus({ preventScroll: true });
}
</script>

<template>
    <!-- Sticky rather than fixed: at the end of the page the button rests in the
         space above the footer instead of covering it. -->
    <div class="sticky bottom-0 z-30 h-0">
        <Transition
            enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
            enter-from-class="translate-y-2 opacity-0"
            leave-active-class="transition duration-150 ease-in motion-reduce:transition-none"
            leave-to-class="translate-y-2 opacity-0"
        >
            <Button
                v-if="visible"
                variant="outline"
                size="icon-lg"
                class="absolute right-4 bottom-4 rounded-full bg-background/80 shadow-lg backdrop-blur-lg sm:right-6 sm:bottom-6 dark:bg-background/80 dark:hover:bg-accent"
                aria-label="Back to top"
                data-test="back-to-top"
                @click="backToTop"
            >
                <ArrowUp class="size-5" aria-hidden="true" />
            </Button>
        </Transition>
    </div>
</template>
