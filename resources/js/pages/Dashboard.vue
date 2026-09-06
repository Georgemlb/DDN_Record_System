<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    BanknoteArrowUp,
    Receipt,
    ReceiptText,
    TrendingUp,
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatPhp, formatTransactionDate, formatTransactionType } from '@/lib/transactionFormat';
import { dashboard } from '@/routes';
import { show as transactionShow } from '@/routes/transactions';
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
    period: string;
    kpis: {
        total_sales: number;
        total_ddn: number;
        total_expenses: number;
        net_profit: number;
        total_cash: number;
        total_gcash: number;
    };
    salesTrend: { date: string; total_sales: number; total_ddn: number }[];
    expensesByCategory: { category: string; total: number }[];
    recentTransactions: Transaction[];
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
// Period Selector
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
    router.get('/dashboard', { period }, { preserveScroll: true, replace: true });
}

// -------------------------------------------------------
// Line Chart — Sales Trend
// -------------------------------------------------------
const lineChartData = computed(() => {
    const labels = props.salesTrend.map((d) => {
        const date = new Date(`${d.date}T00:00:00`);

        return new Intl.DateTimeFormat('en-PH', { month: 'short', day: 'numeric' }).format(date);
    });

    return {
        labels,
        datasets: [
            {
                label: 'Total Sales',
                data: props.salesTrend.map((d) => d.total_sales),
                borderColor: 'hsl(221, 83%, 53%)',
                backgroundColor: 'hsla(221, 83%, 53%, 0.08)',
                fill: true,
                tension: 0.4,
                pointRadius: 3,
                pointBackgroundColor: 'hsl(221, 83%, 53%)',
            },
            {
                label: 'DDN Earnings',
                data: props.salesTrend.map((d) => d.total_ddn),
                borderColor: 'hsl(142, 71%, 45%)',
                backgroundColor: 'hsla(142, 71%, 45%, 0.08)',
                fill: true,
                tension: 0.4,
                pointRadius: 3,
                pointBackgroundColor: 'hsl(142, 71%, 45%)',
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
                borderRadius: 2,
                useBorderRadius: true,
                font: { size: 12 },
            },
        },
        tooltip: {
            callbacks: {
                label: (ctx: any) => ` ${formatPhp(ctx.raw)}`,
            },
        },
    },
    scales: {
        x: {
            grid: { display: false },
            ticks: { font: { size: 11 } },
        },
        y: {
            grid: { color: 'rgba(0,0,0,0.05)' },
            ticks: {
                font: { size: 11 },
                callback: (val: any) => `₱${Number(val).toLocaleString()}`,
            },
        },
    },
};

// -------------------------------------------------------
// Donut Chart — Expenses by Category
// -------------------------------------------------------
const PALETTE = [
    'hsl(221, 83%, 53%)',
    'hsl(142, 71%, 45%)',
    'hsl(38, 92%, 50%)',
    'hsl(0, 84%, 60%)',
    'hsl(271, 81%, 56%)',
    'hsl(187, 85%, 43%)',
    'hsl(24, 95%, 53%)',
    'hsl(330, 81%, 60%)',
];

const donutChartData = computed(() => ({
    labels: props.expensesByCategory.map((e) => e.category),
    datasets: [
        {
            data: props.expensesByCategory.map((e) => e.total),
            backgroundColor: PALETTE.slice(0, props.expensesByCategory.length),
            borderWidth: 0,
            hoverOffset: 6,
        },
    ],
}));

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

// -------------------------------------------------------
// Recent Transactions — service detail helper (reused from transactions/Index)
// -------------------------------------------------------
const getServiceDetail = (tx: Transaction): string => {
    switch (tx.type) {
        case 'airline':
            return tx.provider ? `${tx.provider} (${tx.pax ?? 1} pax)` : `${tx.pax ?? 1} pax`;
        case 'psa':
            return tx.psa_document_type ? `Doc: ${tx.psa_document_type}` : 'PSA Assistance';
        case 'online_assistance':
            return tx.online_assistance_type?.name ?? 'Online Assistance';
        case 'translation':
            return 'Document Translation';
        case 'printing':
            return 'Printing Service';
        default:
            return '—';
    }
};

const hasExpenses = computed(() => props.expensesByCategory.length > 0);
const hasSalesTrend = computed(() => props.salesTrend.length > 0);
</script>

<template>
    <Head title="Dashboard" />

    <main class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
        <!-- Header -->
        <section class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-primary">Business overview</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">Dashboard</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Real-time summary of your sales and expenses.
                </p>
            </div>

            <!-- Period Selector -->
            <div class="flex flex-wrap items-center gap-1 rounded-lg border bg-muted/50 p-1">
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

        <!-- KPI Cards -->
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <!-- Total Sales -->
            <article class="rounded-lg border bg-card p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Total Sales</p>
                        <p class="mt-3 text-2xl font-semibold tracking-tight">
                            {{ formatPhp(kpis.total_sales) }}
                        </p>
                    </div>
                    <span class="rounded-md bg-secondary p-2 text-secondary-foreground">
                        <BanknoteArrowUp class="size-5" />
                    </span>
                </div>
                <div class="mt-3 flex items-center gap-3 border-t pt-3 text-xs text-muted-foreground">
                    <span class="flex items-center gap-1">
                        <span class="inline-block size-2 rounded-full bg-slate-400" />
                        Cash {{ formatPhp(kpis.total_cash) }}
                    </span>
                    <span class="flex items-center gap-1">
                        <span class="inline-block size-2 rounded-full bg-blue-500" />
                        GCash {{ formatPhp(kpis.total_gcash) }}
                    </span>
                </div>
            </article>

            <!-- DDN Earnings -->
            <article class="rounded-lg border bg-card p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">DDN Earnings</p>
                        <p class="mt-3 text-2xl font-semibold tracking-tight">
                            {{ formatPhp(kpis.total_ddn) }}
                        </p>
                    </div>
                    <span class="rounded-md bg-secondary p-2 text-secondary-foreground">
                        <TrendingUp class="size-5" />
                    </span>
                </div>
                <p class="mt-3 border-t pt-3 text-xs text-muted-foreground">
                    DDN's share of all recorded transactions
                </p>
            </article>

            <!-- Total Expenses -->
            <article class="rounded-lg border bg-card p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Total Expenses</p>
                        <p class="mt-3 text-2xl font-semibold tracking-tight">
                            {{ formatPhp(kpis.total_expenses) }}
                        </p>
                    </div>
                    <span class="rounded-md bg-muted p-2 text-muted-foreground">
                        <ReceiptText class="size-5" />
                    </span>
                </div>
                <p class="mt-3 border-t pt-3 text-xs text-muted-foreground">
                    All recorded expenses for the period
                </p>
            </article>

            <!-- Net Profit -->
            <article
                class="rounded-lg border border-primary/20 bg-primary p-5 text-primary-foreground"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-primary-foreground/75">Net Profit</p>
                        <p class="mt-3 text-2xl font-semibold tracking-tight">
                            {{ formatPhp(kpis.net_profit) }}
                        </p>
                    </div>
                    <span class="rounded-md bg-white/15 p-2">
                        <WalletCards class="size-5" />
                    </span>
                </div>
                <p class="mt-3 border-t border-white/20 pt-3 text-xs text-primary-foreground/75">
                    DDN Earnings minus Total Expenses
                </p>
            </article>

            <!-- Cash -->
            <article class="rounded-lg border bg-card p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Cash Collections</p>
                        <p class="mt-3 text-2xl font-semibold tracking-tight">
                            {{ formatPhp(kpis.total_cash) }}
                        </p>
                    </div>
                    <span class="rounded-md bg-muted p-2 text-muted-foreground">
                        <Receipt class="size-5" />
                    </span>
                </div>
                <p class="mt-3 border-t pt-3 text-xs text-muted-foreground">
                    Transactions paid in cash
                </p>
            </article>

            <!-- GCash -->
            <article class="rounded-lg border bg-card p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">GCash Collections</p>
                        <p class="mt-3 text-2xl font-semibold tracking-tight">
                            {{ formatPhp(kpis.total_gcash) }}
                        </p>
                    </div>
                    <span class="rounded-md bg-blue-50 p-2 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                        <WalletCards class="size-5" />
                    </span>
                </div>
                <p class="mt-3 border-t pt-3 text-xs text-muted-foreground">
                    Transactions paid via GCash
                </p>
            </article>
        </section>

        <!-- Charts Row -->
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
                        <Line :data="lineChartData" :options="lineChartOptions" />
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
                    <CardTitle class="text-base">Expenses by Category</CardTitle>
                    <p class="text-sm text-muted-foreground">Breakdown for the period</p>
                </CardHeader>
                <CardContent>
                    <div v-if="hasExpenses" class="flex flex-col gap-4">
                        <div class="mx-auto h-44 w-44">
                            <Doughnut :data="donutChartData" :options="donutChartOptions" />
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
                                        :style="{ backgroundColor: PALETTE[idx] }"
                                    />
                                    <span class="text-muted-foreground">{{ item.category }}</span>
                                </span>
                                <span class="font-medium">{{ formatPhp(item.total) }}</span>
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

        <!-- Recent Transactions -->
        <Card>
            <CardHeader class="flex flex-row items-center justify-between gap-4">
                <div>
                    <CardTitle class="text-base">Recent Transactions</CardTitle>
                    <p class="mt-0.5 text-sm text-muted-foreground">Latest 5 recorded transactions</p>
                </div>
                <Button variant="ghost" size="sm" as-child>
                    <a href="/transactions" class="flex items-center gap-1.5 text-xs">
                        View all <ArrowRight class="size-3.5" />
                    </a>
                </Button>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="recentTransactions.length > 0" class="divide-y">
                    <a
                        v-for="tx in recentTransactions"
                        :key="tx.id"
                        :href="transactionShow({ transaction: tx.id })"
                        class="flex items-center justify-between gap-4 px-6 py-3.5 transition-colors hover:bg-muted/40"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <p class="truncate text-sm font-medium">{{ tx.customer_name }}</p>
                                <Badge variant="outline" class="hidden shrink-0 text-xs sm:inline-flex">
                                    {{ formatTransactionType(tx.type) }}
                                </Badge>
                            </div>
                            <p class="mt-0.5 truncate text-xs text-muted-foreground">
                                {{ getServiceDetail(tx) }} · {{ formatTransactionDate(tx.transaction_date) }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold">{{ formatPhp(tx.amount) }}</p>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                {{ tx.payment_method === 'cash' ? 'Cash' : 'GCash' }}
                            </p>
                        </div>
                    </a>
                </div>
                <div v-else class="flex h-32 items-center justify-center">
                    <p class="text-sm text-muted-foreground">No transactions recorded yet.</p>
                </div>
            </CardContent>
        </Card>
    </main>
</template>
