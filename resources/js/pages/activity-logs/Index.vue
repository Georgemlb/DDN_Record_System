<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { History, Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import FilterToggleBar from '@/components/FilterToggleBar.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type ActivityLogItem = {
    id: number;
    module: string;
    action: string;
    description: string;
    ip_address: string | null;
    created_at: string;
    user?: {
        id: number;
        name: string;
        username: string;
        role: string;
    } | null;
};

type UserOption = {
    id: number;
    name: string;
};

const props = defineProps<{
    logs: {
        data: ActivityLogItem[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        from?: number | null;
        to?: number | null;
        total?: number;
        current_page?: number;
        last_page?: number;
    };
    users: UserOption[];
    filters: {
        search?: string;
        module?: string;
        action?: string;
        user_id?: string;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Activity Log', href: '/activity-logs' }],
    },
});

const search = ref(props.filters.search ?? '');
const moduleFilter = ref(props.filters.module ?? 'all');
const actionFilter = ref(props.filters.action ?? 'all');
const userFilter = ref(props.filters.user_id ?? 'all');

const activeFilterCount = computed(
    () =>
        [
            moduleFilter.value !== 'all',
            actionFilter.value !== 'all',
            userFilter.value !== 'all',
        ].filter(Boolean).length,
);

const clearFilters = () => {
    search.value = '';
    moduleFilter.value = 'all';
    actionFilter.value = 'all';
    userFilter.value = 'all';
};

let timeout: ReturnType<typeof setTimeout>;
watch([search, moduleFilter, actionFilter, userFilter], () => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(
            '/activity-logs',
            {
                search: search.value,
                module: moduleFilter.value,
                action: actionFilter.value,
                user_id: userFilter.value,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

const formatTimestamp = (value: string): string => {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};

const formatLabel = (value: string): string =>
    value.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

const actionBadgeClass = (action: string): string => {
    if (action.includes('delete')) {
        return 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-800 dark:bg-rose-950/50 dark:text-rose-300';
    }

    if (action === 'created' || action === 'restored' || action === 'top_up') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300';
    }

    if (action === 'reopened' || action === 'adjusted') {
        return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-300';
    }

    return 'border-border bg-secondary text-secondary-foreground';
};
</script>

<template>
    <Head title="Activity Log" />

    <main class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
        <section class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Activity &amp; Audit Log
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Chronological trail of transactions, expenses, ledger
                    adjustments, settlements, and user changes.
                </p>
            </div>
        </section>

        <section class="rounded-lg border bg-card p-4 shadow-xs sm:p-5">
            <FilterToggleBar
                :active-count="activeFilterCount"
                @clear="clearFilters"
            >
                <template #search>
                    <div class="relative">
                        <Search
                            class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="search"
                            placeholder="Search activity description..."
                            class="w-full pl-9"
                        />
                    </div>
                </template>

                <template #filters>
                    <Select v-model="moduleFilter">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="All Modules" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Modules</SelectItem>
                            <SelectItem value="transaction"
                                >Transactions</SelectItem
                            >
                            <SelectItem value="expense">Expenses</SelectItem>
                            <SelectItem value="gcash_ledger"
                                >GCash Ledger</SelectItem
                            >
                            <SelectItem value="portal_deposit"
                                >Portal Deposits</SelectItem
                            >
                            <SelectItem value="settlement"
                                >Daily Settlements</SelectItem
                            >
                            <SelectItem value="user">Users</SelectItem>
                        </SelectContent>
                    </Select>

                    <Select v-model="actionFilter">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="All Actions" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Actions</SelectItem>
                            <SelectItem value="created">Created</SelectItem>
                            <SelectItem value="updated">Updated</SelectItem>
                            <SelectItem value="deleted">Deleted</SelectItem>
                            <SelectItem value="restored">Restored</SelectItem>
                            <SelectItem value="force_deleted"
                                >Force Deleted</SelectItem
                            >
                            <SelectItem value="payment_recorded"
                                >Payment Recorded</SelectItem
                            >
                            <SelectItem value="reimbursed"
                                >Reimbursed</SelectItem
                            >
                            <SelectItem value="adjusted">Adjusted</SelectItem>
                            <SelectItem value="top_up">Top-Up</SelectItem>
                            <SelectItem value="closed">Closed</SelectItem>
                            <SelectItem value="reopened">Reopened</SelectItem>
                        </SelectContent>
                    </Select>

                    <Select v-model="userFilter">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="All Users" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Users</SelectItem>
                            <SelectItem
                                v-for="u in users"
                                :key="u.id"
                                :value="String(u.id)"
                            >
                                {{ u.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </template>
            </FilterToggleBar>
        </section>

        <section
            v-if="logs.data.length"
            class="overflow-hidden rounded-lg border bg-card shadow-xs"
        >
            <div class="overflow-x-auto">
                <table class="w-full min-w-185 text-left text-sm">
                    <thead
                        class="border-b bg-muted/40 text-xs tracking-wide text-muted-foreground uppercase"
                    >
                        <tr>
                            <th class="px-5 py-3 font-medium">When</th>
                            <th class="px-5 py-3 font-medium">User</th>
                            <th class="px-5 py-3 font-medium">Module</th>
                            <th class="px-5 py-3 font-medium">Action</th>
                            <th class="px-5 py-3 font-medium">Description</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="entry in logs.data"
                            :key="entry.id"
                            class="transition-colors hover:bg-muted/30"
                        >
                            <td
                                class="px-5 py-3.5 text-xs whitespace-nowrap text-muted-foreground tabular-nums"
                            >
                                {{ formatTimestamp(entry.created_at) }}
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <span class="font-medium text-foreground">
                                    {{ entry.user?.name ?? 'System' }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <Badge variant="outline" class="text-xs">
                                    {{ formatLabel(entry.module) }}
                                </Badge>
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <Badge
                                    variant="outline"
                                    :class="[
                                        'text-xs font-medium',
                                        actionBadgeClass(entry.action),
                                    ]"
                                >
                                    {{ formatLabel(entry.action) }}
                                </Badge>
                            </td>
                            <td class="px-5 py-3.5 text-foreground">
                                {{ entry.description }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="border-t px-5 py-3">
                <Pagination :links="logs.links" />
            </div>
        </section>

        <section
            v-else
            class="flex flex-col items-center justify-center rounded-lg border border-dashed bg-card px-6 py-16 text-center"
        >
            <History class="size-10 text-muted-foreground/60" />
            <h2 class="mt-3 text-base font-semibold">No activity recorded</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Audit entries will appear here as staff and admins record or
                modify data.
            </p>
        </section>
    </main>
</template>
