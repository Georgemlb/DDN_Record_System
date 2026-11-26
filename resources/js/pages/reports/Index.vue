<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { BarChart3, Wallet } from '@lucide/vue';
import { ref, watch } from 'vue';
import type {
    DailySettlementsPageData,
    FinancialReportsPageData,
} from '@/types/report';
import DailySettlementsTab from './components/DailySettlementsTab.vue';
import FinancialReportTab from './components/FinancialReportTab.vue';

const props = defineProps<{
    currentTab: 'daily-settlements' | 'financial-reports';
    dailySettlements: DailySettlementsPageData;
    financialReports: FinancialReportsPageData;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Reports & Settlements',
                href: '/reports',
            },
        ],
    },
});

type TabKey = 'daily-settlements' | 'financial-reports';

const activeTab = ref<TabKey>(props.currentTab || 'daily-settlements');

const tabs = [
    {
        key: 'daily-settlements' as TabKey,
        label: 'Daily Cash Settlements',
        icon: Wallet,
    },
    {
        key: 'financial-reports' as TabKey,
        label: 'Financial Reports & Analytics',
        icon: BarChart3,
    },
];

const switchTab = (key: TabKey) => {
    activeTab.value = key;
    router.get(
        '/reports',
        {
            tab: key,
            period: props.financialReports.period,
            date_from:
                props.financialReports.period === 'custom'
                    ? props.financialReports.startDate
                    : undefined,
            date_to:
                props.financialReports.period === 'custom'
                    ? props.financialReports.endDate
                    : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

watch(
    () => props.currentTab,
    (newTab) => {
        if (newTab && newTab !== activeTab.value) {
            activeTab.value = newTab;
        }
    },
);
</script>

<template>
    <Head title="Reports & Daily Settlements" />

    <main class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
        <section
            class="flex flex-wrap items-start justify-between gap-4 print:hidden"
        >
            <div>
                <p class="text-sm font-medium text-primary">
                    End-of-Day Reconciliation & Analytics
                </p>
                <h1
                    class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl"
                >
                    Reports & Settlements
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Manage daily cash register floats, close daily shifts, and
                    analyze business profitability.
                </p>
            </div>
        </section>

        <section class="flex border-b border-border print:hidden">
            <div class="inline-flex gap-2 pb-px">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    @click="switchTab(tab.key)"
                    :class="[
                        'flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
                        activeTab === tab.key
                            ? 'border-primary text-primary'
                            : 'border-transparent text-muted-foreground hover:border-muted-foreground/30 hover:text-foreground',
                    ]"
                >
                    <component :is="tab.icon" class="size-4" />
                    <span>{{ tab.label }}</span>
                </button>
            </div>
        </section>

        <DailySettlementsTab
            v-if="activeTab === 'daily-settlements'"
            :daily-settlements="dailySettlements"
        />

        <FinancialReportTab
            v-if="activeTab === 'financial-reports'"
            :financial-reports="financialReports"
        />
    </main>
</template>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #printable-slip,
    #printable-slip * {
        visibility: visible;
    }
    #printable-slip {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
}
</style>
