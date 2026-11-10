<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import PasswordStrength from '@/components/auth/PasswordStrength.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/register';
import { ref } from 'vue';

const emit = defineEmits<{ success: []; switch: [] }>();
const password = ref('');
</script>

<template>
    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        :options="{ preserveScroll: true }"
        class="grid gap-5"
        v-slot="{ errors, processing }"
        @success="emit('success')"
    >
        <div class="grid gap-2">
            <Label for="register-email">Email address</Label>
            <Input
                id="register-email"
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
            <Label for="register-password">Password</Label>
            <PasswordInput
                id="register-password"
                v-model="password"
                name="password"
                required
                autocomplete="new-password"
                placeholder="At least 12 characters"
            />
            <PasswordStrength :password="password" />
            <InputError :message="errors.password" />
        </div>

        <div class="grid gap-2">
            <Label for="register-password-confirmation">Confirm password</Label>
            <PasswordInput
                id="register-password-confirmation"
                name="password_confirmation"
                required
                autocomplete="new-password"
                placeholder="Type it again"
            />
            <InputError :message="errors.password_confirmation" />
        </div>

        <Button
            type="submit"
            class="w-full"
            :disabled="processing"
            data-test="register-user-button"
        >
            <Spinner v-if="processing" />
            Create your account
        </Button>

        <p class="text-center text-sm text-muted-foreground">
            Already have an account?
            <button
                type="button"
                class="font-medium text-primary underline-offset-4 hover:underline"
                @click="emit('switch')"
            >
                Log in
            </button>
        </p>
    </Form>
</template>
