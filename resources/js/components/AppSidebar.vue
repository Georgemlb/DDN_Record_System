<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BarChart3,
    FileText,
    LayoutDashboard,
    ReceiptText,
    Settings,
    UtilityPole,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutDashboard,
    },
    {
        title: 'Transactions',
        href: '#',
        icon: ReceiptText,
        disabled: true,
    },
    {
        title: 'Expenses',
        href: '#',
        icon: FileText,
        disabled: true,
    },
    {
        title: 'Utilities',
        href: '#',
        icon: UtilityPole,
        disabled: true,
    },
    {
        title: 'Reports',
        href: '#',
        icon: BarChart3,
        disabled: true,
    },
    {
        title: 'Settings',
        href: editProfile(),
        icon: Settings,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="pt-2">
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
