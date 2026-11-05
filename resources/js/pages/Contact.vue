<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Clock, Mail, MessageSquareText } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { store } from '@/routes/contact';

const user = computed(() => usePage().props.auth.user);
const knownName = computed(() =>
    user.value?.first_name
        ? [user.value.first_name, user.value.last_name]
              .filter(Boolean)
              .join(' ')
        : '',
);
</script>

<template>
    <Head title="Contact" />

    <section class="bg-hero-glow">
        <div
            class="mx-auto grid max-w-6xl gap-12 px-4 py-20 sm:px-6 lg:grid-cols-5"
        >
            <div class="lg:col-span-2">
                <h1 class="text-4xl font-bold tracking-tight">Get in touch</h1>
                <p class="mt-4 text-lg text-muted-foreground">
                    Questions about the demo, the AI workflow, or how it could
                    work for your team? Send us a message.
                </p>
                <ul class="mt-10 space-y-6 text-sm">
                    <li class="flex gap-4">
                        <span
                            class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-accent text-accent-foreground"
                            ><MessageSquareText class="size-5"
                        /></span>
                        <span>
                            <span class="block font-medium"
                                >Real people read this</span
                            >
                            <span class="text-muted-foreground"
                                >Messages go straight to the team's Slack.</span
                            >
                        </span>
                    </li>
                    <li class="flex gap-4">
                        <span
                            class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-accent text-accent-foreground"
                            ><Clock class="size-5"
                        /></span>
                        <span>
                            <span class="block font-medium">Quick replies</span>
                            <span class="text-muted-foreground"
                                >We usually answer within one working day.</span
                            >
                        </span>
                    </li>
                    <li class="flex gap-4">
                        <span
                            class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-accent text-accent-foreground"
                            ><Mail class="size-5"
                        /></span>
                        <span>
                            <span class="block font-medium"
                                >No mailing lists</span
                            >
                            <span class="text-muted-foreground"
                                >We only use your email to reply to you.</span
                            >
                        </span>
                    </li>
                </ul>
            </div>

            <div
                class="rounded-2xl border bg-card p-6 shadow-sm sm:p-8 lg:col-span-3"
            >
                <Form
                    v-bind="store.form()"
                    reset-on-success
                    :options="{ preserveScroll: true }"
                    class="grid gap-5"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="name">Your name</Label>
                            <Input
                                id="name"
                                name="name"
                                required
                                maxlength="100"
                                autocomplete="name"
                                :default-value="knownName"
                                placeholder="Jane Smith"
                            />
                            <InputError :message="errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="email">Email address</Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                required
                                autocomplete="email"
                                :default-value="user?.email ?? ''"
                                placeholder="you@example.com"
                            />
                            <InputError :message="errors.email" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="subject">Subject</Label>
                        <Input
                            id="subject"
                            name="subject"
                            required
                            maxlength="150"
                            placeholder="What's it about?"
                        />
                        <InputError :message="errors.subject" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="message">Message</Label>
                        <Textarea
                            id="message"
                            name="message"
                            required
                            rows="6"
                            maxlength="5000"
                            class="min-h-36"
                            placeholder="Tell us a bit more…"
                        />
                        <InputError :message="errors.message" />
                    </div>

                    <!-- Honeypot: invisible to people, tempting to bots. -->
                    <div class="hidden" aria-hidden="true">
                        <label for="website">Leave this empty</label>
                        <input
                            id="website"
                            name="website"
                            tabindex="-1"
                            autocomplete="off"
                        />
                    </div>
                    <InputError :message="errors.website" />

                    <div class="flex justify-end">
                        <Button
                            type="submit"
                            size="lg"
                            :disabled="processing"
                            data-test="contact-submit"
                        >
                            <Spinner v-if="processing" />
                            Send message
                        </Button>
                    </div>
                </Form>
            </div>
        </div>
    </section>
</template>
