<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowDownLeft,
    ArrowUpRight,
    ChevronUp,
    ChevronDown,
    Loader2,
    Minus,
    Pencil,
    Pin,
    Plus,
    Search,
    SlidersHorizontal,
    Smartphone,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatPhp } from '@/lib/transactionFormat';
import type {
    GcashLedgerEntry,
    GcashLedgerFilterParams,
    GcashLedgerMetrics,
} from '@/types/gcashLedger';

const props = defineProps<{
    entries: {
        data: GcashLedgerEntry[];
        links: any[];
        total: number;
        from: number | null;
        to: number | null;
    };
    metrics: GcashLedgerMetrics;
    filters: GcashLedgerFilterParams;
    types: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'GCash Ledger',
                href: '/gcash-ledger',
            },
        ],
    },
});

// Filters
const search = ref(props.filters.search ?? '');
const typeFilter = ref(props.filters.type ?? 'all');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');

let filterTimeout: ReturnType<typeof setTimeout>;
const applyFilters = () => {
    clearTimeout(filterTimeout);
    filterTimeout = setTimeout(() => {
        router.get(
            '/gcash-ledger',
            {
                search: search.value || undefined,
                type: typeFilter.value !== 'all' ? typeFilter.value : undefined,
                date_from: dateFrom.value || undefined,
                date_to: dateTo.value || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }, 300);
};

watch([search, typeFilter, dateFrom, dateTo], () => {
    applyFilters();
});

const clearFilters = () => {
    search.value = '';
    typeFilter.value = 'all';
    dateFrom.value = '';
    dateTo.value = '';
};

const hasActiveFilters = computed(() => {
    return Boolean(
        search.value ||
        typeFilter.value !== 'all' ||
        dateFrom.value ||
        dateTo.value,
    );
});

// Today date helper
const todayDate = new Date().toISOString().split('T')[0];

// Manual Adjustment Modal
const isAdjustmentModalOpen = ref(false);
const adjustmentDirection = ref<'credit' | 'debit'>('credit');
const isAdjAnchor = ref(false);

const adjustmentForm = useForm({
    amount: '',
    entry_date: todayDate,
    notes: '',
    from_drawer: false,
    is_balance_anchor: false,
    running_balance: '',
});

const openAdjustmentModal = () => {
    adjustmentForm.reset();
    adjustmentForm.entry_date = todayDate;
    adjustmentForm.from_drawer = false;
    adjustmentDirection.value = 'credit';
    isAdjAnchor.value = false;
    isAdjustmentModalOpen.value = true;
};

const submitAdjustment = () => {
    const rawAmount = parseFloat(adjustmentForm.amount as string);

    if (isNaN(rawAmount) || rawAmount <= 0) {
        return;
    }

    const signedAmount =
        adjustmentDirection.value === 'debit' ? -rawAmount : rawAmount;

    adjustmentForm
        .transform((data) => ({
            ...data,
            amount: signedAmount.toFixed(2),
            from_drawer:
                adjustmentDirection.value === 'credit'
                    ? data.from_drawer
                    : false,
            is_balance_anchor: isAdjAnchor.value,
            running_balance:
                isAdjAnchor.value && data.running_balance
                    ? parseFloat(data.running_balance as string).toFixed(2)
                    : null,
        }))
        .post('/gcash-ledger/adjustment', {
            preserveScroll: true,
            onSuccess: () => {
                isAdjustmentModalOpen.value = false;
                adjustmentForm.reset();
            },
        });
};

// Edit Entry Modal
const isEditModalOpen = ref(false);
const entryToEdit = ref<GcashLedgerEntry | null>(null);
const editAdjustmentDirection = ref<'credit' | 'debit'>('credit');
const isAnchorOverride = ref(false);

const editForm = useForm({
    amount: '',
    entry_date: '',
    notes: '',
    running_balance: '',
    is_balance_anchor: false,
});

const openEditModal = (entry: GcashLedgerEntry) => {
    entryToEdit.value = entry;
    editForm.clearErrors();

    isAnchorOverride.value = Boolean(entry.is_balance_anchor);
    editForm.running_balance =
        entry.running_balance !== null && entry.running_balance !== undefined
            ? String(Math.abs(Number(entry.running_balance)))
            : '';
    editForm.is_balance_anchor = Boolean(entry.is_balance_anchor);

    const numAmount = Number(entry.amount);
    editAdjustmentDirection.value = numAmount < 0 ? 'debit' : 'credit';
    editForm.amount = String(Math.abs(numAmount));
    editForm.entry_date = String(entry.entry_date).split('T')[0];
    editForm.notes = entry.notes ?? '';

    isEditModalOpen.value = true;
};

const resetAnchorToAuto = () => {
    isAnchorOverride.value = false;
    editForm.is_balance_anchor = false;
};

const submitEdit = () => {
    if (!entryToEdit.value) {
        return;
    }

    const rawAmount = parseFloat(editForm.amount as string);

    if (isNaN(rawAmount) || rawAmount <= 0) {
        return;
    }

    const signedAmount =
        editAdjustmentDirection.value === 'debit' ? -rawAmount : rawAmount;

    editForm
        .transform((data) => ({
            ...data,
            amount: signedAmount.toFixed(2),
            is_balance_anchor: isAnchorOverride.value,
            running_balance:
                isAnchorOverride.value && data.running_balance
                    ? parseFloat(data.running_balance as string).toFixed(2)
                    : null,
        }))
        .put(`/gcash-ledger/${entryToEdit.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                isEditModalOpen.value = false;
                entryToEdit.value = null;
            },
        });
};

// Delete Entry Modal
const isDeleteModalOpen = ref(false);
const entryToDelete = ref<GcashLedgerEntry | null>(null);
const isDeleting = ref(false);

const openDeleteModal = (entry: GcashLedgerEntry) => {
    entryToDelete.value = entry;
    isDeleteModalOpen.value = true;
};

const executeDelete = () => {
    if (!entryToDelete.value) {
        return;
    }

    isDeleting.value = true;
    router.delete(`/gcash-ledger/${entryToDelete.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            isDeleteModalOpen.value = false;
            entryToDelete.value = null;
        },
    });
};

// Format Helpers
const formatEntryDate = (dateStr: string | null | undefined) => {
    if (!dateStr) {
        return '';
    }

    return String(dateStr).split('T')[0];
};

const getTypeLabel = (type: string) => {
    switch (type) {
        case 'customer_payment':
        case 'direct_payment':
            return 'Customer Payment';
        case 'remittance_cash_out':
            return 'Cash-Out (+GCash)';
        case 'remittance_cash_in':
            return 'Cash-In (-GCash)';
        case 'government_disbursement':
        case 'online_assistance':
            return 'Gov Disbursement';
        case 'expense_payment':
        case 'direct_expense':
            return 'GCash Expense';
        case 'portal_reload':
            return 'Portal Reload';
        case 'cash_transfer':
            return 'Cash Transfer';
        case 'bank_transfer':
            return 'Bank Transfer';
        case 'adjustment':
            return 'Adjustment / Top-Up';
        default:
            return type.replace(/_/g, ' ');
    }
};

const getTypeBadgeClass = (type: string, amount: string | number) => {
    switch (type) {
        case 'customer_payment':
        case 'direct_payment':
        case 'remittance_cash_out':
            return 'bg-emerald-500/15 text-emerald-700 hover:bg-emerald-500/20 dark:text-emerald-300';
        case 'remittance_cash_in':
        case 'government_disbursement':
        case 'online_assistance':
        case 'expense_payment':
        case 'direct_expense':
        case 'portal_reload':
            return 'bg-rose-500/15 text-rose-700 hover:bg-rose-500/20 dark:text-rose-300';
        case 'bank_transfer':
        case 'cash_transfer':
            return 'bg-blue-500/15 text-blue-700 hover:bg-blue-500/20 dark:text-blue-300';
        case 'adjustment':
        default:
            return Number(amount) >= 0
                ? 'bg-emerald-500/15 text-emerald-700 hover:bg-emerald-500/20 dark:text-emerald-300'
                : 'bg-rose-500/15 text-rose-700 hover:bg-rose-500/20 dark:text-rose-300';
    }
};

const isInflow = (entry: GcashLedgerEntry) => {
    if (entry.type === 'adjustment') {
        return Number(entry.amount) > 0;
    }

    return [
        'customer_payment',
        'remittance_cash_out',
        'direct_payment',
        'cash_transfer',
        'bank_transfer',
    ].includes(entry.type);
};

// Reorder / Move
const movingId = ref<number | null>(null);

const moveEntry = (entry: GcashLedgerEntry, direction: 'up' | 'down') => {
    movingId.value = entry.id;
    router.post(
        `/gcash-ledger/${entry.id}/move`,
        {
            direction,
            table_direction: props.filters.direction || 'desc',
        },
        {
            preserveScroll: true,
            onFinish: () => {
                movingId.value = null;
            },
        },
    );
};
</script>

<template>
    <Head title="GCash Wallet Ledger" />

    <div class="space-y-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-bold tracking-tight text-foreground md:text-3xl"
                >
                    GCash Ledger
                </h1>
                <p class="text-sm text-muted-foreground">
                    Track live GCash wallet balance, automatic fees &
                    remittances, and daily reconciliations.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    variant="outline"
                    @click="openAdjustmentModal"
                    class="gap-2"
                >
                    <SlidersHorizontal class="size-4" />
                    <span>Adjustment / Anchor</span>
                </Button>
            </div>
        </div>

        <!-- Low Balance Alert Banner -->
        <div
            v-if="metrics.is_low_balance"
            class="flex items-start gap-3 rounded-lg border border-amber-500/30 bg-amber-500/10 p-4 text-amber-900 dark:text-amber-200"
        >
            <AlertTriangle
                class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400"
            />
            <div class="flex-1 text-sm">
                <span class="font-semibold">Low GCash Balance Alert:</span>
                The current wallet balance is
                <span class="font-bold">{{
                    formatPhp(metrics.current_balance)
                }}</span
                >, which is below the threshold of
                {{ formatPhp(metrics.low_balance_threshold) }}. Please top up
                your GCash wallet to ensure smooth remittance and online service
                payments.
            </div>
            <Button
                size="sm"
                variant="outline"
                class="border-amber-500/30 bg-background text-xs"
                @click="openAdjustmentModal"
            >
                Adjust / Top Up
            </Button>
        </div>

        <!-- Metric KPI Cards -->
        <div class="grid gap-4 sm:grid-cols-3">
            <!-- 1. Current Live Balance -->
            <Card
                :class="[
                    'border shadow-sm transition-all',
                    metrics.is_low_balance
                        ? 'border-amber-500/40 bg-gradient-to-br from-amber-500/5 to-transparent'
                        : 'border-border bg-card',
                ]"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle
                        class="text-sm font-medium text-muted-foreground"
                    >
                        Live GCash Balance
                    </CardTitle>
                    <div
                        :class="[
                            'rounded-full p-2',
                            metrics.is_low_balance
                                ? 'bg-amber-500/20 text-amber-600 dark:text-amber-400'
                                : 'bg-primary/10 text-primary',
                        ]"
                    >
                        <Smartphone class="size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold tracking-tight md:text-3xl">
                        {{ formatPhp(metrics.current_balance) }}
                    </div>
                    <div
                        class="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground"
                    >
                        <span
                            class="inline-block size-2 rounded-full"
                            :class="
                                metrics.is_low_balance
                                    ? 'bg-amber-500'
                                    : 'bg-emerald-500'
                            "
                        />
                        <span>{{
                            metrics.is_low_balance
                                ? 'Low Wallet Balance'
                                : 'Wallet Active & Healthy'
                        }}</span>
                    </div>
                </CardContent>
            </Card>

            <!-- 2. Total Inflows (This Month) -->
            <Card class="border shadow-sm">
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle
                        class="text-sm font-medium text-muted-foreground"
                    >
                        Total Inflows (Month)
                    </CardTitle>
                    <div
                        class="rounded-full bg-emerald-500/10 p-2 text-emerald-600 dark:text-emerald-400"
                    >
                        <ArrowDownLeft class="size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div
                        class="text-2xl font-bold tracking-tight text-emerald-600 md:text-3xl dark:text-emerald-400"
                    >
                        {{ formatPhp(metrics.total_inflows_month) }}
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Cash-Outs & payments into GCash
                    </p>
                </CardContent>
            </Card>

            <!-- 3. Total Outflows (This Month) -->
            <Card class="border shadow-sm">
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle
                        class="text-sm font-medium text-muted-foreground"
                    >
                        Total Outflows (Month)
                    </CardTitle>
                    <div
                        class="rounded-full bg-rose-500/10 p-2 text-rose-600 dark:text-rose-400"
                    >
                        <ArrowUpRight class="size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div
                        class="text-2xl font-bold tracking-tight text-rose-600 md:text-3xl dark:text-rose-400"
                    >
                        {{ formatPhp(metrics.total_outflows_month) }}
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Cash-Ins, Gov fees, & GCash expenses
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Filter Bar -->
        <Card class="border shadow-sm">
            <CardContent class="p-4">
                <div
                    class="grid gap-3 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-5"
                >
                    <!-- Search Input -->
                    <div class="relative sm:col-span-2">
                        <Search
                            class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="search"
                            placeholder="Search reference, customer, or notes..."
                            class="pl-9 text-sm"
                        />
                    </div>

                    <!-- Type Filter -->
                    <div>
                        <Select v-model="typeFilter">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="All Entry Types" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Types</SelectItem>
                                <SelectItem value="remittance_cash_out"
                                    >Cash-Out (Inflow +)</SelectItem
                                >
                                <SelectItem value="remittance_cash_in"
                                    >Cash-In (Outflow -)</SelectItem
                                >
                                <SelectItem value="online_assistance"
                                    >Gov Fee (Outflow -)</SelectItem
                                >
                                <SelectItem value="direct_payment"
                                    >Customer Payment (+)</SelectItem
                                >
                                <SelectItem value="direct_expense"
                                    >GCash Expense (-)</SelectItem
                                >
                                <SelectItem value="cash_transfer"
                                    >Cash Transfer (+)</SelectItem
                                >
                                <SelectItem value="bank_transfer"
                                    >Bank Transfer (+)</SelectItem
                                >
                                <SelectItem value="adjustment"
                                    >Adjustments</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Date From -->
                    <div>
                        <Input
                            type="date"
                            v-model="dateFrom"
                            placeholder="From Date"
                            class="text-sm"
                        />
                    </div>

                    <!-- Date To -->
                    <div>
                        <Input
                            type="date"
                            v-model="dateTo"
                            placeholder="To Date"
                            class="text-sm"
                        />
                    </div>
                </div>

                <!-- Active filter clear button -->
                <div
                    v-if="hasActiveFilters"
                    class="mt-3 flex items-center justify-between border-t pt-3 text-xs text-muted-foreground"
                >
                    <span>Showing filtered results</span>
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="clearFilters"
                        class="h-7 gap-1 text-xs text-muted-foreground hover:text-foreground"
                    >
                        <X class="size-3.5" />
                        Clear Filters
                    </Button>
                </div>
            </CardContent>
        </Card>

        <!-- Ledger Data Table -->
        <Card class="border shadow-sm">
            <CardHeader class="pb-3">
                <CardTitle class="text-base font-semibold"
                    >GCash Ledger & Movement History</CardTitle
                >
                <CardDescription>
                    Complete audit trail of GCash transactions, remittances,
                    government fee disbursements, and running balances.
                </CardDescription>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead
                            class="border-b bg-muted/40 text-xs font-semibold text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Type</th>
                                <th class="px-4 py-3">
                                    Reference / Transaction
                                </th>
                                <th class="px-4 py-3 text-right">Amount</th>
                                <th class="px-4 py-3 text-right">
                                    Running Balance
                                </th>
                                <th class="px-4 py-3">Details / Notes</th>
                                <th class="px-4 py-3">Recorded By</th>
                                <th class="px-4 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-if="entries.data.length === 0"
                                class="text-center text-muted-foreground"
                            >
                                <td colspan="8" class="py-12 text-center">
                                    <div
                                        class="mx-auto flex max-w-xs flex-col items-center justify-center"
                                    >
                                        <Smartphone
                                            class="mb-2 size-8 text-muted-foreground/60"
                                        />
                                        <p class="font-medium">
                                            No ledger entries found
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                hasActiveFilters
                                                    ? 'Try adjusting your search or filters.'
                                                    : 'Add an adjustment or record a GCash transaction to start tracking.'
                                            }}
                                        </p>
                                    </div>
                                </td>
                            </tr>
                            <tr
                                v-for="entry in entries.data"
                                :key="entry.id"
                                class="transition-colors hover:bg-muted/30"
                            >
                                <!-- Date -->
                                <td
                                    class="px-4 py-3 font-medium whitespace-nowrap"
                                >
                                    <div class="flex items-center gap-1.5">
                                        <span>{{
                                            formatEntryDate(entry.entry_date)
                                        }}</span>
                                        <span
                                            v-if="
                                                entry.can_move_up ||
                                                entry.can_move_down
                                            "
                                            class="rounded bg-muted px-1.5 py-0.5 font-mono text-[10px] font-semibold text-muted-foreground"
                                            title="Same-day sequence order"
                                        >
                                            #{{ entry.sort_order }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Type Badge -->
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <Badge
                                        variant="outline"
                                        :class="
                                            getTypeBadgeClass(
                                                entry.type,
                                                entry.amount,
                                            )
                                        "
                                    >
                                        {{ getTypeLabel(entry.type) }}
                                    </Badge>
                                </td>

                                <!-- Reference / Transaction -->
                                <td class="px-4 py-3">
                                    <div
                                        v-if="entry.transaction"
                                        class="space-y-0.5"
                                    >
                                        <div
                                            class="font-medium text-foreground"
                                        >
                                            {{
                                                entry.transaction
                                                    .reference_number
                                            }}
                                        </div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            Client:
                                            {{
                                                entry.transaction.customer_name
                                            }}
                                        </div>
                                    </div>
                                    <div
                                        v-else-if="entry.reference_number"
                                        class="font-medium text-foreground"
                                    >
                                        {{ entry.reference_number }}
                                    </div>
                                    <div v-else class="text-muted-foreground">
                                        —
                                    </div>
                                </td>

                                <!-- Amount (+ or -) -->
                                <td
                                    class="px-4 py-3 text-right font-semibold whitespace-nowrap"
                                >
                                    <span
                                        v-if="isInflow(entry)"
                                        class="text-emerald-600 dark:text-emerald-400"
                                    >
                                        +{{
                                            formatPhp(
                                                Math.abs(Number(entry.amount)),
                                            )
                                        }}
                                    </span>
                                    <span
                                        v-else
                                        class="text-rose-600 dark:text-rose-400"
                                    >
                                        -{{
                                            formatPhp(
                                                Math.abs(Number(entry.amount)),
                                            )
                                        }}
                                    </span>
                                </td>

                                <!-- Running Balance -->
                                <td
                                    class="px-4 py-3 text-right font-medium whitespace-nowrap"
                                >
                                    <div
                                        class="flex items-center justify-end gap-1.5"
                                    >
                                        <span
                                            v-if="entry.is_balance_anchor"
                                            class="inline-flex items-center gap-1 rounded bg-amber-500/15 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-300"
                                            title="Manually anchored running balance"
                                        >
                                            <Pin class="size-2.5" />
                                            Anchor
                                        </span>
                                        <span>{{
                                            formatPhp(entry.running_balance)
                                        }}</span>
                                    </div>
                                </td>

                                <!-- Details / Notes -->
                                <td
                                    class="max-w-[220px] truncate px-4 py-3 text-xs text-muted-foreground"
                                >
                                    <div
                                        v-if="entry.notes"
                                        class="truncate"
                                        :title="entry.notes"
                                    >
                                        {{ entry.notes }}
                                    </div>
                                    <div
                                        v-if="entry.expense"
                                        class="text-[11px] text-emerald-600 dark:text-emerald-400"
                                    >
                                        Linked to Expense #{{
                                            entry.expense.id
                                        }}
                                    </div>
                                </td>

                                <!-- Recorded By -->
                                <td
                                    class="px-4 py-3 text-xs whitespace-nowrap text-muted-foreground"
                                >
                                    {{ entry.user?.name ?? 'System' }}
                                </td>

                                <!-- Action -->
                                <td
                                    class="px-4 py-3 text-right whitespace-nowrap"
                                >
                                    <div
                                        class="flex items-center justify-end gap-1"
                                    >
                                        <!-- Reorder buttons -->
                                        <div
                                            v-if="
                                                entry.can_move_up ||
                                                entry.can_move_down
                                            "
                                            class="mr-1 flex items-center rounded-md border border-border/60 bg-muted/40 p-0.5"
                                        >
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="size-6 text-muted-foreground hover:text-foreground disabled:opacity-20"
                                                :disabled="
                                                    !entry.can_move_up ||
                                                    movingId === entry.id
                                                "
                                                @click="moveEntry(entry, 'up')"
                                                title="Move up in day sequence"
                                            >
                                                <ChevronUp class="size-3.5" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="size-6 text-muted-foreground hover:text-foreground disabled:opacity-20"
                                                :disabled="
                                                    !entry.can_move_down ||
                                                    movingId === entry.id
                                                "
                                                @click="
                                                    moveEntry(entry, 'down')
                                                "
                                                title="Move down in day sequence"
                                            >
                                                <ChevronDown class="size-3.5" />
                                            </Button>
                                        </div>

                                        <!-- Edit button (Adjustment or Anchor) -->
                                        <Button
                                            v-if="
                                                (!entry.transaction_id &&
                                                    !entry.expense_id) ||
                                                entry.is_balance_anchor
                                            "
                                            variant="ghost"
                                            size="icon"
                                            class="size-7 text-muted-foreground hover:text-foreground"
                                            @click="openEditModal(entry)"
                                            title="Edit entry or adjust anchor"
                                        >
                                            <Pencil class="size-3.5" />
                                        </Button>

                                        <!-- Delete button (Adjustment / non-linked) -->
                                        <Button
                                            v-if="
                                                !entry.transaction_id &&
                                                !entry.expense_id
                                            "
                                            variant="ghost"
                                            size="icon"
                                            class="size-7 text-muted-foreground hover:text-destructive"
                                            @click="openDeleteModal(entry)"
                                            title="Delete manual entry"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="entries.links && entries.links.length > 3"
                    class="border-t p-4"
                >
                    <Pagination :links="entries.links" />
                </div>
            </CardContent>
        </Card>

        <!-- MODAL: Manual Adjustment / Anchor -->
        <Dialog v-model:open="isAdjustmentModalOpen">
            <DialogContent class="sm:max-w-[460px]">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <SlidersHorizontal class="size-5 text-primary" />
                        <span>GCash Adjustment / Anchor</span>
                    </DialogTitle>
                    <DialogDescription>
                        Record a manual adjustment or anchor your GCash running
                        balance to match your app count.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitAdjustment" class="space-y-4 py-2">
                    <!-- Adjustment Direction -->
                    <div class="space-y-1.5">
                        <Label>Direction</Label>
                        <div class="grid grid-cols-2 gap-3">
                            <Button
                                type="button"
                                :variant="
                                    adjustmentDirection === 'credit'
                                        ? 'default'
                                        : 'outline'
                                "
                                class="w-full gap-2"
                                @click="adjustmentDirection = 'credit'"
                            >
                                <Plus class="size-4" />
                                <span>Inflow / Credit (+)</span>
                            </Button>
                            <Button
                                type="button"
                                :variant="
                                    adjustmentDirection === 'debit'
                                        ? 'destructive'
                                        : 'outline'
                                "
                                class="w-full gap-2"
                                @click="adjustmentDirection = 'debit'"
                            >
                                <Minus class="size-4" />
                                <span>Outflow / Debit (-)</span>
                            </Button>
                        </div>
                    </div>

                    <!-- From Cash Drawer Option -->
                    <div
                        v-if="adjustmentDirection === 'credit'"
                        class="rounded-lg border border-blue-500/20 bg-blue-500/5 p-3"
                    >
                        <div class="flex items-start gap-2.5">
                            <input
                                id="adj_from_drawer"
                                v-model="adjustmentForm.from_drawer"
                                type="checkbox"
                                class="mt-0.5 size-4 rounded border-input text-primary shadow-xs focus:ring-primary"
                            />
                            <div class="space-y-0.5 text-xs">
                                <Label
                                    for="adj_from_drawer"
                                    class="cursor-pointer font-semibold text-foreground"
                                >
                                    Funded from Cash Drawer (Top-Up)
                                </Label>
                                <p class="text-muted-foreground">
                                    Records a physical cash withdrawal from the
                                    drawer (-Cash Drawer) to reload into office
                                    GCash (+GCash Wallet).
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Amount -->
                    <div class="space-y-1.5">
                        <Label for="adj_amount">
                            Amount (PHP) <span class="text-destructive">*</span>
                        </Label>
                        <Input
                            id="adj_amount"
                            v-model="adjustmentForm.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            required
                            placeholder="0.00"
                        />
                        <span
                            v-if="adjustmentForm.errors.amount"
                            class="text-xs text-destructive"
                        >
                            {{ adjustmentForm.errors.amount }}
                        </span>
                    </div>

                    <!-- Date -->
                    <div class="space-y-1.5">
                        <Label for="adj_date">
                            Date <span class="text-destructive">*</span>
                        </Label>
                        <Input
                            id="adj_date"
                            v-model="adjustmentForm.entry_date"
                            type="date"
                            required
                        />
                        <span
                            v-if="adjustmentForm.errors.entry_date"
                            class="text-xs text-destructive"
                        >
                            {{ adjustmentForm.errors.entry_date }}
                        </span>
                    </div>

                    <!-- Audit Reason -->
                    <div class="space-y-1.5">
                        <Label for="adj_notes">
                            Reason / Description
                            <span class="text-destructive">*</span>
                        </Label>
                        <Input
                            id="adj_notes"
                            v-model="adjustmentForm.notes"
                            required
                            placeholder="e.g. End of day GCash calibration, or manual top-up"
                        />
                        <span
                            v-if="adjustmentForm.errors.notes"
                            class="text-xs text-destructive"
                        >
                            {{ adjustmentForm.errors.notes }}
                        </span>
                    </div>

                    <!-- Balance Anchor Override Checkbox -->
                    <div
                        class="space-y-2.5 rounded-lg border border-amber-500/30 bg-amber-500/5 p-3.5 text-xs"
                    >
                        <label class="flex cursor-pointer items-center gap-2">
                            <input
                                type="checkbox"
                                v-model="isAdjAnchor"
                                class="size-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                            />
                            <span
                                class="flex items-center gap-1.5 font-semibold text-foreground"
                            >
                                <Pin
                                    class="size-3 text-amber-600 dark:text-amber-400"
                                />
                                Anchor Running Balance (Set Exact Balance)
                            </span>
                        </label>

                        <div v-if="isAdjAnchor" class="space-y-1.5 pt-1">
                            <Label for="adj_anchor_balance" class="text-xs">
                                Exact Desired Running Balance on this row (PHP)
                            </Label>
                            <Input
                                id="adj_anchor_balance"
                                v-model="adjustmentForm.running_balance"
                                type="number"
                                step="0.01"
                                placeholder="0.00"
                                class="bg-background"
                            />
                            <p class="text-[11px] text-muted-foreground">
                                Locks this row's running balance to this exact
                                amount. Subsequent transactions will continue
                                from here.
                            </p>
                        </div>
                    </div>

                    <DialogFooter class="pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isAdjustmentModalOpen = false"
                            :disabled="adjustmentForm.processing"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="adjustmentForm.processing"
                            class="gap-2"
                        >
                            <Loader2
                                v-if="adjustmentForm.processing"
                                class="size-4 animate-spin"
                            />
                            <span>Save Entry</span>
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- MODAL: Edit Entry -->
        <Dialog v-model:open="isEditModalOpen">
            <DialogContent class="sm:max-w-[480px]">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Pencil class="size-5 text-primary" />
                        <span>Edit GCash Ledger Entry</span>
                    </DialogTitle>
                    <DialogDescription>
                        Update amount, entry date, notes, or running balance
                        anchor.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitEdit" class="space-y-4 py-2">
                    <!-- Amount -->
                    <div class="space-y-1.5">
                        <Label for="edit_amount">
                            Amount (PHP) <span class="text-destructive">*</span>
                        </Label>
                        <Input
                            id="edit_amount"
                            v-model="editForm.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            required
                            placeholder="0.00"
                        />
                        <span
                            v-if="editForm.errors.amount"
                            class="text-xs text-destructive"
                        >
                            {{ editForm.errors.amount }}
                        </span>
                    </div>

                    <!-- Date -->
                    <div class="space-y-1.5">
                        <Label for="edit_date">
                            Date <span class="text-destructive">*</span>
                        </Label>
                        <Input
                            id="edit_date"
                            v-model="editForm.entry_date"
                            type="date"
                            required
                        />
                        <span
                            v-if="editForm.errors.entry_date"
                            class="text-xs text-destructive"
                        >
                            {{ editForm.errors.entry_date }}
                        </span>
                    </div>

                    <!-- Notes -->
                    <div class="space-y-1.5">
                        <Label for="edit_notes">Notes / Reason</Label>
                        <Input
                            id="edit_notes"
                            v-model="editForm.notes"
                            placeholder="Audit reason or description"
                        />
                        <span
                            v-if="editForm.errors.notes"
                            class="text-xs text-destructive"
                        >
                            {{ editForm.errors.notes }}
                        </span>
                    </div>

                    <!-- Anchor Override Section -->
                    <div
                        class="space-y-2.5 rounded-lg border border-amber-500/30 bg-amber-500/5 p-3.5 text-xs"
                    >
                        <div class="flex items-center justify-between">
                            <label
                                class="flex cursor-pointer items-center gap-2"
                            >
                                <input
                                    type="checkbox"
                                    v-model="isAnchorOverride"
                                    class="size-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                                />
                                <span
                                    class="flex items-center gap-1.5 font-semibold text-foreground"
                                >
                                    <Pin
                                        class="size-3 text-amber-600 dark:text-amber-400"
                                    />
                                    Anchor Running Balance
                                </span>
                            </label>

                            <Button
                                v-if="entryToEdit?.is_balance_anchor"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="h-6 px-2 text-[11px] text-muted-foreground hover:text-destructive"
                                @click="resetAnchorToAuto"
                            >
                                Reset to Auto
                            </Button>
                        </div>

                        <div v-if="isAnchorOverride" class="space-y-1.5 pt-1">
                            <Label for="edit_running_balance" class="text-xs">
                                Desired Running Balance on this row (PHP)
                            </Label>
                            <Input
                                id="edit_running_balance"
                                v-model="editForm.running_balance"
                                type="number"
                                step="0.01"
                                placeholder="0.00"
                                class="bg-background"
                            />
                            <p class="text-[11px] text-muted-foreground">
                                Locks this row's running balance to this exact
                                amount.
                            </p>
                        </div>
                    </div>

                    <DialogFooter class="pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isEditModalOpen = false"
                            :disabled="editForm.processing"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="editForm.processing"
                            class="gap-2"
                        >
                            <Loader2
                                v-if="editForm.processing"
                                class="size-4 animate-spin"
                            />
                            <span>Save Changes</span>
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- MODAL: Delete Confirmation -->
        <Dialog v-model:open="isDeleteModalOpen">
            <DialogContent class="sm:max-w-[420px]">
                <DialogHeader>
                    <DialogTitle
                        class="flex items-center gap-2 text-destructive"
                    >
                        <Trash2 class="size-5" />
                        <span>Delete Ledger Entry</span>
                    </DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete this ledger entry? The
                        running balance will be automatically recalculated.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isDeleteModalOpen = false"
                        :disabled="isDeleting"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        @click="executeDelete"
                        :disabled="isDeleting"
                        class="gap-2"
                    >
                        <Loader2
                            v-if="isDeleting"
                            class="size-4 animate-spin"
                        />
                        <span>Confirm Delete</span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
