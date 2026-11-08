<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { LogOut, Menu, UserRound } from '@lucide/vue';
import { computed, ref } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import ThemeToggle from '@/components/site/ThemeToggle.vue';
import UserMenu from '@/components/site/UserMenu.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useAuthModal } from '@/composables/useAuthModal';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { cn } from '@/lib/utils';
import { aiWorkflow, home, logout } from '@/routes';
import { show as account } from '@/routes/account';
import { show as contact } from '@/routes/contact';

const page = usePage();
const user = computed(() => page.props.auth.user);
const { open } = useAuthModal();
const { isCurrentUrl } = useCurrentUrl();
const mobileOpen = ref(false);

const links = [
    { title: 'Home', href: home() },
    { title: 'AI Workflow', href: aiWorkflow() },
    { title: 'Contact', href: contact() },
];

function openFromMobile(modal: 'login' | 'register'): void {
    mobileOpen.value = false;
    open(modal);
}
</script>

<template>
    <header
        class="sticky top-0 z-40 border-b border-border/60 bg-background/80 backdrop-blur-lg"
    >
        <div
            class="mx-auto flex h-16 max-w-6xl items-center gap-6 px-4 sm:px-6"
        >
            <Link :href="home()" class="shrink-0" aria-label="FugaziByUs home">
                <AppLogo />
            </Link>

            <nav class="hidden items-center gap-1 md:flex" aria-label="Main">
                <Link
                    v-for="link in links"
                    :key="link.title"
                    :href="link.href"
                    :class="
                        cn(
                            'rounded-lg px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground',
                            isCurrentUrl(link.href) &&
                                'bg-accent text-accent-foreground',
                        )
                    "
                >
                    {{ link.title }}
                </Link>
            </nav>

            <div class="ml-auto flex items-center gap-2">
                <ThemeToggle />

                <template v-if="user">
                    <UserMenu :user="user" />
                </template>
                <template v-else>
                    <Button
                        variant="ghost"
                        class="hidden sm:inline-flex"
                        data-test="open-login"
                        @click="open('login')"
                    >
                        Log in
                    </Button>
                    <Button
                        class="hidden sm:inline-flex"
                        data-test="open-register"
                        @click="open('register')"
                    >
                        Register
                    </Button>
                </template>

                <Sheet v-model:open="mobileOpen">
                    <SheetTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="md:hidden"
                            aria-label="Open menu"
                        >
                            <Menu class="size-5" />
                        </Button>
                    </SheetTrigger>
                    <SheetContent side="right" class="w-72 p-6">
                        <SheetHeader class="p-0">
                            <SheetTitle><AppLogo /></SheetTitle>
                        </SheetHeader>
                        <nav class="mt-6 grid gap-1" aria-label="Mobile">
                            <Link
                                v-for="link in links"
                                :key="link.title"
                                :href="link.href"
                                class="rounded-lg px-3 py-2 text-sm font-medium hover:bg-accent"
                                @click="mobileOpen = false"
                            >
                                {{ link.title }}
                            </Link>
                        </nav>
                        <div class="mt-6 grid gap-2 border-t pt-6">
                            <template v-if="user">
                                <Link
                                    :href="account()"
                                    class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium hover:bg-accent"
                                    @click="mobileOpen = false"
                                >
                                    <UserRound class="size-4" /> My account
                                </Link>
                                <Link
                                    :href="logout()"
                                    as="button"
                                    class="flex items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium hover:bg-accent"
                                    @click="router.flushAll()"
                                >
                                    <LogOut class="size-4" /> Log out
                                </Link>
                            </template>
                            <template v-else>
                                <Button
                                    variant="outline"
                                    @click="openFromMobile('login')"
                                    >Log in</Button
                                >
                                <Button @click="openFromMobile('register')"
                                    >Register</Button
                                >
                            </template>
                        </div>
                    </SheetContent>
                </Sheet>
            </div>
        </div>
    </header>
</template>
