<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertCircle,
    Banknote,
    BarChart3,
    Calendar,
    CheckCircle2,
    Download,
    Eye,
    Info,
    Lock,
    Pencil,
    Printer,
    Receipt,
    RefreshCw,
    RotateCcw,
    TrendingDown,
    TrendingUp,
    Wallet,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    formatDateTime,
    formatDiscrepancy,
    formatPeriodLabel,
} from '@/lib/reportFormat';
import {
    formatPhp,
    formatTransactionDate,
    formatTransactionType,
} from '@/lib/transactionFormat';
import type {
    DailySettlement,
    DailySettlementsPageData,
    FinancialReportsPageData,
    ReportPeriod,
} from '@/types/report';

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

// --------------------------------------------------------
// Tabs Navigation
// --------------------------------------------------------
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

// --------------------------------------------------------
// Sub-feature A: Daily Cash Settlements
// --------------------------------------------------------
const isStartModalOpen = ref(false);
const isCloseModalOpen = ref(false);
const isEditModalOpen = ref(false);
const isReopenModalOpen = ref(false);
const settlementToClose = ref<DailySettlement | null>(null);
const settlementToEdit = ref<DailySettlement | null>(null);
const settlementToReopen = ref<DailySettlement | null>(null);

// Start Day Form
const startForm = useForm({
    opening_cash: props.dailySettlements.suggestedOpeningFloat || 0,
    settlement_date: props.dailySettlements.todayDate,
});

const openStartDayModal = () => {
    startForm.opening_cash = props.dailySettlements.suggestedOpeningFloat || 0;
    startForm.settlement_date = props.dailySettlements.todayDate;
    startForm.clearErrors();
    isStartModalOpen.value = true;
};

const submitStartDay = () => {
    startForm.post('/reports/settlements', {
        preserveScroll: true,
        onSuccess: () => {
            isStartModalOpen.value = false;
        },
    });
};

// Close Day Form (Handles today or any historical open record)
const closeForm = useForm({
    actual_cash: '',
    notes: '',
});

const openCloseDayModal = (settlement?: DailySettlement | null) => {
    settlementToClose.value =
        settlement || props.dailySettlements.todaySettlement || null;
    closeForm.actual_cash = '';
    closeForm.notes = '';
    closeForm.clearErrors();
    isCloseModalOpen.value = true;
};

const activeSettlementToClose = computed(() => {
    return (
        settlementToClose.value ||
        props.dailySettlements.todaySettlement ||
        null
    );
});

const currentExpectedCash = computed(() => {
    const s = activeSettlementToClose.value;

    if (!s) {
        return 0;
    }

    return typeof s.expected_cash === 'string'
        ? parseFloat(s.expected_cash)
        : s.expected_cash;
});

const liveClosingDiscrepancy = computed(() => {
    if (closeForm.actual_cash === '' || isNaN(Number(closeForm.actual_cash))) {
        return null;
    }

    const actual = parseFloat(closeForm.actual_cash);

    return actual - currentExpectedCash.value;
});

const submitCloseDay = () => {
    const s = activeSettlementToClose.value;

    if (!s) {
        return;
    }

    closeForm.put(`/reports/settlements/${s.id}/close`, {
        preserveScroll: true,
        onSuccess: () => {
            isCloseModalOpen.value = false;
            settlementToClose.value = null;
        },
    });
};

// Edit Settlement Form
const editForm = useForm({
    opening_cash: 0,
    actual_cash: '' as string | number,
    status: 'open' as 'open' | 'closed',
    notes: '',
    sync_metrics: true,
});

const openEditModal = (settlement: DailySettlement) => {
    settlementToEdit.value = settlement;
    editForm.clearErrors();
    editForm.opening_cash =
        typeof settlement.opening_cash === 'string'
            ? parseFloat(settlement.opening_cash)
            : settlement.opening_cash;
    editForm.actual_cash =
        settlement.actual_cash !== null && settlement.actual_cash !== undefined
            ? settlement.actual_cash
            : '';
    editForm.status = settlement.status as 'open' | 'closed';
    editForm.notes = settlement.notes || '';
    editForm.sync_metrics = true;
    isEditModalOpen.value = true;
};

const computedEditExpectedCash = computed(() => {
    if (!settlementToEdit.value) {
        return 0;
    }

    const opening = Number(editForm.opening_cash) || 0;
    const cashIn =
        typeof settlementToEdit.value.cash_in === 'string'
            ? parseFloat(settlementToEdit.value.cash_in)
            : settlementToEdit.value.cash_in;
    const cashOut =
        typeof settlementToEdit.value.cash_out === 'string'
            ? parseFloat(settlementToEdit.value.cash_out)
            : settlementToEdit.value.cash_out;

    return Math.round((opening + (cashIn || 0) - (cashOut || 0)) * 100) / 100;
});

const computedEditDiscrepancy = computed(() => {
    if (editForm.actual_cash === '' || isNaN(Number(editForm.actual_cash))) {
        return null;
    }

    const actual = Number(editForm.actual_cash);

    return Math.round((actual - computedEditExpectedCash.value) * 100) / 100;
});

