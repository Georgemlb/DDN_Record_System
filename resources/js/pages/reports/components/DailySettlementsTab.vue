<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import {
    AlertCircle,
    CalendarPlus,
    CheckCircle2,
    Eye,
    Info,
    Lock,
    Pencil,
    RotateCcw,
    Smartphone,
    Trash2,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
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
import { formatDateTime, formatDiscrepancy } from '@/lib/reportFormat';
import { formatPhp, formatTransactionDate } from '@/lib/transactionFormat';
import type { DailySettlement, DailySettlementsPageData } from '@/types/report';
import PastSettlementModal from './PastSettlementModal.vue';
import SettlementSlipModal from './SettlementSlipModal.vue';

const props = defineProps<{
    dailySettlements: DailySettlementsPageData;
}>();

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
    opening_gcash: props.dailySettlements.suggestedOpeningGcash || 0,
    settlement_date: props.dailySettlements.todayDate,
});

const openStartDayModal = () => {
    startForm.opening_cash = props.dailySettlements.suggestedOpeningFloat || 0;
    startForm.opening_gcash = props.dailySettlements.suggestedOpeningGcash || 0;
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

// Record Past Settlement Modal Ref
const pastModalRef = ref<InstanceType<typeof PastSettlementModal> | null>(null);
const openPastModal = () => {
    pastModalRef.value?.open();
};

// Close Day Form
const closeForm = useForm({
    actual_cash: '',
    actual_gcash: '',
    notes: '',
});

const openCloseDayModal = (settlement?: DailySettlement | null) => {
    settlementToClose.value =
        settlement || props.dailySettlements.todaySettlement || null;
    closeForm.actual_cash = '';
    closeForm.actual_gcash = '';
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

const currentExpectedGcash = computed(() => {
    const s = activeSettlementToClose.value;

    if (!s) {
        return 0;
    }

    return typeof s.expected_gcash === 'string'
        ? parseFloat(s.expected_gcash)
        : (s.expected_gcash ?? 0);
});

const liveClosingDiscrepancy = computed(() => {
    if (closeForm.actual_cash === '' || isNaN(Number(closeForm.actual_cash))) {
        return null;
    }

    const actual = parseFloat(closeForm.actual_cash);

    return actual - currentExpectedCash.value;
});

const liveClosingGcashDiscrepancy = computed(() => {
    if (
        closeForm.actual_gcash === '' ||
        closeForm.actual_gcash === null ||
        isNaN(Number(closeForm.actual_gcash))
    ) {
        return null;
    }

    const actual = parseFloat(String(closeForm.actual_gcash));

    return actual - currentExpectedGcash.value;
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
    opening_gcash: 0,
    actual_gcash: '' as string | number,
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
    editForm.opening_gcash =
        typeof settlement.opening_gcash === 'string'
            ? parseFloat(settlement.opening_gcash)
            : (settlement.opening_gcash ?? 0);
    editForm.actual_gcash =
        settlement.actual_gcash !== null &&
        settlement.actual_gcash !== undefined
            ? settlement.actual_gcash
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

const computedEditExpectedGcash = computed(() => {
    if (!settlementToEdit.value) {
        return 0;
    }

    const opening = Number(editForm.opening_gcash) || 0;
    const gcashIn =
        typeof settlementToEdit.value.gcash_in === 'string'
            ? parseFloat(settlementToEdit.value.gcash_in)
            : settlementToEdit.value.gcash_in || 0;
    const gcashOut =
        typeof settlementToEdit.value.gcash_out === 'string'
            ? parseFloat(settlementToEdit.value.gcash_out)
            : settlementToEdit.value.gcash_out || 0;

    return Math.round((opening + (gcashIn || 0) - (gcashOut || 0)) * 100) / 100;
});

const computedEditGcashDiscrepancy = computed(() => {
    if (editForm.actual_gcash === '' || isNaN(Number(editForm.actual_gcash))) {
        return null;
    }

    const actual = Number(editForm.actual_gcash);

    return Math.round((actual - computedEditExpectedGcash.value) * 100) / 100;
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

// Delete Settlement Modal
const isDeleteModalOpen = ref(false);
const settlementToDelete = ref<DailySettlement | null>(null);

const openDeleteModal = (settlement: DailySettlement) => {
    settlementToDelete.value = settlement;
    isDeleteModalOpen.value = true;
};

const confirmDelete = () => {
    if (!settlementToDelete.value) {
        return;
    }

    router.delete(`/reports/settlements/${settlementToDelete.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            isDeleteModalOpen.value = false;
            settlementToDelete.value = null;
        },
    });
};

// Slip Viewer & Printer Modal Ref
const slipModalRef = ref<InstanceType<typeof SettlementSlipModal> | null>(null);
const openSlipModal = (settlementId: number) => {
    slipModalRef.value?.open(settlementId);
};
</script>

<template>
    <section class="space-y-6 print:hidden">
        <!-- TODAY'S REGISTER STATUS CARD -->
        <Card class="overflow-hidden border-2 shadow-sm">
            <!-- Case 1: Register NOT yet opened today -->
            <div v-if="!dailySettlements.todaySettlement" class="p-6 sm:p-8">
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
                            Set the opening cash float to begin tracking cash
                            in, cash disbursements, and expected drawer balance
                            for today.
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
                            formatTransactionDate(dailySettlements.todayDate)
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
                                            dailySettlements.todayDraftMetrics
                                                .cash_out,
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
                v-else-if="dailySettlements.todaySettlement.status === 'open'"
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
                                openEditModal(dailySettlements.todaySettlement)
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
                        <p class="text-xs font-medium text-muted-foreground">
                            1. Opening Cash Float
                        </p>
                        <p class="mt-2 text-xl font-semibold tracking-tight">
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
                        <p class="text-xs font-medium text-muted-foreground">
                            2. Cash In (Sales)
                        </p>
                        <p
                            class="mt-2 text-xl font-semibold tracking-tight text-emerald-600 dark:text-emerald-400"
                        >
                            +{{
                                formatPhp(
                                    dailySettlements.todaySettlement.cash_in,
                                )
                            }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Cash transaction payments
                        </p>
                    </div>

                    <div class="rounded-lg border bg-card p-4">
                        <p class="text-xs font-medium text-muted-foreground">
                            3. Cash Out (Disbursements)
                        </p>
                        <p
                            class="mt-2 text-xl font-semibold tracking-tight text-rose-600 dark:text-rose-400"
                        >
                            -{{
                                formatPhp(
                                    dailySettlements.todaySettlement.cash_out,
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

                <!-- Live GCash Wallet Calculation Cards -->
                <div class="mt-4">
                    <div class="mb-2 flex items-center justify-between">
                        <h4
                            class="flex items-center gap-1.5 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            <Smartphone class="size-3.5 text-blue-500" />
                            <span>GCash Wallet Live Reconciliation</span>
                        </h4>
                    </div>
                    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                        <div class="rounded-lg border bg-card p-4">
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                1. Opening GCash
                            </p>
                            <p
                                class="mt-2 text-xl font-semibold tracking-tight"
                            >
                                {{
                                    formatPhp(
                                        dailySettlements.todaySettlement
                                            .opening_gcash ?? 0,
                                    )
                                }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Starting GCash balance
                            </p>
                        </div>
                        <div class="rounded-lg border bg-card p-4">
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                2. GCash In
                            </p>
                            <p
                                class="mt-2 text-xl font-semibold tracking-tight text-emerald-600 dark:text-emerald-400"
                            >
                                +{{
                                    formatPhp(
                                        dailySettlements.todaySettlement
                                            .gcash_in ?? 0,
                                    )
                                }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Cash-outs & customer payments
                            </p>
                        </div>
                        <div class="rounded-lg border bg-card p-4">
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                3. GCash Out
                            </p>
                            <p
                                class="mt-2 text-xl font-semibold tracking-tight text-rose-600 dark:text-rose-400"
                            >
                                -{{
                                    formatPhp(
                                        dailySettlements.todaySettlement
                                            .gcash_out ?? 0,
                                    )
                                }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Cash-ins & gov fees disbursed
                            </p>
                        </div>
                        <div
                            class="rounded-lg border-2 border-blue-500/40 bg-blue-500/5 p-4"
                        >
                            <p
                                class="text-xs font-medium text-blue-600 dark:text-blue-400"
                            >
                                4. Expected GCash Balance
                            </p>
                            <p
                                class="mt-2 text-2xl font-bold tracking-tight text-blue-600 dark:text-blue-400"
                            >
                                {{
                                    formatPhp(
                                        dailySettlements.todaySettlement
                                            .expected_gcash ?? 0,
                                    )
                                }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Opening + In - Out
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Digital Collections Strip -->
                <div
                    class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-muted/30 px-4 py-3 text-xs"
                >
                    <div class="flex items-center gap-4 text-muted-foreground">
                        <span>Digital Collections Today:</span>
                        <span class="font-medium text-foreground">
                            GCash:
                            <span class="text-blue-600 dark:text-blue-400">{{
                                formatPhp(
                                    dailySettlements.todaySettlement
                                        .total_gcash,
                                )
                            }}</span>
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
                                dailySettlements.todaySettlement.total_sales,
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
                            Today's Settlement Complete
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
                                openEditModal(dailySettlements.todaySettlement)
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

                <!-- Final Cash Reconciliation Figures -->
                <div class="mt-6">
                    <h4
                        class="mb-2 flex items-center gap-1.5 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        <Wallet class="size-3.5 text-primary" />
                        <span>Cash Drawer Reconciled</span>
                    </h4>
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
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
                </div>

                <!-- Final GCash Wallet Reconciliation Figures -->
                <div class="mt-4">
                    <h4
                        class="mb-2 flex items-center gap-1.5 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        <Smartphone class="size-3.5 text-blue-500" />
                        <span>GCash Wallet Reconciled</span>
                    </h4>
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div class="rounded-lg border bg-card p-4">
                            <p class="text-xs text-muted-foreground">
                                Opening GCash
                            </p>
                            <p class="mt-1 text-lg font-semibold">
                                {{
                                    formatPhp(
                                        dailySettlements.todaySettlement
                                            .opening_gcash ?? 0,
                                    )
                                }}
                            </p>
                        </div>
                        <div class="rounded-lg border bg-card p-4">
                            <p class="text-xs text-muted-foreground">
                                Expected GCash
                            </p>
                            <p class="mt-1 text-lg font-semibold">
                                {{
                                    formatPhp(
                                        dailySettlements.todaySettlement
                                            .expected_gcash ?? 0,
                                    )
                                }}
                            </p>
                        </div>
                        <div class="rounded-lg border bg-card p-4">
                            <p class="text-xs text-muted-foreground">
                                Actual GCash Counted
                            </p>
                            <p
                                class="mt-1 text-lg font-semibold text-blue-600 dark:text-blue-400"
                            >
                                {{
                                    dailySettlements.todaySettlement
                                        .actual_gcash !== null
                                        ? formatPhp(
                                              dailySettlements.todaySettlement
                                                  .actual_gcash,
                                          )
                                        : '—'
                                }}
                            </p>
                        </div>
                        <div class="rounded-lg border bg-card p-4">
                            <p class="text-xs text-muted-foreground">
                                GCash Discrepancy
                            </p>
                            <div class="mt-1">
                                <Badge
                                    v-if="
                                        dailySettlements.todaySettlement
                                            .gcash_discrepancy !== null
                                    "
                                    :variant="
                                        formatDiscrepancy(
                                            dailySettlements.todaySettlement
                                                .gcash_discrepancy,
                                        ).variant
                                    "
                                    class="text-xs"
                                >
                                    {{
                                        formatDiscrepancy(
                                            dailySettlements.todaySettlement
                                                .gcash_discrepancy,
                                        ).label
                                    }}
                                </Badge>
                                <span
                                    v-else
                                    class="text-xs text-muted-foreground"
                                    >—</span
                                >
                            </div>
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
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h3 class="text-lg font-semibold tracking-tight">
                        Settlement History
                    </h3>
                    <p class="text-sm text-muted-foreground">
                        Audit log of previous daily registers, cash drawer
                        counts, and discrepancies.
                    </p>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    @click="openPastModal"
                    class="gap-1.5 self-start sm:self-auto"
                >
                    <CalendarPlus class="size-4" />
                    Record Past Settlement
                </Button>
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
                                    Drawer (Exp / Act)
                                </th>
                                <th class="p-4 text-center font-medium">
                                    Cash Diff
                                </th>
                                <th class="p-4 text-right font-medium">
                                    GCash (Exp / Act)
                                </th>
                                <th class="p-4 text-center font-medium">
                                    GCash Diff
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
                                    dailySettlements.history.data.length === 0
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
                                v-for="item in dailySettlements.history.data"
                                :key="item.id"
                                class="transition-colors hover:bg-muted/30"
                            >
                                <td class="p-4 font-medium whitespace-nowrap">
                                    {{
                                        formatTransactionDate(
                                            item.settlement_date,
                                        )
                                    }}
                                </td>
                                <td class="p-4 whitespace-nowrap">
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
                                <td
                                    class="p-4 text-right font-mono whitespace-nowrap"
                                >
                                    <div class="font-medium text-foreground">
                                        {{
                                            item.actual_cash !== null
                                                ? formatPhp(item.actual_cash)
                                                : '—'
                                        }}
                                    </div>
                                    <div
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        Exp: {{ formatPhp(item.expected_cash) }}
                                    </div>
                                </td>
                                <td class="p-4 text-center whitespace-nowrap">
                                    <Badge
                                        v-if="item.status === 'closed'"
                                        :variant="
                                            formatDiscrepancy(item.discrepancy)
                                                .variant
                                        "
                                        class="text-xs"
                                    >
                                        {{
                                            formatDiscrepancy(item.discrepancy)
                                                .label
                                        }}
                                    </Badge>
                                    <span
                                        v-else
                                        class="text-xs text-muted-foreground"
                                        >In Progress</span
                                    >
                                </td>
                                <td
                                    class="p-4 text-right font-mono whitespace-nowrap"
                                >
                                    <div class="font-medium text-foreground">
                                        {{
                                            item.actual_gcash !== null &&
                                            item.actual_gcash !== undefined
                                                ? formatPhp(item.actual_gcash)
                                                : '—'
                                        }}
                                    </div>
                                    <div
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        Exp:
                                        {{
                                            formatPhp(item.expected_gcash ?? 0)
                                        }}
                                    </div>
                                </td>
                                <td class="p-4 text-center whitespace-nowrap">
                                    <Badge
                                        v-if="
                                            item.status === 'closed' &&
                                            item.gcash_discrepancy !== null &&
                                            item.gcash_discrepancy !== undefined
                                        "
                                        :variant="
                                            formatDiscrepancy(
                                                item.gcash_discrepancy,
                                            ).variant
                                        "
                                        class="text-xs"
                                    >
                                        {{
                                            formatDiscrepancy(
                                                item.gcash_discrepancy,
                                            ).label
                                        }}
                                    </Badge>
                                    <span
                                        v-else-if="item.status === 'closed'"
                                        class="text-xs text-muted-foreground"
                                        >—</span
                                    >
                                    <span
                                        v-else
                                        class="text-xs text-muted-foreground"
                                        >In Progress</span
                                    >
                                </td>
                                <td class="p-4 text-xs text-muted-foreground">
                                    <div>{{ item.user?.name ?? '—' }}</div>
                                    <div
                                        v-if="item.closed_by_user"
                                        class="text-foreground"
                                    >
                                        Closed: {{ item.closed_by_user.name }}
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
                                            <RotateCcw class="mr-1 size-3.5" />
                                            Reopen
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="h-8 px-2 text-xs text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:text-rose-400 dark:hover:bg-rose-950/30 dark:hover:text-rose-300"
                                            @click="openDeleteModal(item)"
                                        >
                                            <Trash2 class="mr-1 size-3.5" />
                                            Delete
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

        <!-- MODAL: START DAY -->
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
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="opening_cash"
                                >Opening Cash Float (₱)</Label
                            >
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
                                Suggested drawer cash:
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

                        <div class="space-y-2">
                            <Label for="opening_gcash"
                                >Opening GCash Balance (₱)</Label
                            >
                            <Input
                                id="opening_gcash"
                                v-model="startForm.opening_gcash"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                            />
                            <p class="text-xs text-muted-foreground">
                                Suggested GCash balance:
                                <span class="font-medium text-foreground">{{
                                    formatPhp(
                                        dailySettlements.suggestedOpeningGcash ??
                                            0,
                                    )
                                }}</span>
                            </p>
                            <p
                                v-if="startForm.errors.opening_gcash"
                                class="text-xs text-destructive"
                            >
                                {{ startForm.errors.opening_gcash }}
                            </p>
                        </div>
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

        <!-- MODAL: RECORD PAST SETTLEMENT -->
        <PastSettlementModal
            ref="pastModalRef"
            :today-date="dailySettlements.todayDate"
        />

        <!-- MODAL: CLOSE DAY -->
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
                        Count drawer cash and check GCash app balance to
                        reconcile
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
                    <div class="space-y-3 rounded-lg border bg-muted/20 p-4">
                        <div
                            class="flex items-center gap-1.5 text-xs font-semibold tracking-wider text-primary uppercase"
                        >
                            <Wallet class="size-4" />
                            <span>1. Cash Drawer Count & Reconciliation</span>
                        </div>
                        <div class="space-y-1.5 text-xs">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground"
                                    >Opening Float:</span
                                >
                                <span class="font-mono">{{
                                    formatPhp(
                                        activeSettlementToClose?.opening_cash,
                                    )
                                }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground"
                                    >Cash In (Sales):</span
                                >
                                <span
                                    class="font-mono text-emerald-600 dark:text-emerald-400"
                                    >+{{
                                        formatPhp(
                                            activeSettlementToClose?.cash_in,
                                        )
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
                                        formatPhp(
                                            activeSettlementToClose?.cash_out,
                                        )
                                    }}</span
                                >
                            </div>
                            <div
                                class="flex justify-between border-t pt-1.5 font-semibold"
                            >
                                <span>Expected Drawer Cash:</span>
                                <span class="font-mono text-primary">{{
                                    formatPhp(currentExpectedCash)
                                }}</span>
                            </div>
                        </div>

                        <div class="space-y-1.5 pt-1">
                            <Label
                                for="actual_cash"
                                class="text-xs font-medium"
                            >
                                Physical Cash Counted (₱)
                                <span class="text-destructive">*</span>
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

                        <div
                            v-if="liveClosingDiscrepancy !== null"
                            class="flex items-center justify-between rounded border bg-card p-2 text-xs"
                        >
                            <span class="text-muted-foreground"
                                >Cash Discrepancy:</span
                            >
                            <Badge
                                :variant="
                                    formatDiscrepancy(liveClosingDiscrepancy)
                                        .variant
                                "
                                class="text-xs"
                            >
                                {{
                                    formatDiscrepancy(liveClosingDiscrepancy)
                                        .label
                                }}
                            </Badge>
                        </div>
                    </div>

                    <div
                        class="space-y-3 rounded-lg border border-blue-500/20 bg-blue-500/5 p-4"
                    >
                        <div
                            class="flex items-center gap-1.5 text-xs font-semibold tracking-wider text-blue-600 uppercase dark:text-blue-400"
                        >
                            <Smartphone class="size-4" />
                            <span>2. GCash Wallet Count & Reconciliation</span>
                        </div>
                        <div class="space-y-1.5 text-xs">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground"
                                    >Opening GCash:</span
                                >
                                <span class="font-mono">{{
                                    formatPhp(
                                        activeSettlementToClose?.opening_gcash ??
                                            0,
                                    )
                                }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground"
                                    >GCash In:</span
                                >
                                <span
                                    class="font-mono text-emerald-600 dark:text-emerald-400"
                                    >+{{
                                        formatPhp(
                                            activeSettlementToClose?.gcash_in ??
                                                0,
                                        )
                                    }}</span
                                >
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground"
                                    >GCash Out:</span
                                >
                                <span
                                    class="font-mono text-rose-600 dark:text-rose-400"
                                    >-{{
                                        formatPhp(
                                            activeSettlementToClose?.gcash_out ??
                                                0,
                                        )
                                    }}</span
                                >
                            </div>
                            <div
                                class="flex justify-between border-t pt-1.5 font-semibold"
                            >
                                <span>Expected GCash Balance:</span>
                                <span
                                    class="font-mono text-blue-600 dark:text-blue-400"
                                    >{{ formatPhp(currentExpectedGcash) }}</span
                                >
                            </div>
                        </div>

                        <div class="space-y-1.5 pt-1">
                            <Label
                                for="actual_gcash"
                                class="text-xs font-medium"
                            >
                                Actual GCash Balance Counted (₱)
                            </Label>
                            <Input
                                id="actual_gcash"
                                v-model="closeForm.actual_gcash"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="Check GCash app balance"
                            />
                            <p
                                v-if="closeForm.errors.actual_gcash"
                                class="text-xs text-destructive"
                            >
                                {{ closeForm.errors.actual_gcash }}
                            </p>
                        </div>

                        <div
                            v-if="liveClosingGcashDiscrepancy !== null"
                            class="flex items-center justify-between rounded border bg-card p-2 text-xs"
                        >
                            <span class="text-muted-foreground"
                                >GCash Discrepancy:</span
                            >
                            <Badge
                                :variant="
                                    formatDiscrepancy(
                                        liveClosingGcashDiscrepancy,
                                    ).variant
                                "
                                class="text-xs"
                            >
                                {{
                                    formatDiscrepancy(
                                        liveClosingGcashDiscrepancy,
                                    ).label
                                }}
                            </Badge>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="notes">Notes / Discrepancy Remarks</Label>
                        <textarea
                            id="notes"
                            v-model="closeForm.notes"
                            rows="2"
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

        <!-- MODAL: EDIT DAILY SETTLEMENT -->
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
                        <div class="grid grid-cols-2 gap-4 border-t pt-2">
                            <div>
                                <p class="font-semibold text-primary">
                                    Cash Drawer
                                </p>
                                <div
                                    class="mt-1 flex justify-between text-[11px]"
                                >
                                    <span class="text-muted-foreground"
                                        >Cash In:</span
                                    >
                                    <span
                                        class="font-mono text-emerald-600 dark:text-emerald-400"
                                        >+{{
                                            formatPhp(settlementToEdit?.cash_in)
                                        }}</span
                                    >
                                </div>
                                <div class="flex justify-between text-[11px]">
                                    <span class="text-muted-foreground"
                                        >Cash Out:</span
                                    >
                                    <span
                                        class="font-mono text-rose-600 dark:text-rose-400"
                                        >-{{
                                            formatPhp(
                                                settlementToEdit?.cash_out,
                                            )
                                        }}</span
                                    >
                                </div>
                                <div
                                    class="mt-1 flex justify-between border-t pt-1 text-[11px] font-semibold"
                                >
                                    <span>Expected Cash:</span>
                                    <span class="font-mono text-primary">{{
                                        formatPhp(computedEditExpectedCash)
                                    }}</span>
                                </div>
                            </div>
                            <div>
                                <p
                                    class="font-semibold text-blue-600 dark:text-blue-400"
                                >
                                    GCash Wallet
                                </p>
                                <div
                                    class="mt-1 flex justify-between text-[11px]"
                                >
                                    <span class="text-muted-foreground"
                                        >GCash In:</span
                                    >
                                    <span
                                        class="font-mono text-emerald-600 dark:text-emerald-400"
                                        >+{{
                                            formatPhp(
                                                settlementToEdit?.gcash_in ?? 0,
                                            )
                                        }}</span
                                    >
                                </div>
                                <div class="flex justify-between text-[11px]">
                                    <span class="text-muted-foreground"
                                        >GCash Out:</span
                                    >
                                    <span
                                        class="font-mono text-rose-600 dark:text-rose-400"
                                        >-{{
                                            formatPhp(
                                                settlementToEdit?.gcash_out ??
                                                    0,
                                            )
                                        }}</span
                                    >
                                </div>
                                <div
                                    class="mt-1 flex justify-between border-t pt-1 text-[11px] font-semibold"
                                >
                                    <span>Expected GCash:</span>
                                    <span
                                        class="font-mono text-blue-600 dark:text-blue-400"
                                        >{{
                                            formatPhp(computedEditExpectedGcash)
                                        }}</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label for="edit_opening_cash"
                                >Opening Float (₱)</Label
                            >
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

                        <div class="space-y-1.5">
                            <Label for="edit_opening_gcash"
                                >Opening GCash (₱)</Label
                            >
                            <Input
                                id="edit_opening_gcash"
                                v-model="editForm.opening_gcash"
                                type="number"
                                step="0.01"
                                min="0"
                            />
                            <p
                                v-if="editForm.errors.opening_gcash"
                                class="text-xs text-destructive"
                            >
                                {{ editForm.errors.opening_gcash }}
                            </p>
                        </div>
                    </div>

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

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label for="edit_actual_cash">
                                Actual Cash Counted (₱)
                                <span
                                    v-if="editForm.status === 'open'"
                                    class="text-xs font-normal text-muted-foreground"
                                    >(Optional)</span
                                >
                            </Label>
                            <Input
                                id="edit_actual_cash"
                                v-model="editForm.actual_cash"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="Physical cash"
                                :required="editForm.status === 'closed'"
                            />
                            <div
                                v-if="computedEditDiscrepancy !== null"
                                class="flex items-center justify-between pt-1"
                            >
                                <span class="text-[11px] text-muted-foreground"
                                    >Cash Diff:</span
                                >
                                <Badge
                                    :variant="
                                        formatDiscrepancy(
                                            computedEditDiscrepancy,
                                        ).variant
                                    "
                                    class="text-[10px]"
                                >
                                    {{
                                        formatDiscrepancy(
                                            computedEditDiscrepancy,
                                        ).label
                                    }}
                                </Badge>
                            </div>
                            <p
                                v-if="editForm.errors.actual_cash"
                                class="text-xs text-destructive"
                            >
                                {{ editForm.errors.actual_cash }}
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="edit_actual_gcash">
                                Actual GCash Counted (₱)
                                <span
                                    v-if="editForm.status === 'open'"
                                    class="text-xs font-normal text-muted-foreground"
                                    >(Optional)</span
                                >
                            </Label>
                            <Input
                                id="edit_actual_gcash"
                                v-model="editForm.actual_gcash"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="GCash balance"
                            />
                            <div
                                v-if="computedEditGcashDiscrepancy !== null"
                                class="flex items-center justify-between pt-1"
                            >
                                <span class="text-[11px] text-muted-foreground"
                                    >GCash Diff:</span
                                >
                                <Badge
                                    :variant="
                                        formatDiscrepancy(
                                            computedEditGcashDiscrepancy,
                                        ).variant
                                    "
                                    class="text-[10px]"
                                >
                                    {{
                                        formatDiscrepancy(
                                            computedEditGcashDiscrepancy,
                                        ).label
                                    }}
                                </Badge>
                            </div>
                            <p
                                v-if="editForm.errors.actual_gcash"
                                class="text-xs text-destructive"
                            >
                                {{ editForm.errors.actual_gcash }}
                            </p>
                        </div>
                    </div>

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

        <!-- MODAL: REOPEN CONFIRMATION -->
        <Dialog v-model:open="isReopenModalOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Reopen Daily Register?</DialogTitle>
                    <DialogDescription>
                        Reopening this settlement allows you to record missing
                        transactions or adjust the final count for
                        {{
                            settlementToReopen
                                ? formatTransactionDate(
                                      settlementToReopen.settlement_date,
                                  )
                                : 'this day'
                        }}.
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
                        Reopen Register
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- MODAL: DELETE SETTLEMENT CONFIRMATION -->
        <Dialog v-model:open="isDeleteModalOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Delete Settlement Record</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to permanently delete the daily
                        settlement record for
                        <strong class="text-foreground">
                            {{
                                settlementToDelete
                                    ? formatTransactionDate(
                                          settlementToDelete.settlement_date,
                                      )
                                    : 'this date'
                            }}
                        </strong>
                        ? This action cannot be undone, though individual
                        transactions and expenses remain untouched.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="mt-4">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isDeleteModalOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        @click="confirmDelete"
                    >
                        Delete Permanently
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- MODAL: PRINTABLE SETTLEMENT SLIP -->
        <SettlementSlipModal ref="slipModalRef" />
    </section>
</template>
