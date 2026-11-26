<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Banknote,
    BanknoteArrowUp,
    HandCoins,
    ReceiptText,
    TrendingUp,
    Wallet,
} from '@lucide/vue';
import { computed } from 'vue';
import AdminChartsRow from '@/components/dashboard/AdminChartsRow.vue';
import RecentActivityTables from '@/components/dashboard/RecentActivityTables.vue';
import StaffCounterOverview from '@/components/dashboard/StaffCounterOverview.vue';
import GcashIcon from '@/components/icons/GcashIcon.vue';
import { Button } from '@/components/ui/button';
import { formatPhp } from '@/lib/transactionFormat';
import { dashboard } from '@/routes';
import type { Expense } from '@/types/expense';
import type { Transaction } from '@/types/transaction';

const props = defineProps<{
    isStaff?: boolean;
    period: string;
    register: {
        opening_cash: number;
        cash_in: number;
        cash_out: number;
        cash_in_box: number;
        gcash_in: number;
        gcash_out: number;
        exact_net_gcash: number;
        total_sales: number;
        total_expenses: number;
    };
    kpis?: {
        total_sales: number;
        total_ddn: number;
        total_expenses: number;
        operating_expenses?: number;
        portal_reloads?: number;
        net_profit: number;
        total_cash: number;
        total_gcash: number;
        cash_in_box: number;
        exact_net_gcash: number;
    };
    portal?: {
        balance: number;
        is_low_balance: boolean;
        low_balance_threshold: number;
    };
    gcash?: {
        balance: number;
        is_low_balance: boolean;
        low_balance_threshold: number;
    };
    salesTrend?: { date: string; total_sales: number; total_ddn: number }[];
    expensesByCategory?: { category: string; total: number }[];
    recentTransactions: Transaction[];
    recentExpenses: Expense[];
    pendingReimbursements?: { count: number; total: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

type Period = 'today' | 'this_week' | 'this_month' | 'last_month' | 'this_year';

const periods: { key: Period; label: string }[] = [
    { key: 'today', label: 'Today' },
    { key: 'this_week', label: 'This Week' },
    { key: 'this_month', label: 'This Month' },
    { key: 'last_month', label: 'Last Month' },
    { key: 'this_year', label: 'This Year' },
];

const activePeriod = computed(() => props.period as Period);

function switchPeriod(period: Period) {
    router.get(
        '/dashboard',
        { period },
        { preserveState: true, preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Dashboard" />

    <main class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
        <section
            v-if="
                props.pendingReimbursements &&
                props.pendingReimbursements.count > 0
            "
            class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-300 bg-amber-50/80 p-3.5 text-sm dark:border-amber-800/60 dark:bg-amber-950/30"
        >
            <div class="flex items-center gap-2.5">
                <span
                    class="flex size-7 items-center justify-center rounded-full bg-amber-500/20 text-amber-700 dark:text-amber-300"
                >
                    <HandCoins class="size-4" />
                </span>
                <div>
                    <span
                        class="font-medium text-amber-900 dark:text-amber-200"
                    >
                        {{ props.pendingReimbursements.count }} Personal
                        Expense(s) Awaiting Reimbursement:
                    </span>
                    <span
                        class="ml-1 font-bold text-amber-800 dark:text-amber-100"
                    >
                        {{ formatPhp(props.pendingReimbursements.total) }}
                    </span>
                    <span
                        class="ml-1.5 text-xs text-amber-700/80 dark:text-amber-300/80"
                    >
                        (Repayments return directly to cash drawer / GCash
                        without duplicating sales revenue)
                    </span>
                </div>
            </div>
            <Button
                size="sm"
                variant="outline"
                as-child
                class="h-7 border-amber-400 bg-white text-xs font-semibold text-amber-800 hover:bg-amber-100 dark:bg-amber-900/40 dark:text-amber-200 dark:hover:bg-amber-900/70"
            >
                <Link href="/expenses?status=personal_pending">
                    View Pending Items
                </Link>
            </Button>
        </section>

        <StaffCounterOverview
            v-if="isStaff"
            :register="register"
            :gcash="gcash"
        />

        <template v-else-if="kpis">
            <section class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-primary">
                        Business overview
                    </p>
                    <h1
                        class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl"
                    >
                        Dashboard
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Real-time summary of sales, expenses, and cash drawer
                        reconciliation.
                    </p>
                </div>

                <div
                    class="flex flex-wrap items-center gap-1 rounded-lg border bg-muted/50 p-1"
                >
                    <Button
                        v-for="p in periods"
                        :key="p.key"
                        :variant="activePeriod === p.key ? 'default' : 'ghost'"
                        size="sm"
                        class="h-7 px-3 text-xs"
                        @click="switchPeriod(p.key)"
                    >
                        {{ p.label }}
                    </Button>
                </div>
            </section>

            <!-- Compact Live Balances Strip -->
            <section
                aria-label="Live Balances"
                class="grid gap-3 rounded-xl border bg-muted/30 p-3 sm:grid-cols-3"
            >
                <div
                    class="flex items-center justify-between rounded-lg border border-emerald-500/25 bg-card px-4 py-3"
                >
                    <div class="min-w-0">
                        <p
                            class="text-xs font-medium text-emerald-700 dark:text-emerald-400"
                        >
                            Today's Cash in Box
                        </p>
                        <p class="mt-0.5 text-lg font-semibold tracking-tight">
                            {{ formatPhp(register.cash_in_box) }}
                        </p>
                        <p class="text-[11px] text-muted-foreground">
                            +{{ formatPhp(register.cash_in) }} / -{{
                                formatPhp(register.cash_out)
                            }}
                        </p>
                    </div>
                    <span
                        class="rounded-md bg-emerald-500/15 p-2 text-emerald-600 dark:text-emerald-400"
                    >
                        <Banknote class="size-4" />
                    </span>
                </div>

                <Link
                    href="/gcash-ledger"
                    :class="[
                        'flex items-center justify-between rounded-lg border px-4 py-3 transition-colors hover:bg-muted/40',
                        gcash?.is_low_balance
                            ? 'border-amber-500/40 bg-amber-500/5'
                            : 'border-border bg-card',
                    ]"
                >
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                GCash Wallet
                            </p>
                            <span
                                v-if="gcash?.is_low_balance"
                                class="inline-flex items-center rounded bg-amber-500/20 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-300"
                            >
                                Low
                            </span>
                        </div>
                        <p class="mt-0.5 text-lg font-semibold tracking-tight">
                            {{ formatPhp(gcash?.balance ?? 0) }}
                        </p>
                        <p class="text-[11px] text-primary">View Ledger →</p>
                    </div>
                    <span
                        :class="[
                            'rounded-md p-2',
                            gcash?.is_low_balance
                                ? 'bg-amber-500/20 text-amber-600'
                                : 'bg-primary/10 text-primary',
                        ]"
                    >
                        <GcashIcon class="size-4" />
                    </span>
                </Link>

                <Link
                    href="/portal-deposits"
                    :class="[
                        'flex items-center justify-between rounded-lg border px-4 py-3 transition-colors hover:bg-muted/40',
                        portal?.is_low_balance
                            ? 'border-amber-500/40 bg-amber-500/5'
                            : 'border-border bg-card',
                    ]"
                >
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Flight Portal Deposit
                            </p>
                            <span
                                v-if="portal?.is_low_balance"
                                class="inline-flex items-center rounded bg-amber-500/20 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-300"
                            >
                                Low
                            </span>
                        </div>
                        <p class="mt-0.5 text-lg font-semibold tracking-tight">
                            {{ formatPhp(portal?.balance ?? 0) }}
                        </p>
                        <p class="text-[11px] text-primary">Manage Portal →</p>
                    </div>
                    <span
                        :class="[
                            'rounded-md p-2',
                            portal?.is_low_balance
                                ? 'bg-amber-500/20 text-amber-600'
                                : 'bg-primary/10 text-primary',
                        ]"
                    >
                        <Wallet class="size-4" />
                    </span>
                </Link>
            </section>

            <!-- 4 Primary Period KPI Cards -->
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-lg border bg-card p-5">
                    <div class="flex items-start justify-between">
                        <div>
                            <p
                                class="text-sm font-medium text-muted-foreground"
                            >
                                Total Sales
                            </p>
                            <p
                                class="mt-3 text-2xl font-semibold tracking-tight"
                            >
                                {{ formatPhp(kpis.total_sales) }}
                            </p>
                        </div>
                        <span
                            class="rounded-md bg-secondary p-2 text-secondary-foreground"
                        >
                            <BanknoteArrowUp class="size-5" />
                        </span>
                    </div>
                    <div
                        class="mt-3 flex items-center gap-3 border-t pt-3 text-xs text-muted-foreground"
                    >
                        <span>Cash {{ formatPhp(kpis.total_cash) }}</span>
                        <span>·</span>
                        <span>GCash {{ formatPhp(kpis.total_gcash) }}</span>
                    </div>
                </article>

                <article class="rounded-lg border bg-card p-5">
                    <div class="flex items-start justify-between">
                        <div>
                            <p
                                class="text-sm font-medium text-muted-foreground"
                            >
                                DDN Earnings
                            </p>
                            <p
                                class="mt-3 text-2xl font-semibold tracking-tight"
                            >
                                {{ formatPhp(kpis.total_ddn) }}
                            </p>
                        </div>
                        <span
                            class="rounded-md bg-secondary p-2 text-secondary-foreground"
                        >
                            <TrendingUp class="size-5" />
                        </span>
                    </div>
                    <p class="mt-3 border-t pt-3 text-xs text-muted-foreground">
                        DDN's gross margin on recorded sales
                    </p>
                </article>

                <article class="rounded-lg border bg-card p-5">
                    <div class="flex items-start justify-between">
                        <div>
                            <p
                                class="text-sm font-medium text-muted-foreground"
                            >
                                Operating Expenses
                            </p>
                            <p
                                class="mt-3 text-2xl font-semibold tracking-tight"
                            >
                                {{
                                    formatPhp(
                                        kpis.operating_expenses ??
                                            kpis.total_expenses,
                                    )
                                }}
                            </p>
                        </div>
                        <span
                            class="rounded-md bg-muted p-2 text-muted-foreground"
                        >
                            <ReceiptText class="size-5" />
                        </span>
                    </div>
                    <div
                        class="mt-3 flex items-center justify-between border-t pt-3 text-xs text-muted-foreground"
                    >
                        <span>Operating overhead</span>
                        <span
                            v-if="
                                kpis.portal_reloads && kpis.portal_reloads > 0
                            "
                            class="rounded bg-sky-100 px-1.5 py-0.5 text-[10px] font-semibold text-sky-800 dark:bg-sky-950/60 dark:text-sky-300"
                            :title="`Portal reloads: ${formatPhp(kpis.portal_reloads)}`"
                        >
                            +{{ formatPhp(kpis.portal_reloads) }} portal reloads
                        </span>
                    </div>
                </article>

                <article
                    class="rounded-lg border border-primary/20 bg-primary p-5 text-primary-foreground"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <p
                                class="text-sm font-medium text-primary-foreground/75"
                            >
                                Net Operating Profit
                            </p>
                            <p class="mt-3 text-2xl font-bold tracking-tight">
                                {{ formatPhp(kpis.net_profit) }}
                            </p>
                        </div>
                        <span
                            class="rounded-md bg-primary-foreground/10 p-2 text-primary-foreground"
                        >
                            <TrendingUp class="size-5" />
                        </span>
                    </div>
                    <p
                        class="mt-3 border-t border-primary-foreground/15 pt-3 text-xs text-primary-foreground/70"
                    >
                        DDN Earnings minus Operating Expenses
                    </p>
                </article>
            </section>

            <AdminChartsRow
                :sales-trend="salesTrend"
                :expenses-by-category="expensesByCategory"
            />
        </template>

        <RecentActivityTables
            :recent-transactions="recentTransactions"
            :recent-expenses="recentExpenses"
        />
    </main>
</template>
