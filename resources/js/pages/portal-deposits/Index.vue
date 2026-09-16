<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowUpRight,
    ChevronDown,
    ChevronUp,
    ExternalLink,
    Loader2,
    Minus,
    Pencil,
    Pin,
    Plane,
    Plus,
    Search,
    SlidersHorizontal,
    Trash2,
    Wallet,
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
    FlightPortalDeposit,
    FlightPortalFilterParams,
    FlightPortalMetrics,
    FlightPortalPaymentSource,
} from '@/types/flightPortal';

const props = defineProps<{
    deposits: {
        data: FlightPortalDeposit[];
        links: any[];
        total: number;
        from: number | null;
        to: number | null;
    };
    metrics: FlightPortalMetrics;
    filters: FlightPortalFilterParams;
    paymentSources: FlightPortalPaymentSource[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Portal Deposit',
                href: '/portal-deposits',
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
            '/portal-deposits',
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

// Top-Up Modal State & Form
const isTopUpModalOpen = ref(false);
const todayDate = new Date().toISOString().split('T')[0];

const topUpForm = useForm({
    amount: '',
    entry_date: todayDate,
    payment_source: 'bank_transfer' as FlightPortalPaymentSource,
    reference_number: '',
    notes: '',
    record_as_expense: true,
});

const openTopUpModal = () => {
    topUpForm.reset();
    topUpForm.entry_date = todayDate;
    topUpForm.payment_source = 'bank_transfer';
    topUpForm.record_as_expense = true;
    isTopUpModalOpen.value = true;
};

const submitTopUp = () => {
    topUpForm.post('/portal-deposits/top-up', {
        preserveScroll: true,
        onSuccess: () => {
            isTopUpModalOpen.value = false;
            topUpForm.reset();
        },
    });
};

// Manual Adjustment Modal State & Form
const isAdjustmentModalOpen = ref(false);
const adjustmentDirection = ref<'credit' | 'debit'>('credit');
const adjustmentForm = useForm({
    amount: '',
    entry_date: todayDate,
    notes: '',
});

const openAdjustmentModal = () => {
    adjustmentForm.reset();
    adjustmentForm.entry_date = todayDate;
    adjustmentDirection.value = 'credit';
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
        }))
        .post('/portal-deposits/adjustment', {
            preserveScroll: true,
            onSuccess: () => {
                isAdjustmentModalOpen.value = false;
                adjustmentForm.reset();
            },
        });
};

// Edit Deposit Modal State & Form
const isEditModalOpen = ref(false);
const depositToEdit = ref<FlightPortalDeposit | null>(null);
const editAdjustmentDirection = ref<'credit' | 'debit'>('credit');
const isAnchorOverride = ref(false);

const editForm = useForm({
    amount: '',
    entry_date: '',
    payment_source: 'bank_transfer' as FlightPortalPaymentSource,
    reference_number: '',
    notes: '',
    running_balance: '',
    is_balance_anchor: false,
});

const openEditModal = (deposit: FlightPortalDeposit) => {
    depositToEdit.value = deposit;
    editForm.clearErrors();

    isAnchorOverride.value = Boolean(deposit.is_balance_anchor);
    editForm.running_balance =
        deposit.running_balance !== null &&
        deposit.running_balance !== undefined
            ? String(Math.abs(Number(deposit.running_balance)))
            : '';
    editForm.is_balance_anchor = Boolean(deposit.is_balance_anchor);

    if (deposit.type === 'top_up') {
        editForm.amount = String(Math.abs(Number(deposit.amount)));
        editForm.entry_date = deposit.entry_date;
        editForm.payment_source = deposit.payment_source ?? 'bank_transfer';
        editForm.reference_number = deposit.reference_number ?? '';
        editForm.notes = deposit.notes ?? '';
    } else if (deposit.type === 'adjustment') {
        const numAmount = Number(deposit.amount);
        editAdjustmentDirection.value = numAmount < 0 ? 'debit' : 'credit';
        editForm.amount = String(Math.abs(numAmount));
        editForm.entry_date = deposit.entry_date;
        editForm.reference_number = deposit.reference_number ?? '';
        editForm.notes = deposit.notes ?? '';
    } else {
        editForm.amount = String(Math.abs(Number(deposit.amount)));
        editForm.entry_date = deposit.entry_date;
        editForm.reference_number = deposit.reference_number ?? '';
        editForm.notes = deposit.notes ?? '';
    }

    isEditModalOpen.value = true;
};

