<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Plus,
    ReceiptText,
    ArrowUp,
    ArrowDown,
    ArrowUpDown,
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
} from '@/lib/transactionFormat';
import { create, show } from '@/routes/transactions';
import type { Transaction } from '@/types/transaction';

const props = defineProps<{
    transactions: {
        data: Transaction[];
        links: any[];
        total: number;
    };
    filters: {
        search?: string;
        type?: string;
        payment_method?: string;
        date?: string;
        sort?: string;
        direction?: string;
    };
    summary: {
        total_payments: number;
        total_ddn: number;
        total_cash: number;
        total_gcash: number;
    };
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

const getTransactionServiceDetail = (tx: Transaction): string => {
    switch (tx.type) {
        case 'airline':
            return tx.provider
                ? `${tx.provider} (${tx.pax ?? 1} pax)`
                : `${tx.pax ?? 1} pax`;
        case 'psa':
            return tx.psa_document_type
                ? `Doc: ${tx.psa_document_type}`
                : 'PSA Assistance';
        case 'online_assistance':
            return tx.online_assistance_type?.name ?? 'Online Assistance';
        case 'translation':
            return 'Document Translation';
        case 'printing':
            return 'Printing Service';
        default:
            return tx.service_details || '—';
    }
};

const search = ref(props.filters.search ?? '');
const type = ref(props.filters.type ?? 'all');
const paymentMethod = ref(props.filters.payment_method ?? 'all');
const date = ref(props.filters.date ?? 'all');
const sort = ref(props.filters.sort ?? 'transaction_date');
const direction = ref(props.filters.direction ?? 'desc');

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
                sort: sort.value,
                direction: direction.value,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
};

watch([search, type, paymentMethod, date], applyFilters);

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

        <!-- Filters Section -->
        <section
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex flex-1 items-center gap-4">
                <Input
                    v-model="search"
                    placeholder="Search transactions..."
                    class="max-w-xs"
                />
                <Select v-model="type">
                    <SelectTrigger class="w-[180px]">
                        <SelectValue placeholder="Transaction Type" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Types</SelectItem>
                        <SelectItem value="airline">Airline</SelectItem>
                        <SelectItem value="translation">Translation</SelectItem>
                        <SelectItem value="psa">PSA</SelectItem>
                        <SelectItem value="online_assistance"
                            >Online Assistance</SelectItem
                        >
                        <SelectItem value="printing">Printing</SelectItem>
                    </SelectContent>
                </Select>
                <Select v-model="paymentMethod">
                    <SelectTrigger class="w-[160px]">
                        <SelectValue placeholder="Payment Method" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Methods</SelectItem>
                        <SelectItem value="cash">Cash</SelectItem>
                        <SelectItem value="gcash">GCash</SelectItem>
                    </SelectContent>
                </Select>
                <Select v-model="date">
                    <SelectTrigger class="w-[160px]">
                        <SelectValue placeholder="Date Range" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Time</SelectItem>
                        <SelectItem value="today">Today</SelectItem>
                        <SelectItem value="last_7_days">Last 7 Days</SelectItem>
                        <SelectItem value="this_month">This Month</SelectItem>
                    </SelectContent>
                </Select>
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
                            <td class="px-5 py-4 font-medium">
                                <Link
                                    :href="show(transaction)"
                                    class="hover:text-primary hover:underline"
                                >
                                    {{ transaction.customer_name }}
                                </Link>
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
