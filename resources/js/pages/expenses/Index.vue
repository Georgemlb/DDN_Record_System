<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Copy,
    FileText,
    Pencil,
    Plus,
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
    formatExpenseDate,
    formatExpensePaymentMethod,
    formatRecurringFrequency,
} from '@/lib/expenseFormat';
import { formatPhp } from '@/lib/transactionFormat';
import {
    create as expenseCreate,
    edit as expenseEdit,
    show as expenseShow,
} from '@/routes/expenses';
import type {
    Expense,
    ExpenseCategory,
    ExpenseFilters,
    ExpenseSummary,
} from '@/types/expense';

const props = defineProps<{
    expenses: {
        data: Expense[];
        links: any[];
        total: number;
    };
    categories: ExpenseCategory[];
    filters: ExpenseFilters;
    summary: ExpenseSummary;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Expenses',
                href: '/expenses',
            },
        ],
    },
});

const search = ref(props.filters.search ?? '');
const categoryId = ref(
    props.filters.category_id ? String(props.filters.category_id) : 'all',
);
const paymentMethod = ref(props.filters.payment_method ?? 'all');
const date = ref(props.filters.date ?? 'all');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');
const sort = ref(props.filters.sort ?? 'expense_date');
const direction = ref(props.filters.direction ?? 'desc');