const resetAnchorToAuto = () => {
    isAnchorOverride.value = false;
    editForm.is_balance_anchor = false;
};

const submitEdit = () => {
    if (!depositToEdit.value) {
        return;
    }

    if (depositToEdit.value.type === 'adjustment') {
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
                running_balance: isAnchorOverride.value
                    ? parseFloat(
                          (data.running_balance as string) || '0',
                      ).toFixed(2)
                    : null,
            }))
            .put(`/portal-deposits/${depositToEdit.value.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    isEditModalOpen.value = false;
                    depositToEdit.value = null;
                },
            });
    } else {
        editForm
            .transform((data) => ({
                ...data,
                amount: parseFloat((editForm.amount as string) || '0').toFixed(
                    2,
                ),
                is_balance_anchor: isAnchorOverride.value,
                running_balance: isAnchorOverride.value
                    ? parseFloat(
                          (data.running_balance as string) || '0',
                      ).toFixed(2)
                    : null,
            }))
            .put(`/portal-deposits/${depositToEdit.value.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    isEditModalOpen.value = false;
                    depositToEdit.value = null;
                },
            });
    }
};

// Delete Confirmation Modal
const isDeleteModalOpen = ref(false);
const depositToDelete = ref<FlightPortalDeposit | null>(null);
const isDeleting = ref(false);

const confirmDelete = (deposit: FlightPortalDeposit) => {
    depositToDelete.value = deposit;
    isDeleteModalOpen.value = true;
};

