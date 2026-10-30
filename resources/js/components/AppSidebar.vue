<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    FileText,
    LayoutDashboard,
    ReceiptText,
    SlidersHorizontal,
    Users,
    Wallet,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import GcashIcon from '@/components/icons/GcashIcon.vue';
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
import { index as configurations } from '@/routes/configurations';
import { index as expenses } from '@/routes/expenses';
import { index as gcashLedger } from '@/routes/gcash-ledger';
import { index as portalDeposits } from '@/routes/portal-deposits';
import { index as reports } from '@/routes/reports';
import { index as transactions } from '@/routes/transactions';
import { index as users } from '@/routes/users';
import type { NavItem } from '@/types';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const isStaff = computed(() => user.value?.role === 'staff');

const mainNavItems = computed<NavItem[]>(() => {
    if (isStaff.value) {
        return [
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
        ];
    }

    return [
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
            title: 'Portal Deposit',
            href: portalDeposits(),
            icon: Wallet,
        },
        {
            title: 'GCash Ledger',
            href: gcashLedger(),
            icon: GcashIcon,
        },
        {
            title: 'Reports',
            href: reports(),
            icon: BarChart3,
        },
        {
            title: 'Configurations',
            href: configurations(),
            icon: SlidersHorizontal,
        },
        {
            title: 'Users',
            href: users(),
            icon: Users,
        },
    ];
});
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
