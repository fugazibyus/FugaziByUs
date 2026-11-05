<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { KeyRound, LogOut, Mail, UserRound } from '@lucide/vue';
import { computed, ref } from 'vue';
import PasswordStrength from '@/components/auth/PasswordStrength.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import UserInfo from '@/components/UserInfo.vue';
import { logout } from '@/routes';
import { update as updateEmail } from '@/routes/account/email';
import { update as updatePassword } from '@/routes/account/password';
import { update as updateProfile } from '@/routes/account/profile';
import type { User } from '@/types';

defineProps<{ passwordRules: string }>();

const user = computed(() => usePage().props.auth.user as User);
const newPassword = ref('');
const memberSince = computed(() =>
    new Date(user.value.created_at).toLocaleDateString('en', {
        month: 'long',
        year: 'numeric',
    }),
);
</script>

<template>
    <Head title="My account" />

    <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <UserInfo :user="user" show-email />
            </div>
            <Button variant="outline" as-child>
                <Link
                    :href="logout()"
                    as="button"
                    data-test="account-logout"
                    @click="router.flushAll()"
                >
                    <LogOut class="size-4" /> Log out
                </Link>
            </Button>
        </div>
        <h1 class="mt-8 text-3xl font-bold tracking-tight">My account</h1>
        <p class="mt-1 text-muted-foreground">
            Member since {{ memberSince }}. Manage your details below.
        </p>

        <div class="mt-10 space-y-8">
            <!-- Profile -->
            <section class="rounded-2xl border bg-card p-6 sm:p-8">
                <header class="flex items-start gap-3">
                    <UserRound class="mt-0.5 size-5 text-primary" />
                    <div>
                        <h2 class="font-semibold">Your name</h2>
                        <p class="text-sm text-muted-foreground">
                            Optional — used to greet you around the site.
                        </p>
                    </div>
                </header>
                <Form
                    v-bind="updateProfile.form()"
                    :options="{ preserveScroll: true }"
                    class="mt-6 grid gap-5"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="first_name">First name</Label>
                            <Input
                                id="first_name"
                                name="first_name"
                                maxlength="100"
                                autocomplete="given-name"
                                :default-value="user.first_name ?? ''"
                            />
                            <InputError :message="errors.first_name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="last_name">Last name</Label>
                            <Input
                                id="last_name"
                                name="last_name"
                                maxlength="100"
                                autocomplete="family-name"
                                :default-value="user.last_name ?? ''"
                            />
                            <InputError :message="errors.last_name" />
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <Button :disabled="processing" data-test="save-profile">
                            <Spinner v-if="processing" /> Save name
                        </Button>
                    </div>
                </Form>
            </section>

            <!-- Email -->
            <section class="rounded-2xl border bg-card p-6 sm:p-8">
                <header class="flex items-start gap-3">
                    <Mail class="mt-0.5 size-5 text-primary" />
                    <div>
                        <h2 class="font-semibold">Email address</h2>
                        <p class="text-sm text-muted-foreground">
                            You log in with this. Confirm the change with your
                            current password.
                        </p>
                    </div>
                </header>
                <Form
                    v-bind="updateEmail.form()"
                    :options="{ preserveScroll: true }"
                    :reset-on-success="['current_password']"
                    :reset-on-error="['current_password']"
                    class="mt-6 grid gap-5"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="email">New email</Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                required
                                autocomplete="email"
                                :default-value="user.email"
                            />
                            <InputError :message="errors.email" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="email_current_password"
                                >Current password</Label
                            >
                            <PasswordInput
                                id="email_current_password"
                                name="current_password"
                                required
                                autocomplete="current-password"
                            />
                            <InputError :message="errors.current_password" />
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <Button :disabled="processing" data-test="save-email">
                            <Spinner v-if="processing" /> Update email
                        </Button>
                    </div>
                </Form>
            </section>

            <!-- Password -->
            <section class="rounded-2xl border bg-card p-6 sm:p-8">
                <header class="flex items-start gap-3">
                    <KeyRound class="mt-0.5 size-5 text-primary" />
                    <div>
                        <h2 class="font-semibold">Change password</h2>
                        <p class="text-sm text-muted-foreground">
                            Use a long password you don't use anywhere else.
                        </p>
                    </div>
                </header>
                <Form
                    v-bind="updatePassword.form()"
                    :options="{ preserveScroll: true }"
                    reset-on-success
                    :reset-on-error="[
                        'current_password',
                        'password',
                        'password_confirmation',
                    ]"
                    class="mt-6 grid gap-5"
                    v-slot="{ errors, processing }"
                    @success="newPassword = ''"
                >
                    <div class="grid gap-2 sm:max-w-sm">
                        <Label for="current_password">Current password</Label>
                        <PasswordInput
                            id="current_password"
                            name="current_password"
                            required
                            autocomplete="current-password"
                        />
                        <InputError :message="errors.current_password" />
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="password">New password</Label>
                            <PasswordInput
                                id="password"
                                v-model="newPassword"
                                name="password"
                                required
                                autocomplete="new-password"
                                :passwordrules="passwordRules"
                            />
                            <InputError :message="errors.password" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="password_confirmation"
                                >Confirm new password</Label
                            >
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                autocomplete="new-password"
                                :passwordrules="passwordRules"
                            />
                            <InputError
                                :message="errors.password_confirmation"
                            />
                        </div>
                    </div>
                    <PasswordStrength :password="newPassword" />
                    <div class="flex justify-end">
                        <Button
                            :disabled="processing"
                            data-test="save-password"
                        >
                            <Spinner v-if="processing" /> Change password
                        </Button>
                    </div>
                </Form>
            </section>
        </div>
    </div>
</template>
