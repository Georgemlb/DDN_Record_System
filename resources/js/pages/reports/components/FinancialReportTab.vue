<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Banknote,
    Calendar,
    Download,
    Receipt,
    TrendingDown,
    TrendingUp,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { formatPeriodLabel } from '@/lib/reportFormat';
import {
    formatPhp,
    formatTransactionDate,
    formatTransactionType,
} from '@/lib/transactionFormat';
import type { FinancialReportsPageData, ReportPeriod } from '@/types/report';

const props = defineProps<{
    financialReports: FinancialReportsPageData;
}>();

const selectedPeriod = ref<ReportPeriod>(props.financialReports.period);
const dateFrom = ref(props.financialReports.startDate);
const dateTo = ref(props.financialReports.endDate);

const periods: { key: ReportPeriod; label: string }[] = [
    { key: 'today', label: 'Today' },
    { key: 'yesterday', label: 'Yesterday' },
    { key: 'this_week', label: 'This Week' },
    { key: 'this_month', label: 'This Month' },
    { key: 'last_month', label: 'Last Month' },
    { key: 'this_year', label: 'This Year' },
    { key: 'custom', label: 'Custom Range' },
];

const applyFinancialPeriod = (period: ReportPeriod) => {
    selectedPeriod.value = period;

    if (period !== 'custom') {
        router.get(
            '/reports',
            {
                tab: 'financial-reports',
                period,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }
};

const applyCustomDates = () => {
    router.get(
        '/reports',
        {
            tab: 'financial-reports',
            period: 'custom',
            date_from: dateFrom.value,
            date_to: dateTo.value,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const downloadCsv = () => {
    const params = new URLSearchParams({
        period: selectedPeriod.value,
    });

    if (selectedPeriod.value === 'custom') {
        params.append('date_from', dateFrom.value);
        params.append('date_to', dateTo.value);
    }

    window.location.href = `/reports/export?${params.toString()}`;
};

const totalDdnShareSum = computed(() => {
    return props.financialReports.servicesBreakdown.reduce(
        (sum, item) => sum + item.total_ddn,
        0,
    );
});

const calculateServiceSharePct = (ddnAmount: number): string => {
    if (totalDdnShareSum.value <= 0) {
        return '0%';
    }

    const pct = (ddnAmount / totalDdnShareSum.value) * 100;

    return `${pct.toFixed(1)}%`;
};

const calculateExpenseSharePct = (amount: number): string => {
    const totalExp = props.financialReports.summary.total_expenses;

    if (totalExp <= 0) {
        return '0%';
    }

    const pct = (amount / totalExp) * 100;

    return `${pct.toFixed(1)}%`;
};
</script>

<template>
    <section class="space-y-6 print:hidden">
        <Card class="p-4">
            <div
                class="flex flex-col items-start justify-between gap-4 lg:flex-row lg:items-center"
            >
                <div class="flex flex-wrap items-center gap-1.5">
                    <Button
                        v-for="p in periods"
                        :key="p.key"
                        :variant="
                            selectedPeriod === p.key ? 'default' : 'ghost'
                        "
                        size="sm"
                        class="h-8 px-3 text-xs"
                        @click="applyFinancialPeriod(p.key)"
                    >
                        {{ p.label }}
                    </Button>
                </div>

                <div
                    class="flex w-full flex-wrap items-center justify-end gap-3 lg:w-auto"
                >
                    <div
                        v-if="selectedPeriod === 'custom'"
                        class="flex items-center gap-2"
                    >
                        <Input
                            v-model="dateFrom"
                            type="date"
                            class="h-8 w-36 text-xs"
                        />
                        <span class="text-xs text-muted-foreground">to</span>
                        <Input
                            v-model="dateTo"
                            type="date"
                            class="h-8 w-36 text-xs"
                        />
                        <Button
                            size="sm"
                            class="h-8 text-xs"
                            @click="applyCustomDates"
                        >
                            Apply
                        </Button>
                    </div>

                    <Button
                        variant="outline"
                        size="sm"
                        class="h-8 text-xs"
                        @click="downloadCsv"
                    >
                        <Download class="mr-1.5 size-3.5" />
                        Export CSV
                    </Button>
                </div>
            </div>

            <div
                class="mt-3 flex items-center gap-2 border-t pt-2 text-xs text-muted-foreground"
            >
                <Calendar class="size-3.5" />
                <span>Report Period:</span>
                <span class="font-medium text-foreground">
                    {{ formatTransactionDate(financialReports.startDate) }}
                    —
                    {{ formatTransactionDate(financialReports.endDate) }}
                </span>
                <span class="text-muted-foreground/60">
                    ({{ formatPeriodLabel(financialReports.period) }})
                </span>
            </div>
        </Card>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-lg border bg-card p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-medium text-muted-foreground">
                            Total Gross Sales
                        </p>
                        <p class="mt-2 text-2xl font-bold tracking-tight">
                            {{
                                formatPhp(financialReports.summary.total_sales)
                            }}
                        </p>
                    </div>
                    <span
                        class="rounded-md bg-secondary p-2 text-secondary-foreground"
                    >
                        <Receipt class="size-5" />
                    </span>
                </div>
                <div
                    class="mt-3 flex items-center justify-between border-t pt-3 text-xs text-muted-foreground"
                >
                    <span>
                        Cash:
                        <span class="font-medium text-foreground">{{
                            formatPhp(financialReports.summary.cash_sales)
                        }}</span>
                    </span>
                    <span>
                        GCash:
                        <span
                            class="font-medium text-blue-600 dark:text-blue-400"
                            >{{
                                formatPhp(financialReports.summary.gcash_sales)
                            }}</span
                        >
                    </span>
                </div>
            </article>

            <article class="rounded-lg border bg-card p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-medium text-muted-foreground">
                            DDN Commission Share
                        </p>
                        <p
                            class="mt-2 text-2xl font-bold tracking-tight text-primary"
                        >
                            {{ formatPhp(financialReports.summary.total_ddn) }}
                        </p>
                    </div>
                    <span class="rounded-md bg-primary/10 p-2 text-primary">
                        <TrendingUp class="size-5" />
                    </span>
                </div>
                <p class="mt-3 border-t pt-3 text-xs text-muted-foreground">
                    Net revenue earned by DDN across
                    {{ financialReports.summary.transaction_count }}
                    transactions
                </p>
            </article>

            <article class="rounded-lg border bg-card p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-medium text-muted-foreground">
                            Operating Expenses
                        </p>
                        <p
                            class="mt-2 text-2xl font-bold tracking-tight text-rose-600 dark:text-rose-400"
                        >
                            {{
                                formatPhp(
                                    financialReports.summary
                                        .operating_expenses ??
                                        financialReports.summary.total_expenses,
                                )
                            }}
                        </p>
                    </div>
                    <span
                        class="rounded-md bg-rose-500/10 p-2 text-rose-600 dark:text-rose-400"
                    >
                        <TrendingDown class="size-5" />
                    </span>
                </div>
                <div
                    class="mt-3 flex items-center justify-between border-t pt-3 text-xs text-muted-foreground"
                >
                    <span>
                        Cash:
                        {{ formatPhp(financialReports.summary.cash_expenses) }}
                    </span>
                    <span
                        v-if="
                            financialReports.summary.portal_reloads &&
                            financialReports.summary.portal_reloads > 0
                        "
                        class="rounded bg-sky-100 px-1 py-0.5 text-[10px] font-semibold text-sky-800 dark:bg-sky-950/60 dark:text-sky-300"
                    >
                        +{{
                            formatPhp(financialReports.summary.portal_reloads)
                        }}
                        reloads
                    </span>
                    <span v-else>
                        GCash:
                        {{ formatPhp(financialReports.summary.gcash_expenses) }}
                    </span>
                </div>
            </article>

            <article
                :class="[
                    'rounded-lg border p-5',
                    financialReports.summary.net_profit >= 0
                        ? 'border-emerald-500/30 bg-emerald-50/20 dark:bg-emerald-950/20'
                        : 'border-rose-500/30 bg-rose-50/20 dark:bg-rose-950/20',
                ]"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-medium text-muted-foreground">
                            Net Operating Income
                        </p>
                        <p
                            :class="[
                                'mt-2 text-2xl font-bold tracking-tight',
                                financialReports.summary.net_profit >= 0
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : 'text-rose-600 dark:text-rose-400',
                            ]"
                        >
                            {{ formatPhp(financialReports.summary.net_profit) }}
                        </p>
                    </div>
                    <span
                        :class="[
                            'rounded-md p-2',
                            financialReports.summary.net_profit >= 0
                                ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                                : 'bg-rose-500/15 text-rose-600 dark:text-rose-400',
                        ]"
                    >
                        <Banknote class="size-5" />
                    </span>
                </div>
                <p class="mt-3 border-t pt-3 text-xs text-muted-foreground">
                    DDN Commission Share minus Operating Expenses
                </p>
            </article>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader class="pb-3">
                    <CardTitle class="text-base font-semibold">
                        Revenue by Service Type
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr
                                    class="border-y bg-muted/50 text-left text-xs text-muted-foreground"
                                >
                                    <th class="p-3 font-medium">Service</th>
                                    <th class="p-3 text-center font-medium">
                                        Txns
                                    </th>
                                    <th class="p-3 text-right font-medium">
                                        Gross Sales
                                    </th>
                                    <th class="p-3 text-right font-medium">
                                        DDN Share
                                    </th>
                                    <th class="p-3 text-right font-medium">
                                        % Share
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr
                                    v-if="
                                        financialReports.servicesBreakdown
                                            .length === 0
                                    "
                                >
                                    <td
                                        colspan="5"
                                        class="p-6 text-center text-xs text-muted-foreground"
                                    >
                                        No transactions recorded in this period.
                                    </td>
                                </tr>
                                <tr
                                    v-for="item in financialReports.servicesBreakdown"
                                    :key="item.type"
                                    class="transition-colors hover:bg-muted/20"
                                >
                                    <td class="p-3 font-medium">
                                        {{ formatTransactionType(item.type) }}
                                    </td>
                                    <td
                                        class="p-3 text-center text-xs text-muted-foreground"
                                    >
                                        {{ item.count }}
                                    </td>
                                    <td
                                        class="p-3 text-right font-mono text-xs"
                                    >
                                        {{ formatPhp(item.total_sales) }}
                                    </td>
                                    <td
                                        class="p-3 text-right font-mono text-xs font-semibold text-primary"
                                    >
                                        {{ formatPhp(item.total_ddn) }}
                                    </td>
                                    <td
                                        class="p-3 text-right text-xs text-muted-foreground"
                                    >
                                        {{
                                            calculateServiceSharePct(
                                                item.total_ddn,
                                            )
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-3">
                    <CardTitle class="text-base font-semibold">
                        Expenses by Category
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr
                                    class="border-y bg-muted/50 text-left text-xs text-muted-foreground"
                                >
                                    <th class="p-3 font-medium">Category</th>
                                    <th class="p-3 text-center font-medium">
                                        Count
                                    </th>
                                    <th class="p-3 text-right font-medium">
                                        Total Amount
                                    </th>
                                    <th class="p-3 text-right font-medium">
                                        % of Total
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr
                                    v-if="
                                        financialReports.expensesBreakdown
                                            .length === 0
                                    "
                                >
                                    <td
                                        colspan="4"
                                        class="p-6 text-center text-xs text-muted-foreground"
                                    >
                                        No expenses recorded in this period.
                                    </td>
                                </tr>
                                <tr
                                    v-for="item in financialReports.expensesBreakdown"
                                    :key="item.category"
                                    class="transition-colors hover:bg-muted/20"
                                >
                                    <td class="p-3 font-medium">
                                        {{ item.category }}
                                    </td>
                                    <td
                                        class="p-3 text-center text-xs text-muted-foreground"
                                    >
                                        {{ item.count }}
                                    </td>
                                    <td
                                        class="p-3 text-right font-mono text-xs font-semibold text-rose-600 dark:text-rose-400"
                                    >
                                        {{ formatPhp(item.total) }}
                                    </td>
                                    <td
                                        class="p-3 text-right text-xs text-muted-foreground"
                                    >
                                        {{
                                            calculateExpenseSharePct(item.total)
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </section>
</template>
