<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavGroup, NavItem } from '@/types';

const props = defineProps<{
    items?: NavItem[];
    groups?: NavGroup[];
}>();

const { isCurrentUrl } = useCurrentUrl();

const resolvedGroups = computed<NavGroup[]>(() => {
    if (props.groups && props.groups.length > 0) {
        return props.groups;
    }

    return [{ label: 'Platform', items: props.items ?? [] }];
});
</script>

<template>
    <SidebarGroup
        v-for="group in resolvedGroups"
        :key="group.label"
        class="px-2 py-0"
    >
        <SidebarGroupLabel>{{ group.label }}</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in group.items" :key="item.title">
                <SidebarMenuButton
                    :as-child="!item.disabled"
                    :is-active="!item.disabled && isCurrentUrl(item.href)"
                    :tooltip="item.title"
                    :disabled="item.disabled"
                >
                    <span
                        v-if="item.disabled"
                        class="cursor-not-allowed opacity-45"
                    >
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </span>
                    <Link v-else :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
