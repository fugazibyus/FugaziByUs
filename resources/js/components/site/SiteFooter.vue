<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import { aiWorkflow, home } from '@/routes';
import { show as contact } from '@/routes/contact';

const year = new Date().getFullYear();
</script>

<template>
    <footer class="border-t border-border/60">
        <div
            class="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-10 sm:flex-row sm:items-center sm:justify-between sm:px-6"
        >
            <div class="space-y-2">
                <AppLogo />
                <p class="max-w-sm text-sm text-muted-foreground">
                    A demo of a complete AI-driven development lifecycle — from
                    idea to production.
                </p>
            </div>
            <nav
                class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-muted-foreground"
                aria-label="Footer"
            >
                <Link :href="home()" class="hover:text-foreground">Home</Link>
                <Link :href="aiWorkflow()" class="hover:text-foreground"
                    >AI Workflow</Link
                >
                <Link :href="contact()" class="hover:text-foreground"
                    >Contact</Link
                >
            </nav>
        </div>
        <p class="pb-8 text-center text-xs text-muted-foreground">
            © {{ year }} FugaziByUs · Built and shipped with Claude
        </p>
    </footer>
</template>