let timeout: ReturnType<typeof setTimeout>;
const applyFilters = () => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(
            '/expenses',
            {
                search: search.value,
                category_id: categoryId.value,
                payment_method: paymentMethod.value,
                date: date.value,
                date_from: date.value === 'custom' ? dateFrom.value : undefined,
                date_to: date.value === 'custom' ? dateTo.value : undefined,
                sort: sort.value,
                direction: direction.value,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
};

watch(
    [search, categoryId, paymentMethod, date, dateFrom, dateTo],
    applyFilters,
);

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
    <Head title="Expenses" />

    <main class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
        <!-- Top Title & Action -->
        <section class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-primary">Disbursements</p>
                <h1
                    class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl"
                >
                    Expenses
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Record and monitor business operational expenditures and
                    bills.
                </p>
            </div>
            <Button as-child>
                <Link :href="expenseCreate()"><Plus /> Record expense</Link>
            </Button>
        </section>

        <!-- Summary Section -->
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle
                        class="text-sm font-medium text-muted-foreground"
                    >
                        Total Expenses
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-foreground">
                        {{ formatPhp(summary.total_expenses) }}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle
                        class="text-sm font-medium text-muted-foreground"
                    >
                        Cash
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">
                        {{ formatPhp(summary.total_cash) }}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle
                        class="text-sm font-medium text-muted-foreground"
                    >
                        GCash
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">
                        {{ formatPhp(summary.total_gcash) }}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle
                        class="text-sm font-medium text-muted-foreground"
                    >
                        Bank Transfer
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">
                        {{ formatPhp(summary.total_bank_transfer) }}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle
                        class="text-sm font-medium text-muted-foreground"
                    >
                        Top Category
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="summary.top_category"
                        class="truncate text-xl font-bold text-primary"
                        :title="summary.top_category.name"
                    >
                        {{ summary.top_category.name }}
                    </div>
                    <div
                        v-else
                        class="text-xl font-semibold text-muted-foreground"
                    >
                        —
                    </div>
                    <p
                        v-if="summary.top_category"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        {{ formatPhp(summary.top_category.amount) }}
                    </p>
                </CardContent>
            </Card>
        </section>

        <!-- Filters Section -->
        <section class="flex flex-col gap-4">
            <div class="flex flex-wrap items-center gap-3">
                <Input
                    v-model="search"
                    placeholder="Search expenses, payees, notes..."
                    class="w-full sm:w-64"
                />

                <Select v-model="categoryId">
                    <SelectTrigger class="w-full sm:w-[180px]">
                        <SelectValue placeholder="Category" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Categories</SelectItem>
                        <SelectItem
                            v-for="cat in categories"
                            :key="cat.id"
                            :value="String(cat.id)"
                        >
                            {{ cat.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select v-model="paymentMethod">
                    <SelectTrigger class="w-full sm:w-[160px]">
                        <SelectValue placeholder="Payment Method" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Methods</SelectItem>
                        <SelectItem value="cash">Cash</SelectItem>
                        <SelectItem value="gcash">GCash</SelectItem>
                        <SelectItem value="bank_transfer"
                            >Bank Transfer</SelectItem
                        >
                    </SelectContent>
                </Select>

                <Select v-model="date">
                    <SelectTrigger class="w-full sm:w-[160px]">
                        <SelectValue placeholder="Date Range" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Time</SelectItem>
                        <SelectItem value="today">Today</SelectItem>
                        <SelectItem value="last_7_days">Last 7 Days</SelectItem>
                        <SelectItem value="this_month">This Month</SelectItem>
                        <SelectItem value="custom">Custom Range</SelectItem>
                    </SelectContent>
                </Select>

                <div
                    v-if="date === 'custom'"
                    class="flex flex-wrap items-center gap-2"
                >
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

        <!-- Table View -->
        <section
            v-if="expenses.data.length"
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
                                @click="toggleSort('expense_date')"
                            >
                                <div class="flex items-center gap-1">
                                    Date
                                    <component
                                        :is="
                                            sort === 'expense_date'
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
                                @click="toggleSort('title')"
                            >
                                <div class="flex items-center gap-1">
                                    Title / Description
                                    <component
                                        :is="
                                            sort === 'title'
                                                ? direction === 'asc'
                                                    ? ArrowUp
                                                    : ArrowDown
                                                : ArrowUpDown
                                        "
                                        class="size-3"
                                    />
                                </div>
                            </th>
                            <th class="px-5 py-3 font-medium">Category</th>
                            <th class="px-5 py-3 font-medium">Payment</th>
                            <th class="px-5 py-3 font-medium">Payee</th>
                            <th class="px-5 py-3 font-medium">Ref #</th>
                            <th
                                class="cursor-pointer px-5 py-3 text-right font-medium hover:bg-muted"
                                @click="toggleSort('amount')"
                            >
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    Amount
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
                            <th class="px-5 py-3 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="expense in expenses.data"
                            :key="expense.id"
                            class="transition-colors hover:bg-muted/35"
                        >
                            <!-- Date -->
                            <td
                                class="px-5 py-4 whitespace-nowrap text-muted-foreground"
                            >
                                {{ formatExpenseDate(expense.expense_date) }}
                            </td>

                            <!-- Title -->
                            <td class="px-5 py-4 font-medium">
                                <Link
                                    :href="expenseShow(expense)"
                                    class="hover:text-primary hover:underline"
                                >
                                    {{ expense.title }}
                                </Link>
                                <div
                                    v-if="
                                        expense.recurring_frequency !== 'none'
                                    "
                                    class="text-xs text-muted-foreground"
                                >
                                    {{
                                        formatRecurringFrequency(
                                            expense.recurring_frequency,
                                        )
                                    }}
                                </div>
                            </td>

                            <!-- Category -->
                            <td class="px-5 py-4 whitespace-nowrap">
                                <Badge variant="secondary" class="font-normal">
                                    {{ expense.category?.name ?? '—' }}
                                </Badge>
                            </td>

                            <!-- Payment Method -->
                            <td class="px-5 py-4 whitespace-nowrap">
                                <Badge
                                    variant="outline"
                                    class="text-xs uppercase"
                                >
                                    {{
                                        formatExpensePaymentMethod(
                                            expense.payment_method,
                                        )
                                    }}
                                </Badge>
                            </td>

                            <!-- Payee -->
                            <td
                                class="max-w-40 truncate px-5 py-4 text-muted-foreground"
                            >
                                {{ expense.payee || '—' }}
                            </td>

                            <!-- Ref # -->
                            <td
                                class="px-5 py-4 font-mono text-xs text-muted-foreground"
                            >
                                {{ expense.reference_number || '—' }}
                            </td>

                            <!-- Amount -->
                            <td
                                class="px-5 py-4 text-right font-semibold whitespace-nowrap text-foreground"
                            >
                                {{ formatPhp(expense.amount) }}
                            </td>

                            <!-- Actions -->
                            <td class="px-5 py-4 text-right whitespace-nowrap">
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        as-child
                                        title="Duplicate"
                                    >
                                        <Link
                                            :href="`${expenseCreate()}?duplicate_id=${expense.id}`"
                                        >
                                            <Copy class="size-3.5" />
                                        </Link>
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        as-child
                                        title="Edit"
                                    >
                                        <Link :href="expenseEdit(expense)">
                                            <Pencil class="size-3.5" />
                                        </Link>
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :links="expenses.links" />
        </section>

        <!-- Empty State -->
        <section
            v-else
            class="flex flex-1 items-center justify-center rounded-lg border border-dashed bg-card p-8 sm:p-14"
        >
            <div class="max-w-sm text-center">
                <span
                    class="inline-flex rounded-lg bg-secondary p-3 text-secondary-foreground"
                >
                    <FileText class="size-6" />
                </span>
                <h2 class="mt-4 font-semibold">No expenses found</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    No expense records match your current search or filter
                    criteria.
                </p>
                <div class="mt-5 flex justify-center gap-2">
                    <Button
                        variant="outline"
                        @click="
                            () => {
                                search = '';
                                categoryId = 'all';
                                paymentMethod = 'all';
                                date = 'all';
                                dateFrom = '';
                                dateTo = '';
                                applyFilters();
                            }
                        "
                    >
                        Clear filters
                    </Button>
                    <Button as-child>
                        <Link :href="expenseCreate()"
                            ><Plus /> Record expense</Link
                        >
                    </Button>
                </div>
            </div>
        </section>
    </main>
</template>
