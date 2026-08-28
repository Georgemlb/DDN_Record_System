<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus, ReceiptText } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { formatPhp, formatTransactionDate } from '@/lib/transactionFormat';
import { create, show } from '@/routes/transactions';
import type { Transaction } from '@/types/transaction';

defineProps<{
    transactions: Transaction[];
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
</script>

<template>
    <Head title="Transactions" />

    <main class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
        <section class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-primary">Records</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">
                    Transactions
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Record and manage your business transactions.
                </p>
            </div>
            <Button as-child>
                <Link :href="create()"><Plus /> Record transaction</Link>
            </Button>
        </section>

        <section v-if="transactions.length" class="overflow-hidden rounded-lg border bg-card">
            <div class="overflow-x-auto">
                <table class="w-full min-w-185 text-left text-sm">
                    <thead class="border-b bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-5 py-3 font-medium">Date</th>
                            <th class="px-5 py-3 font-medium">Customer / person</th>
                            <th class="px-5 py-3 font-medium">Pax</th>
                            <th class="px-5 py-3 font-medium">Provider</th>
                            <th class="px-5 py-3 font-medium">Details</th>
                            <th class="px-5 py-3 text-right font-medium">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="transaction in transactions" :key="transaction.id" class="hover:bg-muted/35">
                            <td class="whitespace-nowrap px-5 py-4 text-muted-foreground">
                                {{ formatTransactionDate(transaction.transaction_date) }}
                            </td>
                            <td class="px-5 py-4 font-medium">
                                <Link :href="show(transaction)" class="hover:text-primary hover:underline">
                                    {{ transaction.customer_name }}
                                </Link>
                            </td>
                            <td class="px-5 py-4">{{ transaction.pax }}</td>
                            <td class="px-5 py-4 text-muted-foreground">
                                {{ transaction.provider || '—' }}
                            </td>
                            <td class="max-w-70 truncate px-5 py-4 text-muted-foreground">
                                {{ transaction.service_details || '—' }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-right font-medium">
                                {{ formatPhp(transaction.amount) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-else class="flex flex-1 items-center justify-center rounded-lg border border-dashed bg-card p-8 sm:p-14">
            <div class="max-w-sm text-center">
                <span class="inline-flex rounded-lg bg-secondary p-3 text-secondary-foreground">
                    <ReceiptText class="size-6" />
                </span>
                <h2 class="mt-4 font-semibold">No transactions recorded</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Start by recording your first business transaction.
                </p>
                <Button class="mt-5" as-child>
                    <Link :href="create()"><Plus /> Record transaction</Link>
                </Button>
            </div>
        </section>
    </main>
</template>
