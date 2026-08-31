<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus, ReceiptText } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    formatPaymentMethod,
    formatPhp,
    formatTransactionDate,
    formatTransactionType,
} from '@/lib/transactionFormat';
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

        <section
            v-if="transactions.length"
            class="overflow-hidden rounded-lg border bg-card shadow-xs"
        >
            <div class="overflow-x-auto">
                <table class="w-full min-w-185 text-left text-sm">
                    <thead
                        class="border-b bg-muted/40 text-xs tracking-wide text-muted-foreground uppercase"
                    >
                        <tr>
                            <th class="px-5 py-3 font-medium">Date</th>
                            <th class="px-5 py-3 font-medium">
                                Customer / Person
                            </th>
                            <th class="px-5 py-3 font-medium">Service Type</th>
                            <th class="px-5 py-3 font-medium">Details</th>
                            <th class="px-5 py-3 font-medium">Payment</th>
                            <th class="px-5 py-3 text-right font-medium">
                                Customer Total
                            </th>
                            <th class="px-5 py-3 text-right font-medium">
                                DDN Amount
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="transaction in transactions"
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