const submitEditSettlement = () => {
    if (!settlementToEdit.value) {
        return;
    }

    editForm.put(`/reports/settlements/${settlementToEdit.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            isEditModalOpen.value = false;
            settlementToEdit.value = null;
        },
    });
};

// Reopen Modal
const openReopenModal = (settlement: DailySettlement) => {
    settlementToReopen.value = settlement;
    isReopenModalOpen.value = true;
};

const confirmReopen = () => {
    if (!settlementToReopen.value) {
        return;
    }

    router.put(
        `/reports/settlements/${settlementToReopen.value.id}/reopen`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                isReopenModalOpen.value = false;
                settlementToReopen.value = null;
            },
        },
    );
};

// Slip Viewer & Printer Modal
interface SlipData {
    settlement: DailySettlement;
    transactions: Array<{
        id: number;
        reference_number: string | null;
        customer_name: string;
        type: string;
        payment_method: string;
        amount: number | string;
        ddn_amount: number | string | null;
    }>;
    expenses: Array<{
        id: number;
        title: string;
        payment_method: string;
        amount: number | string;
        category?: { id: number; name: string } | null;
    }>;
}

const isSlipModalOpen = ref(false);
const isLoadingSlip = ref(false);
const slipData = ref<SlipData | null>(null);

const openSlipModal = async (settlementId: number) => {
    isLoadingSlip.value = true;
    isSlipModalOpen.value = true;

    try {
        const response = await fetch(`/reports/settlements/${settlementId}`);

        if (!response.ok) {
            throw new Error('Failed to load settlement slip.');
        }

        slipData.value = await response.json();
    } catch {
        slipData.value = null;
    } finally {
        isLoadingSlip.value = false;
    }
};

const printSlip = () => {
    window.print();
};

// --------------------------------------------------------
// Sub-feature B: Financial Reports & Analytics
// --------------------------------------------------------
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

// Helpers for calculations & breakdowns
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
    <Head title="Reports & Settlements" />

    <main class="flex flex-1 flex-col gap-6 p-4 sm:p-6 print:p-0">
        <!-- Top Title & Navigation (Hidden on Print) -->
        <section
            class="flex flex-wrap items-start justify-between gap-4 print:hidden"
        >
            <div>
                <p class="text-sm font-medium text-primary">
                    Financial Auditing & Reconciliation
                </p>
                <h1
                    class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl"
                >
                    Reports & Daily Settlements
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Reconcile physical cash registers, audit daily collections,
                    and generate financial reports.
                </p>
            </div>
        </section>

        <!-- Segmented Tab Navigation (Hidden on Print) -->
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

        <!-- ============================================== -->
        <!-- TAB 1: DAILY CASH SETTLEMENTS                  -->
        <!-- ============================================== -->
        <section
            v-if="activeTab === 'daily-settlements'"
            class="space-y-6 print:hidden"
        >
            <!-- TODAY'S REGISTER STATUS CARD -->
            <Card class="overflow-hidden border-2 shadow-sm">
                <!-- Case 1: Register NOT yet opened today -->
                <div
                    v-if="!dailySettlements.todaySettlement"
                    class="p-6 sm:p-8"
                >
                    <div
                        class="flex flex-col items-start justify-between gap-6 sm:flex-row sm:items-center"
                    >
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <Badge
                                    variant="outline"
                                    class="border-amber-500/50 bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300"
                                >
                                    <AlertCircle class="mr-1 size-3.5" />
                                    Register Not Opened Yet
                                </Badge>
                                <span class="text-xs text-muted-foreground">
                                    {{
                                        formatTransactionDate(
                                            dailySettlements.todayDate,
                                        )
                                    }}
                                </span>
                            </div>
                            <h2 class="text-xl font-semibold tracking-tight">
                                Today's Daily Cash Register
                            </h2>
                            <p class="max-w-xl text-sm text-muted-foreground">
                                Set the opening cash float to begin tracking
                                cash in, cash disbursements, and expected drawer
                                balance for today.
                            </p>
                        </div>

                        <div class="flex flex-col items-end gap-2">
                            <Button
                                size="lg"
                                class="shadow-sm"
                                @click="openStartDayModal"
                            >
                                <Lock class="mr-2 size-4" />
                                Open Today's Register
                            </Button>
                            <p class="text-xs text-muted-foreground">
                                Suggested Float:
                                <span class="font-semibold text-foreground">{{
                                    formatPhp(
                                        dailySettlements.suggestedOpeningFloat,
                                    )
                                }}</span>
                            </p>
                        </div>
                    </div>

                    <!-- Live Unreconciled Activity Preview -->
                    <div
                        v-if="dailySettlements.todayDraftMetrics"
                        class="mt-6 rounded-lg border bg-muted/40 p-4"
                    >
                        <div
                            class="mb-3 flex items-center justify-between text-xs font-medium text-muted-foreground"
                        >
                            <span class="flex items-center gap-1.5">
                                <Info class="size-3.5" />
                                Recorded Activity Today (Pending Opening Float)
                            </span>
                            <span>{{
                                formatTransactionDate(
                                    dailySettlements.todayDate,
                                )
                            }}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Cash In (Sales)
                                </p>
                                <p
                                    class="text-base font-semibold text-emerald-600 dark:text-emerald-400"
                                >
                                    +{{
                                        formatPhp(
                                            dailySettlements.todayDraftMetrics
                                                .cash_in,
                                        )
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Cash Out (Expenses)
                                </p>
                                <p
                                    class="text-base font-semibold text-rose-600 dark:text-rose-400"
                                >
                                    -{{
                                        formatPhp(
                                            dailySettlements.todayDraftMetrics
                                                .cash_out,
                                        )
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Net Cash Flow
                                </p>
                                <p class="text-base font-semibold">
                                    {{
                                        formatPhp(
                                            dailySettlements.todayDraftMetrics
                                                .cash_in -
                                                dailySettlements
                                                    .todayDraftMetrics.cash_out,
                                        )
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    GCash Collections
                                </p>
                                <p
                                    class="text-base font-semibold text-blue-600 dark:text-blue-400"
                                >
                                    {{
                                        formatPhp(
                                            dailySettlements.todayDraftMetrics
                                                .total_gcash,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Case 2: Register is OPEN -->
                <div
                    v-else-if="
                        dailySettlements.todaySettlement.status === 'open'
                    "
                    class="p-6 sm:p-8"
                >
                    <div
                        class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center"
                    >
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="relative flex size-2.5">
                                    <span
                                        class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"
                                    />
                                    <span
                                        class="relative inline-flex size-2.5 rounded-full bg-emerald-500"
                                    />
                                </span>
                                <Badge
                                    class="bg-emerald-500/15 font-semibold text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300"
                                >
                                    Register Open & Active
                                </Badge>
                                <span class="text-xs text-muted-foreground">
                                    Opened at
                                    {{
                                        formatDateTime(
                                            dailySettlements.todaySettlement
                                                .opened_at,
                                        )
                                    }}
                                    by
                                    {{
                                        dailySettlements.todaySettlement.user
                                            ?.name ?? 'Staff'
                                    }}
                                </span>
                            </div>
                            <h2 class="text-xl font-semibold tracking-tight">
                                Today's Physical Cash Register
                            </h2>
                        </div>

                        <div class="flex items-center gap-2">
                            <Button
                                variant="destructive"
                                size="lg"
                                class="shadow-sm"
                                @click="
                                    openCloseDayModal(
                                        dailySettlements.todaySettlement,
                                    )
                                "
                            >
                                <CheckCircle2 class="mr-2 size-4" />
                                Close Register (End of Day)
                            </Button>
                            <Button
                                variant="outline"
                                size="lg"
                                class="shadow-sm"
                                @click="
                                    openEditModal(
                                        dailySettlements.todaySettlement,
                                    )
                                "
                            >
                                <Pencil class="mr-2 size-4" />
                                Edit
                            </Button>
                        </div>
                    </div>

                    <!-- Live Cash Drawer Calculation Cards -->
                    <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                        <div class="rounded-lg border bg-card p-4">
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                1. Opening Cash Float
                            </p>
                            <p
                                class="mt-2 text-xl font-semibold tracking-tight"
                            >
                                {{
                                    formatPhp(
                                        dailySettlements.todaySettlement
                                            .opening_cash,
                                    )
                                }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Starting float in drawer
                            </p>
                        </div>

                        <div class="rounded-lg border bg-card p-4">
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                2. Cash In (Sales)
                            </p>
                            <p
                                class="mt-2 text-xl font-semibold tracking-tight text-emerald-600 dark:text-emerald-400"
                            >
                                +{{
                                    formatPhp(
                                        dailySettlements.todaySettlement
                                            .cash_in,
                                    )
                                }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Cash transaction payments
                            </p>
                        </div>

                        <div class="rounded-lg border bg-card p-4">
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                3. Cash Out (Disbursements)
                            </p>
                            <p
                                class="mt-2 text-xl font-semibold tracking-tight text-rose-600 dark:text-rose-400"
                            >
                                -{{
                                    formatPhp(
                                        dailySettlements.todaySettlement
                                            .cash_out,
                                    )
                                }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Cash operational expenses
                            </p>
                        </div>

                        <div
                            class="rounded-lg border-2 border-primary/40 bg-primary/5 p-4"
                        >
                            <p class="text-xs font-medium text-primary">
                                4. Expected Cash in Drawer
                            </p>
                            <p
                                class="mt-2 text-2xl font-bold tracking-tight text-primary"
                            >
                                {{
                                    formatPhp(
                                        dailySettlements.todaySettlement
                                            .expected_cash,
                                    )
                                }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Float + Cash In - Cash Out
                            </p>
                        </div>
                    </div>

                    <!-- Digital Collections Strip -->
                    <div
                        class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-muted/30 px-4 py-3 text-xs"
                    >
                        <div
                            class="flex items-center gap-4 text-muted-foreground"
                        >
                            <span>Digital Collections Today:</span>
                            <span class="font-medium text-foreground">
                                GCash:
                                <span
                                    class="text-blue-600 dark:text-blue-400"
                                    >{{
                                        formatPhp(
                                            dailySettlements.todaySettlement
                                                .total_gcash,
                                        )
                                    }}</span
                                >
                            </span>
                            <span class="font-medium text-foreground">
                                Bank Transfer:
                                <span>{{
                                    formatPhp(
                                        dailySettlements.todaySettlement
                                            .total_bank_transfer,
                                    )
                                }}</span>
                            </span>
                        </div>
                        <div class="text-muted-foreground">
                            Total Gross Sales Today:
                            <span class="font-semibold text-foreground">{{
                                formatPhp(
                                    dailySettlements.todaySettlement
                                        .total_sales,
                                )
                            }}</span>
                        </div>
                    </div>
                </div>

                <!-- Case 3: Register is CLOSED for today -->
                <div v-else class="p-6 sm:p-8">
                    <div
                        class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center"
                    >
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <Badge
                                    variant="secondary"
                                    class="border font-medium"
                                >
                                    <Lock class="mr-1 size-3.5" />
                                    Register Closed & Reconciled
                                </Badge>
                                <span class="text-xs text-muted-foreground">
                                    Closed at
                                    {{
                                        formatDateTime(
                                            dailySettlements.todaySettlement
                                                .closed_at,
                                        )
                                    }}
                                    by
                                    {{
                                        dailySettlements.todaySettlement
                                            .closed_by_user?.name ?? 'Admin'
                                    }}
                                </span>
                            </div>
                            <h2 class="text-xl font-semibold tracking-tight">
                                Today's Cash Settlement Complete
                            </h2>
                        </div>

                        <div class="flex items-center gap-2">
                            <Button
                                variant="outline"
                                @click="
                                    openSlipModal(
                                        dailySettlements.todaySettlement.id,
                                    )
                                "
                            >
                                <Eye class="mr-1.5 size-4" />
                                View & Print Slip
                            </Button>
                            <Button
                                variant="outline"
                                @click="
                                    openEditModal(
                                        dailySettlements.todaySettlement,
                                    )
                                "
                            >
                                <Pencil class="mr-1.5 size-4" />
                                Edit
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="text-xs text-muted-foreground hover:text-foreground"
                                @click="
                                    openReopenModal(
                                        dailySettlements.todaySettlement,
                                    )
                                "
                            >
                                <RotateCcw class="mr-1 size-3.5" />
                                Reopen
                            </Button>
                        </div>
                    </div>

                    <!-- Final Reconciliation Figures -->
                    <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div class="rounded-lg border bg-card p-4">
                            <p class="text-xs text-muted-foreground">
                                Opening Float
                            </p>
                            <p class="mt-1 text-lg font-semibold">
                                {{
                                    formatPhp(
                                        dailySettlements.todaySettlement
                                            .opening_cash,
                                    )
                                }}
                            </p>
                        </div>
                        <div class="rounded-lg border bg-card p-4">
                            <p class="text-xs text-muted-foreground">
                                Expected Cash
                            </p>
                            <p class="mt-1 text-lg font-semibold">
                                {{
                                    formatPhp(
                                        dailySettlements.todaySettlement
                                            .expected_cash,
                                    )
                                }}
                            </p>
                        </div>
                        <div class="rounded-lg border bg-card p-4">
                            <p class="text-xs text-muted-foreground">
                                Actual Cash Counted
                            </p>
                            <p class="mt-1 text-lg font-semibold text-primary">
                                {{
                                    formatPhp(
                                        dailySettlements.todaySettlement
                                            .actual_cash,
                                    )
                                }}
                            </p>
                        </div>
                        <div class="rounded-lg border bg-card p-4">
                            <p class="text-xs text-muted-foreground">
                                Discrepancy (Over/Short)
                            </p>
                            <div class="mt-1">
                                <Badge
                                    :variant="
                                        formatDiscrepancy(
                                            dailySettlements.todaySettlement
                                                .discrepancy,
                                        ).variant
                                    "
                                    class="text-xs"
                                >
                                    {{
                                        formatDiscrepancy(
                                            dailySettlements.todaySettlement
                                                .discrepancy,
                                        ).label
                                    }}
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="dailySettlements.todaySettlement.notes"
                        class="mt-4 rounded-md border bg-muted/30 p-3 text-xs text-muted-foreground"
                    >
                        <span class="font-medium text-foreground">Notes:</span>
                        {{ dailySettlements.todaySettlement.notes }}
                    </div>
                </div>
            </Card>

            <!-- SETTLEMENT HISTORY TABLE -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold tracking-tight">
                            Settlement History
                        </h3>
                        <p class="text-sm text-muted-foreground">
                            Audit log of previous daily registers, cash drawer
                            counts, and discrepancies.
                        </p>
                    </div>
                </div>

                <Card>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr
                                    class="border-b bg-muted/50 text-left text-muted-foreground"
                                >
                                    <th class="p-4 font-medium">Date</th>
                                    <th class="p-4 font-medium">Status</th>
                                    <th class="p-4 text-right font-medium">
                                        Opening Float
                                    </th>
                                    <th class="p-4 text-right font-medium">
                                        Expected Cash
                                    </th>
                                    <th class="p-4 text-right font-medium">
                                        Actual Counted
                                    </th>
                                    <th class="p-4 text-center font-medium">
                                        Discrepancy
                                    </th>
                                    <th class="p-4 font-medium">
                                        Cashier / Closed By
                                    </th>
                                    <th class="p-4 text-right font-medium">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr
                                    v-if="
                                        dailySettlements.history.data.length ===
                                        0
                                    "
                                >
                                    <td
                                        colspan="8"
                                        class="p-8 text-center text-muted-foreground"
                                    >
                                        No settlement history records found.
                                    </td>
                                </tr>
                                <tr
                                    v-for="item in dailySettlements.history
                                        .data"
                                    :key="item.id"
                                    class="transition-colors hover:bg-muted/30"
                                >
                                    <td class="p-4 font-medium">
                                        {{
                                            formatTransactionDate(
                                                item.settlement_date,
                                            )
                                        }}
                                    </td>
                                    <td class="p-4">
                                        <Badge
                                            :variant="
                                                item.status === 'open'
                                                    ? 'default'
                                                    : 'secondary'
                                            "
                                            class="text-xs capitalize"
                                        >
                                            {{ item.status }}
                                        </Badge>
                                    </td>
                                    <td class="p-4 text-right font-mono">
                                        {{ formatPhp(item.opening_cash) }}
                                    </td>
                                    <td class="p-4 text-right font-mono">
                                        {{ formatPhp(item.expected_cash) }}
                                    </td>
                                    <td
                                        class="p-4 text-right font-mono font-medium"
                                    >
                                        {{
                                            item.actual_cash !== null
                                                ? formatPhp(item.actual_cash)
                                                : '—'
                                        }}
                                    </td>
                                    <td class="p-4 text-center">
                                        <Badge
                                            v-if="item.status === 'closed'"
                                            :variant="
                                                formatDiscrepancy(
                                                    item.discrepancy,
                                                ).variant
                                            "
                                            class="text-xs"
                                        >
                                            {{
                                                formatDiscrepancy(
                                                    item.discrepancy,
                                                ).label
                                            }}
                                        </Badge>
                                        <span
                                            v-else
                                            class="text-xs text-muted-foreground"
                                            >In Progress</span
                                        >
                                    </td>
                                    <td
                                        class="p-4 text-xs text-muted-foreground"
                                    >
                                        <div>
                                            {{ item.user?.name ?? '—' }}
                                        </div>
                                        <div
                                            v-if="item.closed_by_user"
                                            class="text-foreground"
                                        >
                                            Closed:
                                            {{ item.closed_by_user.name }}
                                        </div>
                                    </td>
                                    <td class="p-4 text-right">
                                        <div
                                            class="flex items-center justify-end gap-1.5"
                                        >
                                            <Button
                                                v-if="item.status === 'open'"
                                                variant="default"
                                                size="sm"
                                                class="h-8 bg-emerald-600 px-2.5 text-xs text-white hover:bg-emerald-700 dark:bg-emerald-600 dark:hover:bg-emerald-700"
                                                @click="openCloseDayModal(item)"
                                            >
                                                <CheckCircle2
                                                    class="mr-1 size-3.5"
                                                />
                                                Close
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                class="h-8 px-2 text-xs"
                                                @click="openEditModal(item)"
                                            >
                                                <Pencil class="mr-1 size-3.5" />
                                                Edit
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                class="h-8 px-2 text-xs"
                                                @click="openSlipModal(item.id)"
                                            >
                                                <Eye class="mr-1 size-3.5" />
                                                Slip
                                            </Button>
                                            <Button
                                                v-if="item.status === 'closed'"
                                                variant="ghost"
                                                size="sm"
                                                class="h-8 px-2 text-xs text-muted-foreground hover:text-foreground"
                                                @click="openReopenModal(item)"
                                            >
                                                <RotateCcw
                                                    class="mr-1 size-3.5"
                                                />
                                                Reopen
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <Pagination :links="dailySettlements.history.links" />
                </Card>
            </div>
        </section>

        <!-- ============================================== -->
        <!-- TAB 2: FINANCIAL REPORTS & ANALYTICS           -->
        <!-- ============================================== -->
        <section
            v-if="activeTab === 'financial-reports'"
            class="space-y-6 print:hidden"
        >
            <!-- Period Filters & Export Bar -->
            <Card class="p-4">
                <div
                    class="flex flex-col items-start justify-between gap-4 lg:flex-row lg:items-center"
                >
                    <!-- Period Presets -->
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

                    <!-- Custom Range & CSV Export -->
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
                            <span class="text-xs text-muted-foreground"
                                >to</span
                            >
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

            <!-- KPI Metric Cards -->
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <!-- Total Gross Sales -->
                <article class="rounded-lg border bg-card p-5">
                    <div class="flex items-start justify-between">
                        <div>
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Total Gross Sales
                            </p>
                            <p class="mt-2 text-2xl font-bold tracking-tight">
                                {{
                                    formatPhp(
                                        financialReports.summary.total_sales,
                                    )
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
                                    formatPhp(
                                        financialReports.summary.gcash_sales,
                                    )
                                }}</span
                            >
                        </span>
                    </div>
                </article>

                <!-- Total DDN Share (Gross Margin) -->
                <article class="rounded-lg border bg-card p-5">
                    <div class="flex items-start justify-between">
                        <div>
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                DDN Commission Share
                            </p>
                            <p
                                class="mt-2 text-2xl font-bold tracking-tight text-primary"
                            >
                                {{
                                    formatPhp(
                                        financialReports.summary.total_ddn,
                                    )
                                }}
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

                <!-- Operating Expenses -->
                <article class="rounded-lg border bg-card p-5">
                    <div class="flex items-start justify-between">
                        <div>
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Operating Expenses
                            </p>
                            <p
                                class="mt-2 text-2xl font-bold tracking-tight text-rose-600 dark:text-rose-400"
                            >
                                {{
                                    formatPhp(
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
                            {{
                                formatPhp(
                                    financialReports.summary.cash_expenses,
                                )
                            }}
                        </span>
                        <span>
                            GCash:
                            {{
                                formatPhp(
                                    financialReports.summary.gcash_expenses,
                                )
                            }}
                        </span>
                    </div>
                </article>

                <!-- Net Operating Profit -->
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
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
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
                                {{
                                    formatPhp(
                                        financialReports.summary.net_profit,
                                    )
                                }}
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

            <!-- Two-Column Breakdowns -->
            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Service Breakdown Table -->
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
                                            No transactions recorded in this
                                            period.
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="item in financialReports.servicesBreakdown"
                                        :key="item.type"
                                        class="transition-colors hover:bg-muted/20"
                                    >
                                        <td class="p-3 font-medium">
                                            {{
                                                formatTransactionType(item.type)
                                            }}
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

                <!-- Expenses Breakdown Table -->
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
                                        <th class="p-3 font-medium">
                                            Category
                                        </th>
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
                                                calculateExpenseSharePct(
                                                    item.total,
                                                )
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

        <!-- ============================================== -->
        <!-- MODAL: START DAY (OPEN REGISTER)               -->
        <!-- ============================================== -->
        <Dialog v-model:open="isStartModalOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Open Cash Register</DialogTitle>
                    <DialogDescription>
                        Enter the physical cash float in drawer for
                        {{ formatTransactionDate(dailySettlements.todayDate) }}.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitStartDay" class="space-y-4">
                    <div class="space-y-2">
                        <Label for="opening_cash">
                            Opening Cash Float (₱)
                        </Label>
                        <Input
                            id="opening_cash"
                            v-model="startForm.opening_cash"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            required
                            autofocus
                        />
                        <p class="text-xs text-muted-foreground">
                            Suggested based on previous day's closing cash:
                            <span class="font-medium text-foreground">{{
                                formatPhp(
                                    dailySettlements.suggestedOpeningFloat,
                                )
                            }}</span>
                        </p>
                        <p
                            v-if="startForm.errors.opening_cash"
                            class="text-xs text-destructive"
                        >
                            {{ startForm.errors.opening_cash }}
                        </p>
                    </div>

                    <DialogFooter class="mt-6">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isStartModalOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="startForm.processing">
                            Open Register
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- ============================================== -->
        <!-- MODAL: CLOSE DAY (EOD RECONCILIATION)          -->
        <!-- ============================================== -->
        <Dialog v-model:open="isCloseModalOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        Close Register ({{
                            activeSettlementToClose
                                ? formatTransactionDate(
                                      activeSettlementToClose.settlement_date,
                                  )
                                : 'EOD'
                        }})
                    </DialogTitle>
                    <DialogDescription>
                        Perform physical cash count and reconcile the cash
                        drawer for
                        {{
                            activeSettlementToClose
                                ? formatTransactionDate(
                                      activeSettlementToClose.settlement_date,
                                  )
                                : 'this day'
                        }}.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitCloseDay" class="space-y-4">
                    <!-- Expected Cash Display Box -->
                    <div
                        class="space-y-2 rounded-lg border bg-muted/40 p-4 text-xs"
                    >
                        <div class="flex justify-between">
                            <span class="text-muted-foreground"
                                >Opening Float:</span
                            >
                            <span class="font-mono">{{
                                formatPhp(activeSettlementToClose?.opening_cash)
                            }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground"
                                >Cash In (Sales):</span
                            >
                            <span
                                class="font-mono text-emerald-600 dark:text-emerald-400"
                                >+{{
                                    formatPhp(activeSettlementToClose?.cash_in)
                                }}</span
                            >
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground"
                                >Cash Out (Expenses):</span
                            >
                            <span
                                class="font-mono text-rose-600 dark:text-rose-400"
                                >-{{
                                    formatPhp(activeSettlementToClose?.cash_out)
                                }}</span
                            >
                        </div>
                        <div
                            class="flex justify-between border-t pt-2 text-sm font-semibold"
                        >
                            <span>Expected Cash in Drawer:</span>
                            <span class="font-mono text-primary">{{
                                formatPhp(currentExpectedCash)
                            }}</span>
                        </div>
                    </div>

                    <!-- Actual Cash Count Input -->
                    <div class="space-y-2">
                        <Label for="actual_cash">
                            Physical Cash Counted (₱)
                        </Label>
                        <Input
                            id="actual_cash"
                            v-model="closeForm.actual_cash"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="Count bills & coins in drawer"
                            required
                            autofocus
                        />
                        <p
                            v-if="closeForm.errors.actual_cash"
                            class="text-xs text-destructive"
                        >
                            {{ closeForm.errors.actual_cash }}
                        </p>
                    </div>

                    <!-- Live Discrepancy Indicator -->
                    <div
                        v-if="liveClosingDiscrepancy !== null"
                        class="flex items-center justify-between rounded-md border p-3"
                    >
                        <span class="text-xs text-muted-foreground"
                            >Discrepancy Status:</span
                        >
                        <Badge
                            :variant="
                                formatDiscrepancy(liveClosingDiscrepancy)
                                    .variant
                            "
                            class="text-xs"
                        >
                            {{
                                formatDiscrepancy(liveClosingDiscrepancy).label
                            }}
                        </Badge>
                    </div>

                    <!-- Notes -->
                    <div class="space-y-2">
                        <Label for="notes">Notes / Discrepancy Remarks</Label>
                        <textarea
                            id="notes"
                            v-model="closeForm.notes"
                            rows="3"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                            placeholder="Explain shortages, overages, or cashier handoff remarks..."
                        />
                    </div>

                    <DialogFooter class="mt-6">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isCloseModalOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="closeForm.processing"
                        >
                            Confirm & Close Register
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- ============================================== -->
        <!-- MODAL: EDIT DAILY SETTLEMENT                   -->
        <!-- ============================================== -->
        <Dialog v-model:open="isEditModalOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit Daily Settlement</DialogTitle>
                    <DialogDescription>
                        Adjust opening float, physical cash count, status, or
                        remarks for
                        {{
                            settlementToEdit
                                ? formatTransactionDate(
                                      settlementToEdit.settlement_date,
                                  )
                                : 'this day'
                        }}.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitEditSettlement" class="space-y-4">
                    <!-- Read-only Metrics Preview Box -->
                    <div
                        class="space-y-2 rounded-lg border bg-muted/40 p-4 text-xs"
                    >
                        <div class="flex justify-between">
                            <span class="text-muted-foreground"
                                >Settlement Date:</span
                            >
                            <span class="font-semibold">{{
                                settlementToEdit
                                    ? formatTransactionDate(
                                          settlementToEdit.settlement_date,
                                      )
                                    : '—'
                            }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground"
                                >Cash In (Sales):</span
                            >
                            <span
                                class="font-mono text-emerald-600 dark:text-emerald-400"
                                >+{{
                                    formatPhp(settlementToEdit?.cash_in)
                                }}</span
                            >
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground"
                                >Cash Out (Expenses):</span
                            >
                            <span
                                class="font-mono text-rose-600 dark:text-rose-400"
                                >-{{
                                    formatPhp(settlementToEdit?.cash_out)
                                }}</span
                            >
                        </div>
                        <div
                            class="flex justify-between border-t pt-2 font-semibold"
                        >
                            <span>Computed Expected Cash:</span>
                            <span class="font-mono text-primary">{{
                                formatPhp(computedEditExpectedCash)
                            }}</span>
                        </div>
                    </div>

                    <!-- Opening Cash -->
                    <div class="space-y-2">
                        <Label for="edit_opening_cash">Opening Float (₱)</Label>
                        <Input
                            id="edit_opening_cash"
                            v-model="editForm.opening_cash"
                            type="number"
                            step="0.01"
                            min="0"
                            required
                        />
                        <p
                            v-if="editForm.errors.opening_cash"
                            class="text-xs text-destructive"
                        >
                            {{ editForm.errors.opening_cash }}
                        </p>
                    </div>

                    <!-- Status Selection -->
                    <div class="space-y-2">
                        <Label for="edit_status">Settlement Status</Label>
                        <select
                            id="edit_status"
                            v-model="editForm.status"
                            class="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none"
                            required
                        >
                            <option value="open">Open (In Progress)</option>
                            <option value="closed">Closed & Reconciled</option>
                        </select>
                        <p
                            v-if="editForm.errors.status"
                            class="text-xs text-destructive"
                        >
                            {{ editForm.errors.status }}
                        </p>
                    </div>

                    <!-- Actual Cash Count -->
                    <div class="space-y-2">
                        <Label for="edit_actual_cash">
                            Actual Cash Counted (₱)
                            <span
                                v-if="editForm.status === 'open'"
                                class="text-xs font-normal text-muted-foreground"
                                >(Optional while Open)</span
                            >
                        </Label>
                        <Input
                            id="edit_actual_cash"
                            v-model="editForm.actual_cash"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="Physical cash counted"
                            :required="editForm.status === 'closed'"
                        />
                        <p
                            v-if="editForm.errors.actual_cash"
                            class="text-xs text-destructive"
                        >
                            {{ editForm.errors.actual_cash }}
                        </p>
                    </div>

                    <!-- Live Discrepancy Indicator -->
                    <div
                        v-if="computedEditDiscrepancy !== null"
                        class="flex items-center justify-between rounded-md border p-3"
                    >
                        <span class="text-xs text-muted-foreground"
                            >Discrepancy:</span
                        >
                        <Badge
                            :variant="
                                formatDiscrepancy(computedEditDiscrepancy)
                                    .variant
                            "
                            class="text-xs"
                        >
                            {{
                                formatDiscrepancy(computedEditDiscrepancy).label
                            }}
                        </Badge>
                    </div>

                    <!-- Notes -->
                    <div class="space-y-2">
                        <Label for="edit_notes">Notes / Remarks</Label>
                        <textarea
                            id="edit_notes"
                            v-model="editForm.notes"
                            rows="3"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                            placeholder="Reason for adjustment or audit remarks..."
                        />
                        <p
                            v-if="editForm.errors.notes"
                            class="text-xs text-destructive"
                        >
                            {{ editForm.errors.notes }}
                        </p>
                    </div>

                    <DialogFooter class="mt-6">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isEditModalOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="editForm.processing">
                            Save Changes
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- ============================================== -->
        <!-- MODAL: REOPEN CONFIRMATION                     -->
        <!-- ============================================== -->
        <Dialog v-model:open="isReopenModalOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Reopen Daily Register?</DialogTitle>
                    <DialogDescription>
                        Reopening will change the status back to OPEN for
                        {{
                            settlementToReopen
                                ? formatTransactionDate(
                                      settlementToReopen.settlement_date,
                                  )
                                : 'this date'
                        }}. You can record further transactions or adjust
                        expenses before closing again.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="mt-4">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isReopenModalOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button type="button" @click="confirmReopen">
                        Yes, Reopen Register
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ============================================== -->
        <!-- MODAL: PRINTABLE SETTLEMENT SLIP               -->
        <!-- ============================================== -->
        <Dialog v-model:open="isSlipModalOpen">
            <DialogContent
                class="max-h-[90vh] overflow-y-auto sm:max-w-xl print:h-auto print:w-full print:max-w-none print:overflow-visible print:border-none print:shadow-none"
            >
                <div v-if="isLoadingSlip" class="py-12 text-center">
                    <RefreshCw
                        class="mx-auto size-6 animate-spin text-muted-foreground"
                    />
                    <p class="mt-2 text-xs text-muted-foreground">
                        Loading settlement slip...
                    </p>
                </div>

                <div v-else-if="slipData" class="space-y-6">
                    <!-- Slip Printable Area -->
                    <div
                        id="printable-slip"
                        class="space-y-4 rounded-lg border bg-card p-6 print:border-none print:p-0"
                    >
                        <!-- Header -->
                        <div class="border-b pb-4 text-center">
                            <h2 class="text-lg font-bold tracking-tight">
                                DDN RECORD SYSTEM
                            </h2>
                            <p
                                class="text-xs tracking-wider text-muted-foreground uppercase"
                            >
                                Daily Cash Settlement Slip
                            </p>
                            <p class="mt-1 text-sm font-semibold">
                                {{
                                    formatTransactionDate(
                                        slipData.settlement.settlement_date,
                                    )
                                }}
                            </p>
                        </div>

                        <!-- Cashier & Timestamps -->
                        <div
                            class="grid grid-cols-2 gap-2 border-b pb-3 text-xs text-muted-foreground"
                        >
                            <div>
                                <span>Opened By: </span>
                                <span class="font-medium text-foreground">{{
                                    slipData.settlement.user?.name ?? 'Staff'
                                }}</span>
                                <div class="text-[10px]">
                                    {{
                                        formatDateTime(
                                            slipData.settlement.opened_at,
                                        )
                                    }}
                                </div>
                            </div>
                            <div class="text-right">
                                <span>Closed By: </span>
                                <span class="font-medium text-foreground">{{
                                    slipData.settlement.closed_by_user?.name ??
                                    'Admin'
                                }}</span>
                                <div class="text-[10px]">
                                    {{
                                        formatDateTime(
                                            slipData.settlement.closed_at,
                                        )
                                    }}
                                </div>
                            </div>
                        </div>

                        <!-- Physical Drawer Reconciliation -->
                        <div class="space-y-2 text-xs">
                            <p
                                class="text-[11px] font-semibold tracking-wider text-foreground uppercase"
                            >
                                Cash Drawer Reconciliation
                            </p>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground"
                                    >Opening Cash Float:</span
                                >
                                <span class="font-mono font-medium">{{
                                    formatPhp(slipData.settlement.opening_cash)
                                }}</span>
                            </div>
                            <div
                                class="flex justify-between text-emerald-600 dark:text-emerald-400"
                            >
                                <span>+ Total Cash In (Sales):</span>
                                <span class="font-mono font-medium">{{
                                    formatPhp(slipData.settlement.cash_in)
                                }}</span>
                            </div>
                            <div
                                class="flex justify-between text-rose-600 dark:text-rose-400"
                            >
                                <span>- Total Cash Out (Expenses):</span>
                                <span class="font-mono font-medium">{{
                                    formatPhp(slipData.settlement.cash_out)
                                }}</span>
                            </div>
                            <div
                                class="flex justify-between border-t pt-2 text-sm font-semibold"
                            >
                                <span>Expected Drawer Cash:</span>
                                <span class="font-mono text-primary">{{
                                    formatPhp(slipData.settlement.expected_cash)
                                }}</span>
                            </div>
                            <div
                                class="flex justify-between border-t pt-1 text-sm font-semibold"
                            >
                                <span>Actual Cash Counted:</span>
                                <span class="font-mono">{{
                                    formatPhp(slipData.settlement.actual_cash)
                                }}</span>
                            </div>
                            <div
                                class="flex items-center justify-between border-t pt-2 font-semibold"
                            >
                                <span>Discrepancy (Over/Short):</span>
                                <Badge
                                    :variant="
                                        formatDiscrepancy(
                                            slipData.settlement.discrepancy,
                                        ).variant
                                    "
                                    class="text-xs"
                                >
                                    {{
                                        formatDiscrepancy(
                                            slipData.settlement.discrepancy,
                                        ).label
                                    }}
                                </Badge>
                            </div>
                        </div>

                        <!-- Non-Cash & Gross Business Summary -->
                        <div class="space-y-2 border-t pt-3 text-xs">
                            <p
                                class="text-[11px] font-semibold tracking-wider text-foreground uppercase"
                            >
                                Non-Cash Collections & Business Summary
                            </p>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground"
                                    >GCash Payments:</span
                                >
                                <span
                                    class="font-mono text-blue-600 dark:text-blue-400"
                                    >{{
                                        formatPhp(
                                            slipData.settlement.total_gcash,
                                        )
                                    }}</span
                                >
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground"
                                    >Bank Transfer Expenses:</span
                                >
                                <span class="font-mono">{{
                                    formatPhp(
                                        slipData.settlement.total_bank_transfer,
                                    )
                                }}</span>
                            </div>
                            <div class="flex justify-between font-medium">
                                <span>Total Gross Sales:</span>
                                <span class="font-mono">{{
                                    formatPhp(slipData.settlement.total_sales)
                                }}</span>
                            </div>
                            <div class="flex justify-between font-medium">
                                <span>Total DDN Earnings:</span>
                                <span class="font-mono text-primary">{{
                                    formatPhp(
                                        slipData.settlement.total_ddn_earnings,
                                    )
                                }}</span>
                            </div>
                        </div>

                        <!-- Notes if any -->
                        <div
                            v-if="slipData.settlement.notes"
                            class="border-t pt-3 text-xs"
                        >
                            <p class="font-semibold text-foreground">
                                Closing Remarks:
                            </p>
                            <p class="mt-1 text-muted-foreground italic">
                                "{{ slipData.settlement.notes }}"
                            </p>
                        </div>
                    </div>

                    <!-- Modal Actions (Hidden on Print) -->
                    <DialogFooter class="print:hidden">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isSlipModalOpen = false"
                        >
                            Close
                        </Button>
                        <Button type="button" @click="printSlip">
                            <Printer class="mr-2 size-4" />
                            Print Slip
                        </Button>
                    </DialogFooter>
                </div>
            </DialogContent>
        </Dialog>
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
