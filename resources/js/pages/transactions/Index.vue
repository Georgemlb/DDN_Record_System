<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Plus,
    ReceiptText,
    ArrowUp,
    ArrowDown,
    ArrowUpDown,
    RotateCcw,
    Trash2,
    CheckCircle2,
    Eye,
} from '@lucide/vue';
import { ref, watch } from 'vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    formatPaymentMethod,
    formatPhp,
    formatTransactionDate,
    formatTransactionType,
    getTransactionServiceDetail,
} from '@/lib/transactionFormat';
import { create, show } from '@/routes/transactions';
import type {
    Transaction,
    TransactionCounts,
    TransactionFilters,
    TransactionSummary,
} from '@/types/transaction';

const props = defineProps<{
    transactions: {
        data: Transaction[];
        links: any[];
        total: number;
    };
    filters: TransactionFilters;
    summary: TransactionSummary;
    counts?: TransactionCounts;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Transactions',
                href: '/transactions',
            },
        ],
    },
});

const search = ref(props.filters.search ?? '');
const type = ref(props.filters.type ?? 'all');
const paymentMethod = ref(props.filters.payment_method ?? 'all');
const date = ref(props.filters.date ?? 'all');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');
const sort = ref(props.filters.sort ?? 'transaction_date');
const direction = ref(props.filters.direction ?? 'desc');
const status = ref<'active' | 'trash'>(
    props.filters.status === 'trash' ? 'trash' : 'active',
);

let timeout: ReturnType<typeof setTimeout>;
const applyFilters = () => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(
            '/transactions',
            {
                search: search.value,
                type: type.value,
                payment_method: paymentMethod.value,
                date: date.value,
                date_from: date.value === 'custom' ? dateFrom.value : undefined,
                date_to: date.value === 'custom' ? dateTo.value : undefined,
                sort: sort.value,
                direction: direction.value,
                status: status.value,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
};

watch(
    [search, type, paymentMethod, date, dateFrom, dateTo, status],
    applyFilters,
);

const setStatus = (newStatus: 'active' | 'trash') => {
    if (status.value !== newStatus) {
        status.value = newStatus;
    }
};

const restoringId = ref<number | null>(null);
const restoreTransaction = (id: number) => {
    restoringId.value = id;
    router.post(
        `/transactions/${id}/restore`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                restoringId.value = null;
            },
        },
    );
};

const forceDeletingId = ref<number | null>(null);
const forceDeleteTransaction = (id: number) => {
    if (
        confirm(
            'Are you sure you want to permanently delete this transaction? This action cannot be undone.',
        )
    ) {
        forceDeletingId.value = id;
        router.delete(`/transactions/${id}/force-delete`, {
            preserveScroll: true,
            onFinish: () => {
                forceDeletingId.value = null;
            },
        });
    }
};

const toggleSort = (field: string) => {
    if (sort.value === field) {
        direction.value = direction.value === 'asc' ? 'desc' : 'asc';
    } else {
        sort.value = field;
        direction.value = 'asc';
    }

    applyFilters();
};
</script>

