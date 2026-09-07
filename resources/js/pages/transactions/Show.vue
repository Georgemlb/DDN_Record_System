<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import TransactionController from '@/actions/App/Http/Controllers/TransactionController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    formatPaymentMethod,
    formatPhp,
    formatTransactionDate,
    formatTransactionType,
} from '@/lib/transactionFormat';
import { edit, index } from '@/routes/transactions';
import type { Transaction } from '@/types/transaction';

defineProps<{
    transaction: Transaction;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Transactions', href: index() },
            {
                title: 'Transaction details',
                href: '#',
            },
        ],
    },
});
</script>

<template>
    <Head :title="`Transaction: ${transaction.customer_name}`" />

    <main class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
        <section class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span
                        v-if="transaction.reference_number"
                        class="rounded border bg-muted px-2 py-0.5 font-mono text-xs font-semibold text-foreground"
                    >
                        {{ transaction.reference_number }}
                    </span>
                    <p class="text-sm font-medium text-primary">
                        Transaction details
                    </p>
                    <Badge variant="secondary">
                        {{ formatTransactionType(transaction.type) }}
                    </Badge>
                </div>
                <h1
                    class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl"
                >
                    {{ transaction.customer_name }}
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Recorded on
                    {{ formatTransactionDate(transaction.transaction_date) }}
                    <span v-if="transaction.user">
                        · by {{ transaction.user.name }}
                    </span>
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" as-child
                    ><Link :href="edit(transaction)"
                        ><Pencil /> Edit</Link
                    ></Button
                >
                <Dialog>
                    <DialogTrigger as-child
                        ><Button variant="destructive"
                            ><Trash2 /> Delete</Button
                        ></DialogTrigger
                    >
                    <DialogContent>
                        <Form
                            :action="
                                TransactionController.destroy(transaction).url
                            "
                            method="delete"
                            v-slot="{ processing }"
                        >
                            <DialogHeader>
                                <DialogTitle
                                    >Move this transaction to
                                    trash?</DialogTitle
                                >
                                <DialogDescription
                                    >This moves the record for
                                    {{ transaction.customer_name }} to the
                                    trash. You can view or restore it at any
                                    time from the Trash tab.</DialogDescription
                                >
                            </DialogHeader>
                            <DialogFooter class="mt-6 gap-2">
                                <DialogClose as-child
                                    ><Button variant="outline"
                                        >Cancel</Button
                                    ></DialogClose
                                >
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    :disabled="processing"
                                    >Move to trash</Button
                                >
                            </DialogFooter>
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </section>

        <section class="grid max-w-4xl gap-4 md:grid-cols-2">
            <!-- 1. Customer & Common Details -->
            <article class="rounded-lg border bg-card p-5 sm:p-6">
                <h2 class="font-semibold">Customer & General Info</h2>
                <dl class="mt-5 space-y-4 text-sm">
                    <div>
                        <dt class="text-muted-foreground">Customer Name</dt>
                        <dd class="mt-1 text-base font-medium">
                            {{ transaction.customer_name }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Payment Method</dt>
                        <dd class="mt-1 font-medium">
                            <Badge variant="outline">
                                {{
                                    formatPaymentMethod(
                                        transaction.payment_method,
                                    )
                                }}
                            </Badge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Transaction Date</dt>
                        <dd class="mt-1 font-medium">
                            {{
                                formatTransactionDate(
                                    transaction.transaction_date,
                                )
                            }}
                        </dd>
                    </div>
                </dl>
            </article>

            <!-- 2. Service Specific Details -->
            <article class="rounded-lg border bg-card p-5 sm:p-6">
                <h2 class="font-semibold">Service Details</h2>
                <dl class="mt-5 space-y-4 text-sm">
                    <div>
                        <dt class="text-muted-foreground">Service Type</dt>
                        <dd class="mt-1 font-medium">
                            {{ formatTransactionType(transaction.type) }}
                        </dd>
                    </div>

                    <!-- Airline Details -->
                    <template v-if="transaction.type === 'airline'">
                        <div>
                            <dt class="text-muted-foreground">
                                Passengers (Pax)
                            </dt>
                            <dd class="mt-1 font-medium">
                                {{ transaction.pax ?? 1 }} pax
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">
                                Airline / Provider
                            </dt>
                            <dd class="mt-1 font-medium">
                                {{ transaction.provider || '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">
                                Additional Baggage
                            </dt>
                            <dd class="mt-1 font-medium">
                                <span v-if="transaction.baggage_option">
                                    <template
                                        v-if="
                                            transaction.baggage_option.airline
                                        "
                                    >
                                        {{ transaction.baggage_option.airline }}
                                        —
                                    </template>
                                    {{ transaction.baggage_option.label }} ({{
                                        formatPhp(
                                            transaction.baggage_option.price,
                                        )
                                    }})
                                </span>
                                <span v-else>None</span>
                            </dd>
                        </div>
                    </template>

                    <!-- Document Translation Details -->
                    <template v-if="transaction.type === 'translation'">
                        <div>
                            <dt class="text-muted-foreground">Service</dt>
                            <dd class="mt-1 font-medium">
                                Document Translation
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">
                                Translator Payment
                            </dt>
                            <dd class="mt-1 font-medium text-destructive">
                                {{ formatPhp(transaction.third_party_payment) }}
                            </dd>
                        </div>
                    </template>

                    <!-- PSA Assistance Details -->
                    <template v-if="transaction.type === 'psa'">
                        <div>
                            <dt class="text-muted-foreground">
                                PSA Document Type
                            </dt>
                            <dd class="mt-1 font-medium">
                                {{ transaction.psa_document_type || '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">
                                PSA Officer Payment
                            </dt>
                            <dd class="mt-1 font-medium text-destructive">
                                {{ formatPhp(transaction.third_party_payment) }}
                            </dd>
                        </div>
                    </template>

                    <!-- Online Assistance Details -->
                    <template v-if="transaction.type === 'online_assistance'">
                        <div>
                            <dt class="text-muted-foreground">
                                Assistance Type
                            </dt>
                            <dd class="mt-1 font-medium">
                                {{
                                    transaction.online_assistance_type?.name ??
                                    '—'
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">
                                Employee Share (40%)
                            </dt>
                            <dd class="mt-1 font-medium">
                                {{ formatPhp(transaction.employee_share) }}
                            </dd>
                        </div>
                    </template>

                    <!-- Printing Details -->
                    <template v-if="transaction.type === 'printing'">
                        <div>
                            <dt class="text-muted-foreground">Service</dt>
                            <dd class="mt-1 font-medium">Printing Service</dd>
                        </div>
                    </template>
                </dl>
            </article>

            <!-- 3. Financial Summary Card -->
            <article
                class="rounded-lg border border-primary/20 bg-primary p-5 text-primary-foreground sm:p-6"
            >
                <h2 class="font-semibold text-primary-foreground/75">
                    Financial Summary
                </h2>
                <div class="mt-4 space-y-3">
                    <div>
                        <p class="text-xs text-primary-foreground/70">
                            Customer Total Paid
                        </p>
                        <p class="mt-1 text-2xl font-bold tracking-tight">
                            {{ formatPhp(transaction.amount) }}
                        </p>
                    </div>

                    <div
                        v-if="
                            transaction.third_party_payment &&
                            Number(transaction.third_party_payment) > 0
                        "
                        class="border-t border-primary-foreground/20 pt-2"
                    >
                        <p class="text-xs text-primary-foreground/70">
                            {{
                                transaction.type === 'psa'
                                    ? 'PSA Officer Payment'
                                    : 'Translator Payment'
                            }}
                        </p>
                        <p
                            class="mt-1 text-lg font-semibold text-primary-foreground/90"
                        >
                            - {{ formatPhp(transaction.third_party_payment) }}
                        </p>
                    </div>

                    <div class="border-t border-primary-foreground/20 pt-2">
                        <p class="text-xs text-primary-foreground/70">
                            DDN Net Amount
                        </p>
                        <p class="mt-1 text-3xl font-extrabold tracking-tight">
                            {{
                                formatPhp(
                                    transaction.ddn_amount ??
                                        transaction.amount,
                                )
                            }}
                        </p>
                    </div>
                </div>
            </article>

            <!-- 4. Notes Card -->
            <article class="rounded-lg border bg-card p-5 sm:p-6">
                <h2 class="font-semibold">Notes</h2>
                <p
                    class="mt-3 text-sm whitespace-pre-wrap text-muted-foreground"
                >
                    {{ transaction.notes || 'No additional notes provided.' }}
                </p>
            </article>
        </section>

        <Button variant="outline" class="w-fit" as-child
            ><Link :href="index()">Back to transactions</Link></Button
        >
    </main>
</template>
