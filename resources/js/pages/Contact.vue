<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { ChevronDown, Clock, Mail, MessageSquareText } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { aiWorkflow } from '@/routes';
import { store } from '@/routes/contact';

const faqs = [
    {
        question: 'What is FugaziByUs?',
        answer: 'A demo site that shows a complete AI-driven development lifecycle, from a request coming in to the change going live.',
    },
    {
        question: 'Is the AI really building this site?',
        answer: 'Yes, together with a human team. AI agents triage requests, write the code and tests, and review the changes. Automated checks and, where needed, developers decide what gets merged and released.',
        link: { label: 'See how the AI workflow works', href: aiWorkflow() },
    },
    {
        question: 'Who reads my message?',
        answer: 'Real people on the FugaziByUs team. We read every message and usually reply within one working day.',
    },
    {
        question: 'What do you do with my email address?',
        answer: "We only use it to reply to you. You won't be added to any mailing list.",
    },
];

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
                <h1 class="text-4xl font-bold tracking-tight">
                    Get in touch with us
                </h1>
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
                            <Label for="name" class="gap-1"
                                >Your name <RequiredMark
                            /></Label>
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
                            <Label for="email" class="gap-1"
                                >Email address <RequiredMark
                            /></Label>
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
                        <Label for="subject" class="gap-1"
                            >Subject <RequiredMark
                        /></Label>
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
                        <Label for="message" class="gap-1"
                            >Message <RequiredMark
                        /></Label>
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

                    <div class="flex flex-wrap items-center gap-4">
                        <p
                            class="text-sm text-muted-foreground"
                            data-test="required-fields-note"
                        >
                            <RequiredMark /> Required fields
                        </p>
                        <Button
                            type="submit"
                            size="lg"
                            class="ml-auto"
                            :disabled="processing"
                            data-test="contact-submit"
                        >
                            <Spinner v-if="processing" />
                            Send message
                        </Button>
                    </div>
                </Form>
            </div>

            <section
                class="lg:col-span-5"
                aria-labelledby="faq-heading"
                data-test="contact-faq"
            >
                <h2
                    id="faq-heading"
                    class="text-2xl font-bold tracking-tight sm:text-3xl"
                >
                    Frequently asked questions
                </h2>
                <div class="mt-6 divide-y rounded-2xl border bg-card shadow-sm">
                    <Collapsible
                        v-for="faq in faqs"
                        :key="faq.question"
                        v-slot="{ open }"
                    >
                        <h3>
                            <CollapsibleTrigger
                                class="flex w-full items-center justify-between gap-4 rounded-2xl px-6 py-4 text-left font-medium transition-colors outline-none hover:text-primary focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                {{ faq.question }}
                                <ChevronDown
                                    :class="[
                                        'size-4 shrink-0 text-muted-foreground transition-transform',
                                        open && 'rotate-180',
                                    ]"
                                    aria-hidden="true"
                                />
                            </CollapsibleTrigger>
                        </h3>
                        <CollapsibleContent
                            class="px-6 pb-4 text-sm text-muted-foreground"
                        >
                            <p>{{ faq.answer }}</p>
                            <Link
                                v-if="faq.link"
                                :href="faq.link.href"
                                class="mt-2 inline-block font-medium text-primary underline-offset-4 hover:underline"
                            >
                                {{ faq.link.label }}
                            </Link>
                        </CollapsibleContent>
                    </Collapsible>
                </div>
            </section>
        </div>
    </section>
</template>