<template>
    <Head title="Transactions" />

    <main class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
        <section class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-primary">Records</p>
                <h1
                    class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl"
                >
                    Transactions
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Record and manage your business income and service
                    transactions.
                </p>
            </div>
            <Button as-child>
                <Link :href="create()"><Plus /> Record transaction</Link>
            </Button>
        </section>

        <!-- Status View Tabs (Active vs Trash) -->
        <section class="flex items-center gap-2 border-b pb-3">
            <Button
                :variant="status === 'active' ? 'default' : 'outline'"
                size="sm"
                class="gap-1.5"
                @click="setStatus('active')"
            >
                <CheckCircle2 class="size-4" />
                Active Records
                <Badge
                    v-if="counts"
                    :variant="status === 'active' ? 'secondary' : 'outline'"
                    class="ml-1 text-xs"
                >
                    {{ counts.active }}
                </Badge>
            </Button>
            <Button
                :variant="status === 'trash' ? 'destructive' : 'outline'"
                size="sm"
                class="gap-1.5"
                @click="setStatus('trash')"
            >
                <Trash2 class="size-4" />
                Trash
                <Badge
                    v-if="counts"
                    :variant="status === 'trash' ? 'secondary' : 'outline'"
                    class="ml-1 text-xs"
                >
                    {{ counts.trash }}
                </Badge>
            </Button>
        </section>

        <!-- Summary Section -->
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground"
                        >Total Payments</CardTitle
                    >
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">
                        {{ formatPhp(summary.total_payments) }}
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground"
                        >Total DDN Amount</CardTitle
                    >
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-primary">
                        {{ formatPhp(summary.total_ddn) }}
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground"
                        >Cash Received</CardTitle
                    >
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">
                        {{ formatPhp(summary.total_cash) }}
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground"
                        >GCash Received</CardTitle
                    >
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">
                        {{ formatPhp(summary.total_gcash) }}
                    </div>
                </CardContent>
            </Card>
        </section>

        <!-- Filter and Search Section -->
        <section
            class="flex flex-col gap-4 rounded-lg border bg-card p-4 sm:p-5"
        >
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Input
                    v-model="search"
                    placeholder="Search customer, ref #, or details..."
                    class="w-full"
                />

                <Select v-model="type">
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="All types" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Service Types</SelectItem>
                        <SelectItem value="airline">Airline</SelectItem>
                        <SelectItem value="translation">Translation</SelectItem>
                        <SelectItem value="psa">PSA Application</SelectItem>
                        <SelectItem value="online_assistance"
                            >Online Assistance</SelectItem
                        >
                        <SelectItem value="printing"
                            >Printing & Services</SelectItem
                        >
                    </SelectContent>
                </Select>

                <Select v-model="paymentMethod">
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="All methods" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Payment Methods</SelectItem>
                        <SelectItem value="cash">Cash</SelectItem>
                        <SelectItem value="gcash">GCash</SelectItem>
                    </SelectContent>
                </Select>

                <Select v-model="date">
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="All dates" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Dates</SelectItem>
                        <SelectItem value="today">Today</SelectItem>
                        <SelectItem value="last_7_days">Last 7 Days</SelectItem>
                        <SelectItem value="this_month">This Month</SelectItem>
                        <SelectItem value="custom">Custom Range</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Custom Date Range Row -->
            <div
                v-if="date === 'custom'"
                class="flex flex-wrap items-center gap-2 border-t pt-3"
            >
                <span class="text-xs font-medium text-muted-foreground"
                    >Date range:</span
                >
                <div class="flex items-center gap-2">
                    <Input
                        v-model="dateFrom"
                        type="date"
                        placeholder="From date"
                        class="w-auto text-xs"
                    />
                    <span class="text-xs text-muted-foreground">to</span>
                    <Input
                        v-model="dateTo"
                        type="date"
                        placeholder="To date"
                        class="w-auto text-xs"
                    />
                </div>
            </div>
        </section>

        <!-- Table Section -->
        <section
            v-if="transactions.data.length"
            class="overflow-hidden rounded-lg border bg-card shadow-xs"
        >
            <div class="overflow-x-auto">
                <table class="w-full min-w-185 text-left text-sm">
                    <thead
                        class="border-b bg-muted/40 text-xs tracking-wide text-muted-foreground uppercase"
                    >
                        <tr>
                            <th
                                class="cursor-pointer px-5 py-3 font-medium hover:bg-muted"
                                @click="toggleSort('transaction_date')"
                            >
                                <div class="flex items-center gap-1">
                                    Date
                                    <component
                                        :is="
                                            sort === 'transaction_date'
                                                ? direction === 'asc'
                                                    ? ArrowUp
                                                    : ArrowDown
                                                : ArrowUpDown
                                        "
                                        class="size-3"
                                    />
                                </div>
                            </th>
                            <th
                                class="cursor-pointer px-5 py-3 font-medium hover:bg-muted"
                                @click="toggleSort('reference_number')"
                            >
                                <div class="flex items-center gap-1">
                                    Ref #
                                    <component
                                        :is="
                                            sort === 'reference_number'
                                                ? direction === 'asc'
                                                    ? ArrowUp
                                                    : ArrowDown
                                                : ArrowUpDown
                                        "
                                        class="size-3"
                                    />
                                </div>
                            </th>
                            <th
                                class="cursor-pointer px-5 py-3 font-medium hover:bg-muted"
                                @click="toggleSort('customer_name')"
                            >
                                <div class="flex items-center gap-1">
                                    Customer / Person
                                    <component
                                        :is="
                                            sort === 'customer_name'
                                                ? direction === 'asc'
                                                    ? ArrowUp
                                                    : ArrowDown
                                                : ArrowUpDown
                                        "
                                        class="size-3"
                                    />
                                </div>
                            </th>
                            <th class="px-5 py-3 font-medium">Service Type</th>
                            <th class="px-5 py-3 font-medium">Details</th>
                            <th class="px-5 py-3 font-medium">Payment</th>
                            <th
                                class="cursor-pointer px-5 py-3 text-right font-medium hover:bg-muted"
                                @click="toggleSort('amount')"
                            >
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    Customer Total
                                    <component
                                        :is="
                                            sort === 'amount'
                                                ? direction === 'asc'
                                                    ? ArrowUp
                                                    : ArrowDown
                                                : ArrowUpDown
                                        "
                                        class="size-3"
                                    />
                                </div>
                            </th>
                            <th
                                class="cursor-pointer px-5 py-3 text-right font-medium hover:bg-muted"
                                @click="toggleSort('ddn_amount')"
                            >
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    DDN Amount
                                    <component
                                        :is="
                                            sort === 'ddn_amount'
                                                ? direction === 'asc'
                                                    ? ArrowUp
                                                    : ArrowDown
                                                : ArrowUpDown
                                        "
                                        class="size-3"
                                    />
                                </div>
                            </th>
                            <th class="px-5 py-3 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="transaction in transactions.data"
                            :key="transaction.id"
                            class="transition-colors hover:bg-muted/35"
                        >
                            <td
                                class="px-5 py-4 whitespace-nowrap text-muted-foreground"
                            >
                                {{
                                    formatTransactionDate(
                                        transaction.transaction_date,
                                    )
                                }}
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap">
                                <Badge
                                    variant="outline"
                                    class="font-mono text-xs font-normal"
                                >
                                    {{ transaction.reference_number || '—' }}
                                </Badge>
                            </td>
                            <td class="px-5 py-4 font-medium">
                                <div>
                                    <Link
                                        :href="show(transaction)"
                                        class="hover:text-primary hover:underline"
                                    >
                                        {{ transaction.customer_name }}
                                    </Link>
                                    <span
                                        v-if="transaction.user"
                                        class="block text-xs font-normal text-muted-foreground"
                                    >
                                        by {{ transaction.user.name }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap">
                                <Badge variant="secondary" class="font-normal">
                                    {{
                                        formatTransactionType(transaction.type)
                                    }}
                                </Badge>
                            </td>
                            <td
                                class="max-w-60 truncate px-5 py-4 text-muted-foreground"
                            >
                                {{ getTransactionServiceDetail(transaction) }}
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap">
                                <Badge
                                    variant="outline"
                                    class="text-xs uppercase"
                                >
                                    {{
                                        formatPaymentMethod(
                                            transaction.payment_method,
                                        )
                                    }}
                                </Badge>
                            </td>
                            <td
                                class="px-5 py-4 text-right font-medium whitespace-nowrap"
                            >
                                {{ formatPhp(transaction.amount) }}
                            </td>
                            <td
                                class="px-5 py-4 text-right font-semibold whitespace-nowrap text-primary"
                            >
                                {{
                                    formatPhp(
                                        transaction.ddn_amount ??
                                            transaction.amount,
                                    )
                                }}
                            </td>
                            <td class="px-5 py-4 text-right whitespace-nowrap">
                                <div
                                    v-if="status === 'trash'"
                                    class="flex items-center justify-end gap-1.5"
                                >
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="h-8 gap-1 text-xs"
                                        :disabled="
                                            restoringId === transaction.id
                                        "
                                        @click="
                                            restoreTransaction(transaction.id)
                                        "
                                    >
                                        <RotateCcw class="size-3.5" />
                                        Restore
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        class="h-8 gap-1 text-xs"
                                        :disabled="
                                            forceDeletingId === transaction.id
                                        "
                                        @click="
                                            forceDeleteTransaction(
                                                transaction.id,
                                            )
                                        "
                                    >
                                        <Trash2 class="size-3.5" />
                                        Delete
                                    </Button>
                                </div>
                                <div
                                    v-else
                                    class="flex items-center justify-end"
                                >
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="h-8 px-2"
                                        as-child
                                    >
                                        <Link
                                            :href="show(transaction)"
                                            title="View details"
                                        >
                                            <Eye class="size-4" />
                                        </Link>
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :links="transactions.links" />
        </section>

        <section
            v-else
            class="flex flex-1 items-center justify-center rounded-lg border border-dashed bg-card p-8 sm:p-14"
        >
            <div class="max-w-sm text-center">
                <span
                    class="inline-flex rounded-lg bg-secondary p-3 text-secondary-foreground"
                >
                    <ReceiptText class="size-6" />
                </span>
                <h2 class="mt-4 font-semibold">No transactions found</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    No transactions match your current filters.
                </p>
                <div class="mt-5 flex justify-center gap-2">
                    <Button
                        variant="outline"
                        @click="
                            () => {
                                search = '';
                                type = 'all';
                                paymentMethod = 'all';
                                date = 'all';
                                applyFilters();
                            }
                        "
                    >
                        Clear filters
                    </Button>
                    <Button as-child>
                        <Link :href="create()"
                            ><Plus /> Record transaction</Link
                        >
                    </Button>
                </div>
            </div>
        </section>
    </main>
</template>
