<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { LogOut, Monitor, Moon, Sun } from '@lucide/vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { useAppearance } from '@/composables/useAppearance';
import { logout } from '@/routes';
import type { User } from '@/types';

type Props = {
    user: User;
};

const { appearance, updateAppearance } = useAppearance();

const handleLogout = () => {
    router.flushAll();
};

defineProps<Props>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuSub>
            <DropdownMenuSubTrigger class="cursor-pointer">
                <Sun v-if="appearance === 'light'" class="mr-2 size-4" />
                <Moon v-else-if="appearance === 'dark'" class="mr-2 size-4" />
                <Monitor v-else class="mr-2 size-4" />
                <span>Theme</span>
            </DropdownMenuSubTrigger>
            <DropdownMenuSubContent>
                <DropdownMenuItem
                    @click="updateAppearance('light')"
                    class="cursor-pointer"
                >
                    <Sun class="mr-2 size-4" />
                    <span>Light</span>
                </DropdownMenuItem>
                <DropdownMenuItem
                    @click="updateAppearance('dark')"
                    class="cursor-pointer"
                >
                    <Moon class="mr-2 size-4" />
                    <span>Dark</span>
                </DropdownMenuItem>
                <DropdownMenuItem
                    @click="updateAppearance('system')"
                    class="cursor-pointer"
                >
                    <Monitor class="mr-2 size-4" />
                    <span>System</span>
                </DropdownMenuItem>
            </DropdownMenuSubContent>
        </DropdownMenuSub>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full cursor-pointer"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            Log out
        </Link>
    </DropdownMenuItem>
</template>
