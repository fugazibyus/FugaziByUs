<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ChevronDown, LogOut, UserRound } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import UserInfo from '@/components/UserInfo.vue';
import { getInitials } from '@/composables/useInitials';
import { logout } from '@/routes';
import { show as account } from '@/routes/account';
import type { User } from '@/types';

defineProps<{ user: User }>();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                class="h-10 gap-2 px-2"
                data-test="user-menu"
            >
                <Avatar class="size-8 rounded-lg">
                    <AvatarFallback class="rounded-lg">{{
                        getInitials(user.name)
                    }}</AvatarFallback>
                </Avatar>
                <span
                    class="hidden max-w-40 truncate text-sm font-medium sm:inline"
                    >{{ user.name }}</span
                >
                <ChevronDown class="size-4 text-muted-foreground" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-60">
            <DropdownMenuLabel class="p-0 font-normal">
                <div class="flex items-center gap-2 px-1 py-1.5 text-sm">
                    <UserInfo :user="user" show-email />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem as-child>
                <Link :href="account()" class="w-full cursor-pointer" prefetch>
                    <UserRound class="mr-2 size-4" />
                    My account
                </Link>
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem as-child>
                <Link
                    :href="logout()"
                    as="button"
                    class="w-full cursor-pointer"
                    data-test="logout-button"
                    @click="router.flushAll()"
                >
                    <LogOut class="mr-2 size-4" />
                    Log out
                </Link>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
