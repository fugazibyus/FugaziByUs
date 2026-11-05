<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

const emit = defineEmits<{ success: []; switch: [] }>();
</script>

<template>
    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        :reset-on-error="['password']"
        :options="{ preserveScroll: true }"
        class="grid gap-5"
        v-slot="{ errors, processing }"
        @success="emit('success')"
    >
        <div class="grid gap-2">
            <Label for="login-email">Email address</Label>
            <Input
                id="login-email"
                type="email"
                name="email"
                required
                v-focus
                autocomplete="email"
                placeholder="you@example.com"
            />
            <InputError :message="errors.email" />
        </div>

        <div class="grid gap-2">
            <div class="flex items-center justify-between">
                <Label for="login-password">Password</Label>
                <TextLink :href="request()" class="text-xs" :tabindex="-1">
                    Forgot password?
                </TextLink>
            </div>
            <PasswordInput
                id="login-password"
                name="password"
                required
                autocomplete="current-password"
                placeholder="Your password"
            />
            <InputError :message="errors.password" />
        </div>

        <Label for="login-remember" class="flex items-center gap-3 font-normal">
            <Checkbox id="login-remember" name="remember" />
            Remember me
        </Label>

        <Button
            type="submit"
            class="w-full"
            :disabled="processing"
            data-test="login-button"
        >
            <Spinner v-if="processing" />
            Log in
        </Button>

        <p class="text-center text-sm text-muted-foreground">
            New here?
            <button
                type="button"
                class="font-medium text-primary underline-offset-4 hover:underline"
                @click="emit('switch')"
            >
                Create an account
            </button>
        </p>
    </Form>
</template>
