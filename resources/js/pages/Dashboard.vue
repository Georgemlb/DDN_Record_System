<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowDownRight,
    ArrowRight,
    ArrowUpRight,
    Banknote,
    BanknoteArrowUp,
    HandCoins,
    Plus,
    Receipt,
    ReceiptText,
    TrendingUp,
    Wallet,
    WalletCards,
} from '@lucide/vue';
import {
    ArcElement,
    CategoryScale,
    Chart as ChartJS,
    Filler,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Title,
    Tooltip,
} from 'chart.js';
import { computed } from 'vue';
import { Doughnut, Line } from 'vue-chartjs';
import GcashIcon from '@/components/icons/GcashIcon.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    formatExpenseDate,
    formatExpensePaymentMethod,
} from '@/lib/expenseFormat';
import {
    formatPhp,
    formatTransactionDate,
    formatTransactionType,
    getTransactionServiceDetail,
} from '@/lib/transactionFormat';
import { dashboard } from '@/routes';
import { create as createExpense } from '@/routes/expenses';
import { create as createTransaction } from '@/routes/transactions';
import { show as transactionShow } from '@/routes/transactions';
import type { Expense } from '@/types/expense';
import type { Transaction } from '@/types/transaction';

ChartJS.register(
    ArcElement,
    CategoryScale,
    Filler,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Title,
    Tooltip,
);

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

// -------------------------------------------------------
// Period Selector (Admin)
// -------------------------------------------------------
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

// -------------------------------------------------------
// Chart Data (Admin)
// -------------------------------------------------------
const PALETTE = [
    '#3b82f6',
    '#10b981',
    '#f59e0b',
    '#ef4444',
    '#8b5cf6',
    '#ec4899',
    '#06b6d4',
    '#84cc16',
    '#f97316',
    '#6366f1',
];

function formatChartDate(dateStr: string): string {
    const [year, month, day] = dateStr.split('-').map(Number);
    const d = new Date(year, month - 1, day);

    return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
}

const lineChartData = computed(() => {
    const trend = props.salesTrend ?? [];

    return {
        labels: trend.map((d) => formatChartDate(d.date)),
        datasets: [
            {
                label: 'Total Sales',
                data: trend.map((d) => d.total_sales),
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.08)',
                borderWidth: 2,
                pointRadius: trend.length > 20 ? 0 : 3,
                pointHoverRadius: 5,
                fill: true,
                tension: 0.3,
            },
            {
                label: 'DDN Earnings',
                data: trend.map((d) => d.total_ddn),
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.08)',
                borderWidth: 2,
                pointRadius: trend.length > 20 ? 0 : 3,
                pointHoverRadius: 5,
                fill: true,
                tension: 0.3,
            },
        ],
    };
});

const lineChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top' as const,
            align: 'end' as const,
            labels: {
                boxWidth: 10,
                boxHeight: 10,
                usePointStyle: true,
                pointStyle: 'circle',
                font: { size: 12 },
            },
        },
        tooltip: {
            callbacks: {
                label: (ctx: any) =>
                    ` ${ctx.dataset.label}: ${formatPhp(ctx.raw)}`,
            },
        },
    },
    scales: {
        x: {
            grid: { display: false },
            ticks: { font: { size: 11 }, maxRotation: 0, autoSkip: true },
        },
        y: {
            grid: { color: 'rgba(0, 0, 0, 0.04)' },
            ticks: {
                font: { size: 11 },
                callback: (val: any) =>
                    val >= 1000 ? `₱${(val / 1000).toFixed(0)}k` : `₱${val}`,
            },
        },
    },
};

const donutChartData = computed(() => {
    const expenses = props.expensesByCategory ?? [];

    return {
        labels: expenses.map((e) => e.category),
        datasets: [
            {
                data: expenses.map((e) => e.total),
                backgroundColor: PALETTE.slice(0, expenses.length),
                borderWidth: 0,
                hoverOffset: 6,
            },
        ],
    };
});

const donutChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '68%',
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (ctx: any) => ` ${ctx.label}: ${formatPhp(ctx.raw)}`,
            },
        },
    },
};

const hasExpenses = computed(() => (props.expensesByCategory ?? []).length > 0);
const hasSalesTrend = computed(() => (props.salesTrend ?? []).length > 0);
</script>

