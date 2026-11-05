<script setup lang="ts">
import { computed, onMounted } from 'vue';
import LoginForm from '@/components/auth/LoginForm.vue';
import RegisterForm from '@/components/auth/RegisterForm.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useAuthModal } from '@/composables/useAuthModal';

const { active, open, close, openFromUrl } = useAuthModal();

const isOpen = computed({
    get: () => active.value !== null,
    set: (value) => {
        if (!value) {
            close();
        }
    },
});

const copy = {
    login: {
        title: 'Welcome back',
        description: 'Log in to manage your account.',
    },
    register: {
        title: 'Create your account',
        description: 'Just an email and a strong password to get started.',
    },
};

onMounted(openFromUrl);
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="sm:max-w-md" data-test="auth-modal">
            <DialogHeader class="items-center text-center">
                <AppLogoIcon class="mb-2 size-10" />
                <DialogTitle class="text-xl">
                    {{ copy[active ?? 'login'].title }}
                </DialogTitle>
                <DialogDescription>
                    {{ copy[active ?? 'login'].description }}
                </DialogDescription>
            </DialogHeader>

            <LoginForm
                v-if="active === 'login'"
                @success="close"
                @switch="open('register')"
            />
            <RegisterForm
                v-else-if="active === 'register'"
                @success="close"
                @switch="open('login')"
            />
        </DialogContent>
    </Dialog>
</template>
