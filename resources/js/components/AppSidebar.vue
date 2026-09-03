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
import { index as expenses } from '@/routes/expenses';
import { edit as editProfile } from '@/routes/profile';
import { index as transactions } from '@/routes/transactions';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutDashboard,
    },
    {
        title: 'Transactions',
        href: transactions(),
        icon: ReceiptText,
    },
    {
        title: 'Expenses',
        href: expenses(),
        icon: FileText,
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