<template>
    <Head title="Dashboard" />

    <main class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
        <!-- Pending Personal Reimbursements Alert Banner -->
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

        <!-- ======================================================== -->
        <!-- STAFF VIEW: Counter Register & Operational Overview     -->
        <!-- ======================================================== -->
        <template v-if="isStaff">
            <!-- Staff Header & Quick Actions -->
            <section
                class="flex flex-wrap items-center justify-between gap-4 border-b pb-5"
            >
                <div>
                    <Badge variant="secondary" class="mb-1 text-xs">
                        Daily Counter Shift
                    </Badge>
                    <h1
                        class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl"
                    >
                        Counter Register
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Live Cash in Box, exact GCash balances, and today's
                        operations.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <Button as-child class="gap-1.5 shadow-sm">
                        <Link :href="createTransaction()">
                            <Plus class="size-4" />
                            New Transaction
                        </Link>
                    </Button>
                    <Button variant="outline" as-child class="gap-1.5">
                        <Link :href="createExpense()">
                            <Plus class="size-4" />
                            New Expense
                        </Link>
                    </Button>
                </div>
            </section>

            <!-- Counter Register KPI Cards -->
            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <!-- Cash in Box (Cash Drawer) -->
                <article
                    class="rounded-xl border border-emerald-500/30 bg-gradient-to-br from-emerald-500/10 via-card to-card p-5 shadow-sm"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <p
                                class="text-xs font-semibold tracking-wider text-emerald-700 uppercase dark:text-emerald-400"
                            >
                                Cash in Box (Drawer)
                            </p>
                            <p
                                class="mt-2 text-3xl font-bold tracking-tight text-foreground"
                            >
                                {{ formatPhp(register.cash_in_box) }}
                            </p>
                        </div>
                        <span
                            class="rounded-lg bg-emerald-500/15 p-2.5 text-emerald-600 dark:text-emerald-400"
                        >
                            <Banknote class="size-6" />
                        </span>
                    </div>
                    <div
                        class="mt-4 flex flex-col gap-1 border-t border-emerald-500/15 pt-3 text-xs text-muted-foreground"
                    >
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-1">
                                <ArrowUpRight
                                    class="size-3.5 text-emerald-500"
                                />
                                Cash In (Sales):
                            </span>
                            <span class="font-medium text-foreground">
                                +{{ formatPhp(register.cash_in) }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-1">
                                <ArrowDownRight
                                    class="size-3.5 text-rose-500"
                                />
                                Cash Out (Expenses):
                            </span>
                            <span class="font-medium text-foreground">
                                -{{ formatPhp(register.cash_out) }}
                            </span>
                        </div>
                        <div
                            v-if="register.opening_cash > 0"
                            class="flex items-center justify-between text-[11px] text-muted-foreground/80"
                        >
                            <span>Opening Float:</span>
                            <span>{{ formatPhp(register.opening_cash) }}</span>
                        </div>
                    </div>
                </article>

                <!-- Live GCash Wallet Balance -->
                <article
                    :class="[
                        'rounded-xl border p-5 shadow-sm transition-all',
                        gcash?.is_low_balance
                            ? 'border-amber-500/40 bg-gradient-to-br from-amber-500/10 via-card to-card'
                            : 'border-blue-500/30 bg-gradient-to-br from-blue-500/10 via-card to-card',
                    ]"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="flex items-center gap-1.5">
                                <p
                                    class="text-xs font-semibold tracking-wider text-blue-700 uppercase dark:text-blue-400"
                                >
                                    GCash Wallet Balance
                                </p>
                                <span
                                    v-if="gcash?.is_low_balance"
                                    class="inline-flex items-center rounded bg-amber-500/20 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-300"
                                >
                                    Low
                                </span>
                            </div>
                            <p
                                class="mt-2 text-3xl font-bold tracking-tight text-foreground"
                            >
                                {{ formatPhp(gcash?.balance ?? 0) }}
                            </p>
                        </div>
                        <span
                            :class="[
                                'rounded-lg p-2.5',
                                gcash?.is_low_balance
                                    ? 'bg-amber-500/20 text-amber-600'
                                    : 'bg-blue-500/15 text-blue-600 dark:text-blue-400',
                            ]"
                        >
                            <GcashIcon class="size-6" />
                        </span>
                    </div>
                    <div
                        class="mt-4 flex flex-col gap-1 border-t border-blue-500/15 pt-3 text-xs text-muted-foreground"
                    >
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-1">
                                <ArrowUpRight class="size-3.5 text-blue-500" />
                                Today In:
                            </span>
                            <span class="font-medium text-foreground">
                                +{{ formatPhp(register.gcash_in) }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-1">
                                <ArrowDownRight
                                    class="size-3.5 text-rose-500"
                                />
                                Today Out:
                            </span>
                            <span class="font-medium text-foreground">
                                -{{ formatPhp(register.gcash_out) }}
                            </span>
                        </div>
                    </div>
                </article>

                <!-- Today's Total Counter Volume -->
                <article
                    class="rounded-xl border bg-card p-5 shadow-sm sm:col-span-2 lg:col-span-1"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <p
                                class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                Today's Activity
                            </p>
                            <p
                                class="mt-2 text-3xl font-bold tracking-tight text-foreground"
                            >
                                {{ formatPhp(register.total_sales) }}
                            </p>
                        </div>
                        <span
                            class="rounded-lg bg-primary/10 p-2.5 text-primary"
                        >
                            <Receipt class="size-6" />
                        </span>
                    </div>
                    <div
                        class="mt-4 flex items-center justify-between border-t pt-3 text-xs text-muted-foreground"
                    >
                        <span>Total Counter Outflow:</span>
                        <span class="font-semibold text-rose-600">
                            {{ formatPhp(register.total_expenses) }}
                        </span>
                    </div>
                </article>
            </section>
        </template>

        <!-- ======================================================== -->
        <!-- ADMIN / TECH ADMIN VIEW: Executive Business Intelligence -->
        <!-- ======================================================== -->
        <template v-else-if="kpis">
            <!-- Header & Period Filter -->
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

                <!-- Period Selector -->
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

            <!-- Executive KPI Cards -->
            <section
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
            >
                <!-- Total Sales -->
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

                <!-- DDN Earnings -->
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

                <!-- Operating Expenses -->
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
                                {{ formatPhp(kpis.operating_expenses ?? kpis.total_expenses) }}
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
                            v-if="kpis.portal_reloads && kpis.portal_reloads > 0"
                            class="rounded bg-sky-100 px-1.5 py-0.5 text-[10px] font-semibold text-sky-800 dark:bg-sky-950/60 dark:text-sky-300"
                            :title="`Portal reloads: ${formatPhp(kpis.portal_reloads)}`"
                        >
                            +{{ formatPhp(kpis.portal_reloads) }} portal reloads
                        </span>
                    </div>
                </article>

                <!-- Net Profit -->
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

                <!-- Live Cash in Box (Today) -->
                <article
                    class="rounded-lg border border-emerald-500/30 bg-emerald-500/5 p-5"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <p
                                class="text-sm font-medium text-emerald-700 dark:text-emerald-400"
                            >
                                Today's Cash in Box
                            </p>
                            <p
                                class="mt-3 text-2xl font-semibold tracking-tight"
                            >
                                {{ formatPhp(register.cash_in_box) }}
                            </p>
                        </div>
                        <span
                            class="rounded-md bg-emerald-500/15 p-2 text-emerald-600 dark:text-emerald-400"
                        >
                            <Banknote class="size-5" />
                        </span>
                    </div>
                    <div
                        class="mt-3 flex items-center justify-between border-t border-emerald-500/15 pt-3 text-xs text-muted-foreground"
                    >
                        <span>In: +{{ formatPhp(register.cash_in) }}</span>
                        <span>Out: -{{ formatPhp(register.cash_out) }}</span>
                    </div>
                </article>

                <!-- GCash Wallet Balance -->
                <article
                    :class="[
                        'rounded-lg border p-5 transition-all',
                        gcash?.is_low_balance
                            ? 'border-amber-500/40 bg-gradient-to-br from-amber-500/10 to-transparent'
                            : 'border-border bg-card',
                    ]"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="flex items-center gap-1.5">
                                <p
                                    class="text-sm font-medium text-muted-foreground"
                                >
                                    GCash Wallet Balance
                                </p>
                                <span
                                    v-if="gcash?.is_low_balance"
                                    class="inline-flex items-center rounded bg-amber-500/20 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-300"
                                >
                                    Low
                                </span>
                            </div>
                            <p
                                class="mt-3 text-2xl font-semibold tracking-tight"
                            >
                                {{ formatPhp(gcash?.balance ?? 0) }}
                            </p>
                        </div>
                        <Link
                            href="/gcash-ledger"
                            :class="[
                                'rounded-md p-2 transition-colors',
                                gcash?.is_low_balance
                                    ? 'bg-amber-500/20 text-amber-600 hover:bg-amber-500/30'
                                    : 'bg-primary/10 text-primary hover:bg-primary/20',
                            ]"
                            title="Manage GCash Ledger"
                        >
                            <GcashIcon class="size-5" />
                        </Link>
                    </div>
                    <div
                        class="mt-3 flex items-center justify-between border-t pt-3 text-xs text-muted-foreground"
                    >
                        <span>Today In: +{{ formatPhp(register.gcash_in) }} / Out: -{{ formatPhp(register.gcash_out) }}</span>
                        <Link
                            href="/gcash-ledger"
                            class="font-medium text-primary hover:underline"
                        >
                            Ledger &rarr;
                        </Link>
                    </div>
                </article>

                <!-- Flight Portal Deposit -->
                <article
                    :class="[
                        'rounded-lg border p-5 transition-all sm:col-span-2 xl:col-span-2',
                        portal?.is_low_balance
                            ? 'border-amber-500/40 bg-gradient-to-br from-amber-500/10 to-transparent'
                            : 'border-border bg-card',
                    ]"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="flex items-center gap-1.5">
                                <p
                                    class="text-sm font-medium text-muted-foreground"
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
                            <p
                                class="mt-3 text-2xl font-semibold tracking-tight"
                            >
                                {{ formatPhp(portal?.balance ?? 0) }}
                            </p>
                        </div>
                        <Link
                            href="/portal-deposits"
                            :class="[
                                'rounded-md p-2 transition-colors',
                                portal?.is_low_balance
                                    ? 'bg-amber-500/20 text-amber-600 hover:bg-amber-500/30'
                                    : 'bg-primary/10 text-primary hover:bg-primary/20',
                            ]"
                            title="Manage Portal Deposit"
                        >
                            <Wallet class="size-5" />
                        </Link>
                    </div>
                    <div
                        class="mt-3 flex items-center justify-between border-t pt-3 text-xs text-muted-foreground"
                    >
                        <span>Available ticket booking balance</span>
                        <Link
                            href="/portal-deposits"
                            class="font-medium text-primary hover:underline"
                        >
                            Manage &rarr;
                        </Link>
                    </div>
                </article>
            </section>

            <!-- Charts Row (Admin) -->
            <section class="grid gap-4 lg:grid-cols-5">
                <!-- Line Chart — Sales Trend -->
                <Card class="lg:col-span-3">
                    <CardHeader>
                        <CardTitle class="text-base">Sales Trend</CardTitle>
                        <p class="text-sm text-muted-foreground">
                            Daily Total Sales vs DDN Earnings
                        </p>
                    </CardHeader>
                    <CardContent>
                        <div v-if="hasSalesTrend" class="h-64">
                            <Line
                                :data="lineChartData"
                                :options="lineChartOptions"
                            />
                        </div>
                        <div
                            v-else
                            class="flex h-64 items-center justify-center rounded-md border border-dashed"
                        >
                            <p class="text-sm text-muted-foreground">
                                No sales data for the selected period.
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Donut Chart — Expenses by Category -->
                <Card class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle class="text-base"
                            >Operating Expenses by Category</CardTitle
                        >
                        <p class="text-sm text-muted-foreground">
                            Operational overhead breakdown (excluding portal capital transfers)
                        </p>
                    </CardHeader>
                    <CardContent>
                        <div v-if="hasExpenses" class="flex flex-col gap-4">
                            <div class="mx-auto h-44 w-44">
                                <Doughnut
                                    :data="donutChartData"
                                    :options="donutChartOptions"
                                />
                            </div>
                            <ul class="space-y-1.5">
                                <li
                                    v-for="(item, idx) in expensesByCategory"
                                    :key="item.category"
                                    class="flex items-center justify-between text-sm"
                                >
                                    <span class="flex items-center gap-2">
                                        <span
                                            class="inline-block size-2.5 flex-shrink-0 rounded-full"
                                            :style="{
                                                backgroundColor: PALETTE[idx],
                                            }"
                                        />
                                        <span class="text-muted-foreground">{{
                                            item.category
                                        }}</span>
                                    </span>
                                    <span class="font-medium">{{
                                        formatPhp(item.total)
                                    }}</span>
                                </li>
                            </ul>
                        </div>
                        <div
                            v-else
                            class="flex h-64 items-center justify-center rounded-md border border-dashed"
                        >
                            <p class="text-sm text-muted-foreground">
                                No expenses for the selected period.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </section>
        </template>

        <!-- ======================================================== -->
        <!-- SHARED DUAL TABLES: Recent Transactions & Recent Expenses-->
        <!-- ======================================================== -->
        <section class="grid gap-6 lg:grid-cols-2">
            <!-- Recent Transactions Table -->
            <Card class="shadow-sm">
                <CardHeader
                    class="flex flex-row items-center justify-between gap-4 border-b pb-4"
                >
                    <div>
                        <CardTitle class="text-base"
                            >Recent Transactions</CardTitle
                        >
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Latest 5 recorded counter transactions
                        </p>
                    </div>
                    <Button variant="ghost" size="sm" as-child>
                        <Link
                            href="/transactions"
                            class="flex items-center gap-1.5 text-xs text-primary"
                        >
                            View all <ArrowRight class="size-3.5" />
                        </Link>
                    </Button>
                </CardHeader>
                <CardContent class="p-0">
                    <div v-if="recentTransactions.length > 0" class="divide-y">
                        <Link
                            v-for="tx in recentTransactions"
                            :key="tx.id"
                            :href="transactionShow.url({ transaction: tx.id })"
                            class="flex items-center justify-between gap-3 px-5 py-3.5 transition-colors hover:bg-muted/40"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <p class="truncate text-sm font-medium">
                                        {{ tx.customer_name }}
                                    </p>
                                    <Badge
                                        variant="outline"
                                        class="shrink-0 text-[10px]"
                                    >
                                        {{ formatTransactionType(tx.type) }}
                                    </Badge>
                                </div>
                                <p
                                    class="mt-0.5 truncate text-xs text-muted-foreground"
                                >
                                    {{ getTransactionServiceDetail(tx) }} ·
                                    {{
                                        formatTransactionDate(
                                            tx.transaction_date,
                                        )
                                    }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold">
                                    {{ formatPhp(tx.amount) }}
                                </p>
                                <span
                                    :class="[
                                        'inline-block text-[11px] font-medium capitalize',
                                        tx.payment_method === 'cash'
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : tx.payment_method === 'gcash'
                                              ? 'text-blue-600 dark:text-blue-400'
                                              : 'text-purple-600 dark:text-purple-400',
                                    ]"
                                >
                                    {{ tx.payment_method }}
                                </span>
                            </div>
                        </Link>
                    </div>
                    <div
                        v-else
                        class="flex h-32 items-center justify-center text-sm text-muted-foreground"
                    >
                        No transactions recorded yet.
                    </div>
                </CardContent>
            </Card>

            <!-- Recent Expenses Table -->
            <Card class="shadow-sm">
                <CardHeader
                    class="flex flex-row items-center justify-between gap-4 border-b pb-4"
                >
                    <div>
                        <CardTitle class="text-base">Recent Expenses</CardTitle>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Latest 5 recorded business expenses
                        </p>
                    </div>
                    <Button variant="ghost" size="sm" as-child>
                        <Link
                            href="/expenses"
                            class="flex items-center gap-1.5 text-xs text-primary"
                        >
                            View all <ArrowRight class="size-3.5" />
                        </Link>
                    </Button>
                </CardHeader>
                <CardContent class="p-0">
                    <div v-if="recentExpenses.length > 0" class="divide-y">
                        <Link
                            v-for="exp in recentExpenses"
                            :key="exp.id"
                            :href="`/expenses/${exp.id}`"
                            class="flex items-center justify-between gap-3 px-5 py-3.5 transition-colors hover:bg-muted/40"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <p class="truncate text-sm font-medium">
                                        {{ exp.title }}
                                    </p>
                                    <Badge
                                        variant="secondary"
                                        class="shrink-0 text-[10px]"
                                    >
                                        {{ exp.category?.name ?? 'General' }}
                                    </Badge>
                                </div>
                                <p
                                    class="mt-0.5 truncate text-xs text-muted-foreground"
                                >
                                    {{ formatExpenseDate(exp.expense_date) }}
                                    <span v-if="exp.payee">
                                        · Payee: {{ exp.payee }}
                                    </span>
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-rose-600">
                                    -{{ formatPhp(exp.amount) }}
                                </p>
                                <span
                                    class="inline-block text-[11px] text-muted-foreground capitalize"
                                >
                                    {{
                                        formatExpensePaymentMethod(
                                            exp.payment_method,
                                        )
                                    }}
                                </span>
                            </div>
                        </Link>
                    </div>
                    <div
                        v-else
                        class="flex h-32 items-center justify-center text-sm text-muted-foreground"
                    >
                        No expenses recorded yet.
                    </div>
                </CardContent>
            </Card>
        </section>
    </main>
</template>
