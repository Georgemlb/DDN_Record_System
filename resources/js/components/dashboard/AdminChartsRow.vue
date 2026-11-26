<script setup lang="ts">
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
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatPhp } from '@/lib/transactionFormat';

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
    salesTrend?: { date: string; total_sales: number; total_ddn: number }[];
    expensesByCategory?: { category: string; total: number }[];
}>();

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
                label: (ctx: { dataset: { label?: string }; raw: unknown }) =>
                    ` ${ctx.dataset.label}: ${formatPhp(Number(ctx.raw))}`,
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
                callback: (val: string | number) => {
                    const num = Number(val);

                    return num >= 1000
                        ? `₱${(num / 1000).toFixed(0)}k`
                        : `₱${num}`;
                },
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
                label: (ctx: { label?: string; raw: unknown }) =>
                    ` ${ctx.label}: ${formatPhp(Number(ctx.raw))}`,
            },
        },
    },
};

const hasExpenses = computed(() => (props.expensesByCategory ?? []).length > 0);
const hasSalesTrend = computed(() => (props.salesTrend ?? []).length > 0);
</script>

<template>
    <section class="grid gap-4 lg:grid-cols-5">
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

        <Card class="lg:col-span-2">
            <CardHeader>
                <CardTitle class="text-base"
                    >Operating Expenses by Category</CardTitle
                >
                <p class="text-sm text-muted-foreground">
                    Operational overhead breakdown (excluding capital transfers)
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
                                        backgroundColor:
                                            PALETTE[idx % PALETTE.length],
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
