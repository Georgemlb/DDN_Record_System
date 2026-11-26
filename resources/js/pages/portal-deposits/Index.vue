<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowUpRight,
    ChevronDown,
    ChevronUp,
    ExternalLink,
    Loader2,
    Pencil,
    Pin,
    Plane,
    Plus,
    Search,
    SlidersHorizontal,
    Trash2,
    Wallet,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import FilterToggleBar from '@/components/FilterToggleBar.vue';
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
import PortalAdjustmentModal from './components/PortalAdjustmentModal.vue';
import PortalEditModal from './components/PortalEditModal.vue';
import PortalTopUpModal from './components/PortalTopUpModal.vue';

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

// Modal Refs
const topUpModalRef = ref<InstanceType<typeof PortalTopUpModal> | null>(null);
const adjustmentModalRef = ref<InstanceType<
    typeof PortalAdjustmentModal
> | null>(null);
const editModalRef = ref<InstanceType<typeof PortalEditModal> | null>(null);

const openTopUpModal = () => {
    topUpModalRef.value?.open();
};

const openAdjustmentModal = () => {
    adjustmentModalRef.value?.open();
};

const openEditModal = (deposit: FlightPortalDeposit) => {
    editModalRef.value?.open(deposit);
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
                <FilterToggleBar
                    :active-count="
                        (typeFilter !== 'all' ? 1 : 0) +
                        (dateFrom ? 1 : 0) +
                        (dateTo ? 1 : 0)
                    "
                    @clear="clearFilters"
                >
                    <template #search>
                        <div class="relative">
                            <Search
                                class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                v-model="search"
                                placeholder="Search reference, customer, or notes..."
                                class="pl-9 text-sm"
                            />
                        </div>
                    </template>

                    <template #filters>
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

                        <Input
                            v-model="dateFrom"
                            type="date"
                            placeholder="From Date"
                            class="text-sm"
                        />

                        <Input
                            v-model="dateTo"
                            type="date"
                            placeholder="To Date"
                            class="text-sm"
                        />
                    </template>
                </FilterToggleBar>
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
        <PortalTopUpModal ref="topUpModalRef" />

        <!-- MODAL: Manual Adjustment -->
        <PortalAdjustmentModal ref="adjustmentModalRef" />

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
        <PortalEditModal ref="editModalRef" />
    </div>
</template>