const executeDelete = () => {
    if (!depositToDelete.value) {
        return;
    }

    isDeleting.value = true;
    router.delete(`/portal-deposits/${depositToDelete.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            isDeleteModalOpen.value = false;
            depositToDelete.value = null;
        },
    });
};

const formatSource = (source: string | null) => {
    if (!source) {
        return 'N/A';
    }

    if (source === 'bank_transfer') {
        return 'Bank Transfer';
    }

    if (source === 'gcash') {
        return 'GCash';
    }

    if (source === 'cash') {
        return 'Cash';
    }

    return source;
};

const formatEntryDate = (dateStr: string | null | undefined) => {
    if (!dateStr) {
        return '';
    }

    return String(dateStr).split('T')[0];
};

// Reordering / Move Up & Down
const movingId = ref<number | null>(null);

const moveEntry = (deposit: FlightPortalDeposit, direction: 'up' | 'down') => {
    movingId.value = deposit.id;
    router.post(
        `/portal-deposits/${deposit.id}/move`,
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
    <Head title="Flight Portal Deposit Tracker" />

    <div class="space-y-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-bold tracking-tight text-foreground md:text-3xl"
                >
                    Flight Portal Deposit
                </h1>
                <p class="text-sm text-muted-foreground">
                    Monitor live portal balance, record top-ups, and track
                    automatic airline ticket deductions.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    variant="outline"
                    @click="openAdjustmentModal"
                    class="gap-2"
                >
                    <SlidersHorizontal class="size-4" />
                    <span>Adjustment</span>
                </Button>
                <Button @click="openTopUpModal" class="gap-2">
                    <Plus class="size-4" />
                    <span>Add Top-Up</span>
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
                <span class="font-semibold">Low Portal Deposit Balance:</span>
                The current balance is
                <span class="font-bold">{{
                    formatPhp(metrics.current_balance)
                }}</span
                >, which is below the threshold of
                {{ formatPhp(metrics.low_balance_threshold) }}. Please top up
                soon to ensure smooth flight issuance.
            </div>
            <Button
                size="sm"
                variant="outline"
                class="border-amber-500/30 bg-background text-xs"
                @click="openTopUpModal"
            >
                Top Up Now
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
                        Live Portal Balance
                    </CardTitle>
                    <div
                        :class="[
                            'rounded-full p-2',
                            metrics.is_low_balance
                                ? 'bg-amber-500/20 text-amber-600 dark:text-amber-400'
                                : 'bg-primary/10 text-primary',
                        ]"
                    >
                        <Wallet class="size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold tracking-tight md:text-3xl">
                        {{ formatPhp(metrics.current_balance) }}
                    </div>
                    <p
                        class="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground"
                    >
                        <span
                            v-if="metrics.is_low_balance"
                            class="inline-flex items-center rounded-md bg-amber-500/15 px-1.5 py-0.5 font-medium text-amber-700 dark:text-amber-300"
                        >
                            Low Balance Alert
                        </span>
                        <span
                            v-else
                            class="inline-flex items-center rounded-md bg-emerald-500/15 px-1.5 py-0.5 font-medium text-emerald-700 dark:text-emerald-300"
                        >
                            Funded
                        </span>
                        <span>Estimated third-party balance</span>
                    </p>
                </CardContent>
            </Card>

            <!-- 2. Total Top-ups (This Month) -->
            <Card class="border shadow-sm">
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle
                        class="text-sm font-medium text-muted-foreground"
                    >
                        Total Top-Ups (Month)
                    </CardTitle>
                    <div
                        class="rounded-full bg-emerald-500/10 p-2 text-emerald-600 dark:text-emerald-400"
                    >
                        <ArrowUpRight class="size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div
                        class="text-2xl font-bold tracking-tight text-emerald-600 md:text-3xl dark:text-emerald-400"
                    >
                        {{ formatPhp(metrics.total_top_ups_month) }}
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Total funds deposited this month
                    </p>
                </CardContent>
            </Card>

            <!-- 3. Total Ticket Deductions (This Month) -->
            <Card class="border shadow-sm">
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle
                        class="text-sm font-medium text-muted-foreground"
                    >
                        Ticket Deductions (Month)
                    </CardTitle>
                    <div
                        class="rounded-full bg-blue-500/10 p-2 text-blue-600 dark:text-blue-400"
                    >
                        <Plane class="size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div
                        class="text-2xl font-bold tracking-tight text-blue-600 md:text-3xl dark:text-blue-400"
                    >
                        {{ formatPhp(metrics.total_deductions_month) }}
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Net ticket cost deducted this month
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
                                <SelectItem value="top_up"
                                    >Top-Ups (+)</SelectItem
                                >
                                <SelectItem value="ticket_deduction"
                                    >Ticket Deductions (-)</SelectItem
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
                    >Deposit Ledger & Movement History</CardTitle
                >
                <CardDescription>
                    Complete audit trail of portal reloads, ticket bookings, and
                    balance reconciliations.
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
                                <th class="px-4 py-3">Reference / Ticket</th>
                                <th class="px-4 py-3 text-right">Amount</th>
                                <th class="px-4 py-3 text-right">
                                    Running Balance
                                </th>
                                <th class="px-4 py-3">Source / Details</th>
                                <th class="px-4 py-3">Recorded By</th>
                                <th class="px-4 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-if="deposits.data.length === 0"
                                class="text-center text-muted-foreground"
                            >
                                <td colspan="8" class="py-12 text-center">
                                    <div
                                        class="mx-auto flex max-w-xs flex-col items-center justify-center"
                                    >
                                        <Wallet
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
                                                    : 'Add your first portal top-up above to start tracking.'
                                            }}
                                        </p>
                                    </div>
                                </td>
                            </tr>
                            <tr
                                v-for="entry in deposits.data"
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
                                        v-if="entry.type === 'top_up'"
                                        variant="default"
                                        class="bg-emerald-600/15 text-emerald-700 hover:bg-emerald-600/20 dark:text-emerald-300"
                                    >
                                        Top-Up
                                    </Badge>
                                    <Badge
                                        v-else-if="
                                            entry.type === 'ticket_deduction'
                                        "
                                        variant="secondary"
                                        class="bg-blue-500/15 text-blue-700 hover:bg-blue-500/20 dark:text-blue-300"
                                    >
                                        Ticket Deduction
                                    </Badge>
                                    <Badge
                                        v-else
                                        variant="outline"
                                        class="bg-purple-500/15 text-purple-700 hover:bg-purple-500/20 dark:text-purple-300"
                                    >
                                        Adjustment
                                    </Badge>
                                </td>

                                <!-- Reference / Ticket -->
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
                                            Pax / Client:
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
                                        v-if="
                                            entry.type === 'top_up' ||
                                            (entry.type === 'adjustment' &&
                                                Number(entry.amount) > 0)
                                        "
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

                                <!-- Source / Details -->
                                <td
                                    class="max-w-[220px] truncate px-4 py-3 text-xs text-muted-foreground"
                                >
                                    <div
                                        v-if="entry.payment_source"
                                        class="font-medium text-foreground"
                                    >
                                        {{ formatSource(entry.payment_source) }}
                                    </div>
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
                                        <!-- Reorder buttons (active when multiple entries exist on this date) -->
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
                                        <template
                                            v-if="
                                                entry.type !==
                                                'ticket_deduction'
                                            "
                                        >
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="size-7 text-muted-foreground hover:text-foreground"
                                                @click="openEditModal(entry)"
                                                title="Edit deposit entry"
                                            >
                                                <Pencil class="size-3.5" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="size-7 text-muted-foreground hover:text-destructive"
                                                @click="confirmDelete(entry)"
                                                title="Delete entry"
                                            >
                                                <Trash2 class="size-3.5" />
                                            </Button>
                                        </template>
                                        <template v-else>
                                            <span
                                                class="text-xs text-muted-foreground/60"
                                            >
                                                Auto
                                            </span>
                                            <Link
                                                v-if="entry.transaction_id"
                                                :href="`/transactions/${entry.transaction_id}/edit`"
                                                class="inline-flex size-7 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                                title="Edit linked airline transaction"
                                            >
                                                <ExternalLink
                                                    class="size-3.5"
                                                />
                                            </Link>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="border-t p-4">
                    <Pagination :links="deposits.links" />
                </div>
            </CardContent>
        </Card>

        <!-- MODAL: Add Top-up -->
        <Dialog v-model:open="isTopUpModalOpen">
            <DialogContent class="sm:max-w-[480px]">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Plus class="size-5 text-emerald-600" />
                        <span>Add Flight Portal Top-Up</span>
                    </DialogTitle>
                    <DialogDescription>
                        Deposit funds to your third-party flight booking portal
                        to increase your available ticketing balance.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitTopUp" class="space-y-4 py-2">
                    <!-- Amount -->
                    <div class="space-y-1.5">
                        <Label for="topup_amount"
                            >Top-Up Amount (PHP)
                            <span class="text-destructive">*</span></Label
                        >
                        <Input
                            id="topup_amount"
                            v-model="topUpForm.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            required
                            placeholder="0.00"
                        />
                        <span
                            v-if="topUpForm.errors.amount"
                            class="text-xs text-destructive"
                        >
                            {{ topUpForm.errors.amount }}
                        </span>
                    </div>

                    <!-- Date & Payment Source -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label for="topup_date"
                                >Date
                                <span class="text-destructive">*</span></Label
                            >
                            <Input
                                id="topup_date"
                                v-model="topUpForm.entry_date"
                                type="date"
                                required
                            />
                            <span
                                v-if="topUpForm.errors.entry_date"
                                class="text-xs text-destructive"
                            >
                                {{ topUpForm.errors.entry_date }}
                            </span>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="topup_source"
                                >Payment Source
                                <span class="text-destructive">*</span></Label
                            >
                            <Select v-model="topUpForm.payment_source">
                                <SelectTrigger id="topup_source" class="w-full">
                                    <SelectValue placeholder="Select Source" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="bank_transfer"
                                        >Bank Transfer</SelectItem
                                    >
                                    <SelectItem value="gcash">GCash</SelectItem>
                                    <SelectItem value="cash">Cash</SelectItem>
                                </SelectContent>
                            </Select>
                            <span
                                v-if="topUpForm.errors.payment_source"
                                class="text-xs text-destructive"
                            >
                                {{ topUpForm.errors.payment_source }}
                            </span>
                        </div>
                    </div>

                    <!-- Reference Number -->
                    <div class="space-y-1.5">
                        <Label for="topup_ref"
                            >Reference Number (Optional)</Label
                        >
                        <Input
                            id="topup_ref"
                            v-model="topUpForm.reference_number"
                            placeholder="e.g. Bank Deposit Slip # or Ref ID"
                        />
                        <span
                            v-if="topUpForm.errors.reference_number"
                            class="text-xs text-destructive"
                        >
                            {{ topUpForm.errors.reference_number }}
                        </span>
                    </div>

                    <!-- Notes -->
                    <div class="space-y-1.5">
                        <Label for="topup_notes"
                            >Notes / Purpose (Optional)</Label
                        >
                        <Input
                            id="topup_notes"
                            v-model="topUpForm.notes"
                            placeholder="e.g. Reload via BDO Online for peak season"
                        />
                        <span
                            v-if="topUpForm.errors.notes"
                            class="text-xs text-destructive"
                        >
                            {{ topUpForm.errors.notes }}
                        </span>
                    </div>

                    <!-- Record as Expense Checkbox -->
                    <div class="rounded-lg border bg-muted/30 p-3">
                        <label class="flex cursor-pointer items-start gap-2.5">
                            <input
                                type="checkbox"
                                v-model="topUpForm.record_as_expense"
                                class="mt-0.5 size-4 rounded border-gray-300 text-primary focus:ring-primary"
                            />
                            <div class="space-y-0.5">
                                <span class="text-sm leading-none font-medium"
                                    >Record as Drawer Cash-Out / Fund Transfer</span
                                >
                                <p class="text-xs text-muted-foreground">
                                    Records cash-out under "Flight Portal Reload" in the Daily Cash Settlement. Excluded from operating expenses and net profit.
                                </p>
                            </div>
                        </label>
                    </div>

                    <DialogFooter class="pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isTopUpModalOpen = false"
                            :disabled="topUpForm.processing"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="topUpForm.processing"
                            class="gap-2"
                        >
                            <Loader2
                                v-if="topUpForm.processing"
                                class="size-4 animate-spin"
                            />
                            <span>Save Top-Up</span>
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- MODAL: Manual Adjustment -->
        <Dialog v-model:open="isAdjustmentModalOpen">
            <DialogContent class="sm:max-w-[460px]">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <SlidersHorizontal class="size-5 text-primary" />
                        <span>Manual Balance Adjustment</span>
                    </DialogTitle>
                    <DialogDescription>
                        Reconcile discrepancies between DDN records and the
                        third-party portal balance.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitAdjustment" class="space-y-4 py-2">
                    <!-- Adjustment Type -->
                    <div class="space-y-1.5">
                        <Label>Adjustment Type</Label>
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
                                <span>Credit (+ Add)</span>
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
                                <span>Debit (- Deduct)</span>
                            </Button>
                        </div>
                    </div>

                    <!-- Amount -->
                    <div class="space-y-1.5">
                        <Label for="adj_amount"
                            >Adjustment Amount (PHP)
                            <span class="text-destructive">*</span></Label
                        >
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
                        <Label for="adj_date"
                            >Date <span class="text-destructive">*</span></Label
                        >
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

                    <!-- Reason / Notes -->
                    <div class="space-y-1.5">
                        <Label for="adj_notes"
                            >Audit Reason / Explanation
                            <span class="text-destructive">*</span></Label
                        >
                        <Input
                            id="adj_notes"
                            v-model="adjustmentForm.notes"
                            required
                            placeholder="e.g. Portal monthly bonus credit, or fee discrepancy"
                        />
                        <span
                            v-if="adjustmentForm.errors.notes"
                            class="text-xs text-destructive"
                        >
                            {{ adjustmentForm.errors.notes }}
                        </span>
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
                            <span>Apply Adjustment</span>
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
                        <span
                            v-if="depositToDelete?.expense_id"
                            class="mt-2 block font-medium text-destructive"
                        >
                            Notice: This entry is linked to an Expense record.
                            Deleting this will also remove the linked expense.
                        </span>
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

        <!-- MODAL: Edit Deposit Entry -->
        <Dialog v-model:open="isEditModalOpen">
            <DialogContent class="sm:max-w-[480px]">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Pencil class="size-5 text-primary" />
                        <span v-if="depositToEdit?.type === 'top_up'"
                            >Edit Flight Portal Top-Up</span
                        >
                        <span v-else-if="depositToEdit?.type === 'adjustment'"
                            >Edit Balance Adjustment</span
                        >
                        <span v-else>Edit Deposit Entry</span>
                    </DialogTitle>
                    <DialogDescription>
                        <span v-if="depositToEdit?.type === 'top_up'">
                            Update top-up details. Running balances and any
                            linked expense records will automatically
                            synchronize.
                        </span>
                        <span v-else-if="depositToEdit?.type === 'adjustment'">
                            Update manual balance adjustment. Running balances
                            will be recalculated chronologically.
                        </span>
                        <span v-else> Update deposit ledger record. </span>
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitEdit" class="space-y-4 py-2">
                    <!-- Linked Expense Notice if applicable -->
                    <div
                        v-if="depositToEdit?.expense"
                        class="flex items-start gap-2.5 rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-3 text-xs text-emerald-900 dark:text-emerald-200"
                    >
                        <Wallet
                            class="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                        />
                        <div>
                            <span class="font-semibold"
                                >Linked to Expense #{{
                                    depositToEdit.expense.id
                                }}:</span
                            >
                            Updating this top-up will automatically update the
                            agency cash/expense ledger and daily cash
                            settlement.
                        </div>
                    </div>

                    <!-- Top-Up Edit Form -->
                    <template v-if="depositToEdit?.type === 'top_up'">
                        <!-- Amount -->
                        <div class="space-y-1.5">
                            <Label for="edit_topup_amount"
                                >Top-Up Amount (PHP)
                                <span class="text-destructive">*</span></Label
                            >
                            <Input
                                id="edit_topup_amount"
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

                        <!-- Date & Payment Source -->
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1.5">
                                <Label for="edit_topup_date"
                                    >Date
                                    <span class="text-destructive"
                                        >*</span
                                    ></Label
                                >
                                <Input
                                    id="edit_topup_date"
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
                            <div class="space-y-1.5">
                                <Label for="edit_topup_source"
                                    >Payment Source
                                    <span class="text-destructive"
                                        >*</span
                                    ></Label
                                >
                                <Select v-model="editForm.payment_source">
                                    <SelectTrigger
                                        id="edit_topup_source"
                                        class="w-full"
                                    >
                                        <SelectValue
                                            placeholder="Select Source"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="bank_transfer"
                                            >Bank Transfer</SelectItem
                                        >
                                        <SelectItem value="gcash"
                                            >GCash</SelectItem
                                        >
                                        <SelectItem value="cash"
                                            >Cash</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                                <span
                                    v-if="editForm.errors.payment_source"
                                    class="text-xs text-destructive"
                                >
                                    {{ editForm.errors.payment_source }}
                                </span>
                            </div>
                        </div>

                        <!-- Reference Number -->
                        <div class="space-y-1.5">
                            <Label for="edit_topup_ref"
                                >Reference Number (Optional)</Label
                            >
                            <Input
                                id="edit_topup_ref"
                                v-model="editForm.reference_number"
                                placeholder="e.g. Bank Deposit Slip # or Ref ID"
                            />
                            <span
                                v-if="editForm.errors.reference_number"
                                class="text-xs text-destructive"
                            >
                                {{ editForm.errors.reference_number }}
                            </span>
                        </div>

                        <!-- Notes -->
                        <div class="space-y-1.5">
                            <Label for="edit_topup_notes"
                                >Notes / Purpose (Optional)</Label
                            >
                            <Input
                                id="edit_topup_notes"
                                v-model="editForm.notes"
                                placeholder="e.g. Reload via BDO Online for peak season"
                            />
                            <span
                                v-if="editForm.errors.notes"
                                class="text-xs text-destructive"
                            >
                                {{ editForm.errors.notes }}
                            </span>
                        </div>
                    </template>

                    <!-- Adjustment Edit Form -->
                    <template v-else-if="depositToEdit?.type === 'adjustment'">
                        <!-- Adjustment Direction -->
                        <div class="space-y-1.5">
                            <Label>Adjustment Type</Label>
                            <div class="grid grid-cols-2 gap-3">
                                <Button
                                    type="button"
                                    :variant="
                                        editAdjustmentDirection === 'credit'
                                            ? 'default'
                                            : 'outline'
                                    "
                                    class="w-full gap-2"
                                    @click="editAdjustmentDirection = 'credit'"
                                >
                                    <Plus class="size-4" />
                                    <span>Credit (+ Add)</span>
                                </Button>
                                <Button
                                    type="button"
                                    :variant="
                                        editAdjustmentDirection === 'debit'
                                            ? 'destructive'
                                            : 'outline'
                                    "
                                    class="w-full gap-2"
                                    @click="editAdjustmentDirection = 'debit'"
                                >
                                    <Minus class="size-4" />
                                    <span>Debit (- Deduct)</span>
                                </Button>
                            </div>
                        </div>

                        <!-- Amount -->
                        <div class="space-y-1.5">
                            <Label for="edit_adj_amount"
                                >Adjustment Amount (PHP)
                                <span class="text-destructive">*</span></Label
                            >
                            <Input
                                id="edit_adj_amount"
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
                            <Label for="edit_adj_date"
                                >Date
                                <span class="text-destructive">*</span></Label
                            >
                            <Input
                                id="edit_adj_date"
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

                        <!-- Notes / Audit Reason -->
                        <div class="space-y-1.5">
                            <Label for="edit_adj_notes"
                                >Audit Reason / Explanation
                                <span class="text-destructive">*</span></Label
                            >
                            <Input
                                id="edit_adj_notes"
                                v-model="editForm.notes"
                                required
                                placeholder="e.g. Portal monthly bonus credit, or fee discrepancy"
                            />
                            <span
                                v-if="editForm.errors.notes"
                                class="text-xs text-destructive"
                            >
                                {{ editForm.errors.notes }}
                            </span>
                        </div>
                    </template>

                    <!-- Fallback / Unlinked -->
                    <template v-else>
                        <div class="space-y-1.5">
                            <Label for="edit_gen_amount"
                                >Amount (PHP)
                                <span class="text-destructive">*</span></Label
                            >
                            <Input
                                id="edit_gen_amount"
                                v-model="editForm.amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                required
                            />
                            <span
                                v-if="editForm.errors.amount"
                                class="text-xs text-destructive"
                            >
                                {{ editForm.errors.amount }}
                            </span>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="edit_gen_date"
                                >Date
                                <span class="text-destructive">*</span></Label
                            >
                            <Input
                                id="edit_gen_date"
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

                        <div class="space-y-1.5">
                            <Label for="edit_gen_notes">Notes</Label>
                            <Input
                                id="edit_gen_notes"
                                v-model="editForm.notes"
                            />
                            <span
                                v-if="editForm.errors.notes"
                                class="text-xs text-destructive"
                            >
                                {{ editForm.errors.notes }}
                            </span>
                        </div>
                    </template>

                    <!-- Running Balance Anchor Override Section -->
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
                                    Anchor Running Balance (Manual Calibration)
                                </span>
                            </label>

                            <Button
                                v-if="depositToEdit?.is_balance_anchor"
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
                                Setting an anchor sets this row's running
                                balance explicitly. Subsequent rows calculate
                                forward from this exact balance.
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
    </div>
</template>
