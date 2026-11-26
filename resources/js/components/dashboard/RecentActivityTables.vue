<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    formatExpenseDate,
    formatExpensePaymentMethod,
} from '@/lib/expenseFormat';
import {
    formatPhp,
    formatTransactionDate,
    formatTransactionType,
    getTransactionServiceDetail,
} from '@/lib/transactionFormat';
import { show as transactionShow } from '@/routes/transactions';
import type { Expense } from '@/types/expense';
import type { Transaction } from '@/types/transaction';

defineProps<{
    recentTransactions: Transaction[];
    recentExpenses: Expense[];
}>();
</script>

<template>
    <section class="grid gap-6 lg:grid-cols-2">
        <Card class="shadow-sm">
            <CardHeader
                class="flex flex-row items-center justify-between gap-4 border-b pb-4"
            >
                <div>
                    <CardTitle class="text-base">Recent Transactions</CardTitle>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Latest 5 recorded counter transactions
                    </p>
                </div>
                <Button variant="ghost" size="sm" as-child>
                    <Link
                        href="/transactions"
                        class="flex items-center gap-1.5 text-xs text-primary"
                    >
                        View all <ArrowRight class="size-3.5" />
                    </Link>
                </Button>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="recentTransactions.length > 0" class="divide-y">
                    <Link
                        v-for="tx in recentTransactions"
                        :key="tx.id"
                        :href="transactionShow.url({ transaction: tx.id })"
                        class="flex items-center justify-between gap-3 px-5 py-3.5 transition-colors hover:bg-muted/40"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <p class="truncate text-sm font-medium">
                                    {{ tx.customer_name }}
                                </p>
                                <Badge
                                    variant="outline"
                                    class="shrink-0 text-[10px]"
                                >
                                    {{ formatTransactionType(tx.type) }}
                                </Badge>
                            </div>
                            <p
                                class="mt-0.5 truncate text-xs text-muted-foreground"
                            >
                                {{ getTransactionServiceDetail(tx) }} ·
                                {{ formatTransactionDate(tx.transaction_date) }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold">
                                {{ formatPhp(tx.amount) }}
                            </p>
                            <span
                                :class="[
                                    'inline-block text-[11px] font-medium capitalize',
                                    tx.payment_method === 'cash'
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : tx.payment_method === 'gcash'
                                          ? 'text-blue-600 dark:text-blue-400'
                                          : 'text-purple-600 dark:text-purple-400',
                                ]"
                            >
                                {{ tx.payment_method }}
                            </span>
                        </div>
                    </Link>
                </div>
                <div
                    v-else
                    class="flex h-32 items-center justify-center text-sm text-muted-foreground"
                >
                    No transactions recorded yet.
                </div>
            </CardContent>
        </Card>

        <Card class="shadow-sm">
            <CardHeader
                class="flex flex-row items-center justify-between gap-4 border-b pb-4"
            >
                <div>
                    <CardTitle class="text-base">Recent Expenses</CardTitle>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Latest 5 recorded business expenses
                    </p>
                </div>
                <Button variant="ghost" size="sm" as-child>
                    <Link
                        href="/expenses"
                        class="flex items-center gap-1.5 text-xs text-primary"
                    >
                        View all <ArrowRight class="size-3.5" />
                    </Link>
                </Button>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="recentExpenses.length > 0" class="divide-y">
                    <Link
                        v-for="exp in recentExpenses"
                        :key="exp.id"
                        :href="`/expenses/${exp.id}`"
                        class="flex items-center justify-between gap-3 px-5 py-3.5 transition-colors hover:bg-muted/40"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <p class="truncate text-sm font-medium">
                                    {{ exp.title }}
                                </p>
                                <Badge
                                    variant="secondary"
                                    class="shrink-0 text-[10px]"
                                >
                                    {{ exp.category?.name ?? 'General' }}
                                </Badge>
                            </div>
                            <p
                                class="mt-0.5 truncate text-xs text-muted-foreground"
                            >
                                {{ formatExpenseDate(exp.expense_date) }}
                                <span v-if="exp.payee">
                                    · Payee: {{ exp.payee }}
                                </span>
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-rose-600">
                                -{{ formatPhp(exp.amount) }}
                            </p>
                            <span
                                class="inline-block text-[11px] text-muted-foreground capitalize"
                            >
                                {{
                                    formatExpensePaymentMethod(
                                        exp.payment_method,
                                    )
                                }}
                            </span>
                        </div>
                    </Link>
                </div>
                <div
                    v-else
                    class="flex h-32 items-center justify-center text-sm text-muted-foreground"
                >
                    No expenses recorded yet.
                </div>
            </CardContent>
        </Card>
    </section>
</template>
