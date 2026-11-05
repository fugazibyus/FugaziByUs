<script setup lang="ts">
import { Toaster } from '@/components/ui/sonner';
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';

const { title = '', description = '' } = defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <AuthLayout :title="title" :description="description">
        <slot />
    </AuthLayout>
    <Toaster position="top-right" rich-colors close-button />
</template>
