<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ChevronsUpDown } from '@lucide/vue';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { getInitials } from '@/composables/useInitials';
import type { BreadcrumbItem } from '@/types';

const page = usePage();
const user = computed(() => page.props.auth.user);

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
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <button
                    type="button"
                    class="ml-auto flex items-center gap-2 rounded-md px-2 py-1.5 text-left transition-colors hover:bg-accent focus:ring-2 focus:ring-ring focus:outline-none"
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
    </header>
</template>
