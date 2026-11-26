<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowDownRight,
    ArrowUpRight,
    Banknote,
    Plus,
    Receipt,
} from '@lucide/vue';
import GcashIcon from '@/components/icons/GcashIcon.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatPhp } from '@/lib/transactionFormat';
import { create as createExpense } from '@/routes/expenses';
import { create as createTransaction } from '@/routes/transactions';

defineProps<{
    register: {
        opening_cash: number;
        cash_in: number;
        cash_out: number;
        cash_in_box: number;
        gcash_in: number;
        gcash_out: number;
        exact_net_gcash: number;
        total_sales: number;
        total_expenses: number;
    };
    gcash?: {
        balance: number;
        is_low_balance: boolean;
        low_balance_threshold: number;
    };
}>();
</script>

<template>
    <section
        class="flex flex-wrap items-center justify-between gap-4 border-b pb-5"
    >
        <div>
            <Badge variant="secondary" class="mb-1 text-xs">
                Daily Counter Shift
            </Badge>
            <h1
                class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl"
            >
                Counter Register
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Live Cash in Box, exact GCash balances, and today's operations.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <Button as-child class="gap-1.5 shadow-sm">
                <Link :href="createTransaction()">
                    <Plus class="size-4" />
                    New Transaction
                </Link>
            </Button>
            <Button variant="outline" as-child class="gap-1.5">
                <Link :href="createExpense()">
                    <Plus class="size-4" />
                    New Expense
                </Link>
            </Button>
        </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <article
            class="rounded-xl border border-emerald-500/30 bg-gradient-to-br from-emerald-500/10 via-card to-card p-5 shadow-sm"
        >
            <div class="flex items-start justify-between">
                <div>
                    <p
                        class="text-xs font-semibold tracking-wider text-emerald-700 uppercase dark:text-emerald-400"
                    >
                        Cash in Box (Drawer)
                    </p>
                    <p
                        class="mt-2 text-3xl font-bold tracking-tight text-foreground"
                    >
                        {{ formatPhp(register.cash_in_box) }}
                    </p>
                </div>
                <span
                    class="rounded-lg bg-emerald-500/15 p-2.5 text-emerald-600 dark:text-emerald-400"
                >
                    <Banknote class="size-6" />
                </span>
            </div>
            <div
                class="mt-4 flex flex-col gap-1 border-t border-emerald-500/15 pt-3 text-xs text-muted-foreground"
            >
                <div class="flex items-center justify-between">
                    <span class="flex items-center gap-1">
                        <ArrowUpRight class="size-3.5 text-emerald-500" />
                        Cash In (Sales):
                    </span>
                    <span class="font-medium text-foreground">
                        +{{ formatPhp(register.cash_in) }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="flex items-center gap-1">
                        <ArrowDownRight class="size-3.5 text-rose-500" />
                        Cash Out (Expenses):
                    </span>
                    <span class="font-medium text-foreground">
                        -{{ formatPhp(register.cash_out) }}
                    </span>
                </div>
                <div
                    v-if="register.opening_cash > 0"
                    class="flex items-center justify-between text-[11px] text-muted-foreground/80"
                >
                    <span>Opening Float:</span>
                    <span>{{ formatPhp(register.opening_cash) }}</span>
                </div>
            </div>
        </article>

        <article
            :class="[
                'rounded-xl border p-5 shadow-sm transition-all',
                gcash?.is_low_balance
                    ? 'border-amber-500/40 bg-gradient-to-br from-amber-500/10 via-card to-card'
                    : 'border-blue-500/30 bg-gradient-to-br from-blue-500/10 via-card to-card',
            ]"
        >
            <div class="flex items-start justify-between">
                <div>
                    <div class="flex items-center gap-1.5">
                        <p
                            class="text-xs font-semibold tracking-wider text-blue-700 uppercase dark:text-blue-400"
                        >
                            GCash Wallet Balance
                        </p>
                        <span
                            v-if="gcash?.is_low_balance"
                            class="inline-flex items-center rounded bg-amber-500/20 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-300"
                        >
                            Low
                        </span>
                    </div>
                    <p
                        class="mt-2 text-3xl font-bold tracking-tight text-foreground"
                    >
                        {{ formatPhp(gcash?.balance ?? 0) }}
                    </p>
                </div>
                <span
                    :class="[
                        'rounded-lg p-2.5',
                        gcash?.is_low_balance
                            ? 'bg-amber-500/20 text-amber-600'
                            : 'bg-blue-500/15 text-blue-600 dark:text-blue-400',
                    ]"
                >
                    <GcashIcon class="size-6" />
                </span>
            </div>
            <div
                class="mt-4 flex flex-col gap-1 border-t border-blue-500/15 pt-3 text-xs text-muted-foreground"
            >
                <div class="flex items-center justify-between">
                    <span class="flex items-center gap-1">
                        <ArrowUpRight class="size-3.5 text-blue-500" />
                        Today In:
                    </span>
                    <span class="font-medium text-foreground">
                        +{{ formatPhp(register.gcash_in) }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="flex items-center gap-1">
                        <ArrowDownRight class="size-3.5 text-rose-500" />
                        Today Out:
                    </span>
                    <span class="font-medium text-foreground">
                        -{{ formatPhp(register.gcash_out) }}
                    </span>
                </div>
            </div>
        </article>

        <article
            class="rounded-xl border bg-card p-5 shadow-sm sm:col-span-2 lg:col-span-1"
        >
            <div class="flex items-start justify-between">
                <div>
                    <p
                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Today's Activity
                    </p>
                    <p
                        class="mt-2 text-3xl font-bold tracking-tight text-foreground"
                    >
                        {{ formatPhp(register.total_sales) }}
                    </p>
                </div>
                <span class="rounded-lg bg-primary/10 p-2.5 text-primary">
                    <Receipt class="size-6" />
                </span>
            </div>
            <div
                class="mt-4 flex items-center justify-between border-t pt-3 text-xs text-muted-foreground"
            >
                <span>Total Counter Outflow:</span>
                <span class="font-semibold text-rose-600">
                    {{ formatPhp(register.total_expenses) }}
                </span>
            </div>
        </article>
    </section>
</template>
