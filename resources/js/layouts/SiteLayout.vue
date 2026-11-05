<script setup lang="ts">
import AuthModals from '@/components/auth/AuthModals.vue';
import SiteFooter from '@/components/site/SiteFooter.vue';
import SiteHeader from '@/components/site/SiteHeader.vue';
import { Toaster } from '@/components/ui/sonner';
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <SiteHeader />
        <main class="flex-1">
            <slot />
        </main>
        <SiteFooter />
    </div>
    <AuthModals />
    <Toaster position="top-right" rich-colors close-button />
</template>
