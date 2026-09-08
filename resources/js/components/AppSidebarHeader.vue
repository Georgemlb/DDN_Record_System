<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ChevronsUpDown, Moon, Sun } from '@lucide/vue';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useAppearance } from '@/composables/useAppearance';
import { getInitials } from '@/composables/useInitials';
import type { BreadcrumbItem } from '@/types';

const page = usePage();
const user = computed(() => page.props.auth.user);
const { resolvedAppearance, updateAppearance } = useAppearance();

const toggleTheme = () => {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
};

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 bg-background px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-6"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <button
                type="button"
                @click="toggleTheme"
                class="flex size-9 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                :title="
                    resolvedAppearance === 'dark'
                        ? 'Switch to light mode'
                        : 'Switch to dark mode'
                "
                aria-label="Toggle theme"
            >
                <Sun v-if="resolvedAppearance === 'dark'" class="size-4" />
                <Moon v-else class="size-4" />
            </button>

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-md px-2 py-1.5 text-left transition-colors hover:bg-accent focus:ring-2 focus:ring-ring focus:outline-none"
                    >
                        <Avatar class="size-8">
                            <AvatarImage
                                v-if="user?.avatar"
                                :src="user.avatar"
                                :alt="user.name"
                            />
                            <AvatarFallback
                                class="bg-secondary text-xs font-semibold text-secondary-foreground"
                            >
                                {{ getInitials(user?.name) }}
                            </AvatarFallback>
                        </Avatar>
                        <span
                            class="hidden max-w-36 truncate text-sm font-medium sm:block"
                        >
                            {{ user?.name }}
                        </span>
                        <ChevronsUpDown
                            class="hidden size-4 text-muted-foreground sm:block"
                        />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-56">
                    <UserMenuContent v-if="user" :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>
