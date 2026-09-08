<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    Banknote,
    Clock,
    Pencil,
    Phone,
    Trash2,
} from '@lucide/vue';
import { ref } from 'vue';
import TransactionController from '@/actions/App/Http/Controllers/TransactionController';
import CollectBalanceModal from '@/components/CollectBalanceModal.vue';
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

const isCollectModalOpen = ref(false);

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

    <CollectBalanceModal
        v-model:open="isCollectModalOpen"
        :transaction="transaction"
    />

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
                    <Badge
                        v-if="
                            transaction.payment_status === 'paid' ||
                            Number(transaction.balance) <= 0
                        "
                        class="border-emerald-200 bg-emerald-50 text-xs font-medium text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300"
                    >
                        Fully Paid
                    </Badge>
                    <Badge
                        v-else-if="transaction.payment_status === 'partial'"
                        class="border-amber-200 bg-amber-50 text-xs font-medium text-amber-700 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-300"
                    >
                        Balance Due: {{ formatPhp(transaction.balance) }}
                    </Badge>
                    <Badge
                        v-else
                        class="border-rose-200 bg-rose-50 text-xs font-medium text-rose-700 dark:border-rose-800 dark:bg-rose-950/50 dark:text-rose-300"
                    >
                        Unpaid: {{ formatPhp(transaction.balance) }}
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
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    v-if="Number(transaction.balance) > 0"
                    class="gap-1.5 bg-emerald-600 text-white hover:bg-emerald-700"
                    @click="isCollectModalOpen = true"
                >
                    <Banknote class="size-4" />
                    <span>Collect Balance</span>
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="edit(transaction)"><Pencil /> Edit</Link>
                </Button>
                <Dialog>
                    <DialogTrigger as-child>
                        <Button variant="destructive"><Trash2 /> Delete</Button>
                    </DialogTrigger>
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
                                <DialogDescription>
                                    This will soft-delete the transaction
                                    record. You can restore it later from the
                                    trash view if needed.
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter class="mt-4">
                                <DialogClose as-child>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        :disabled="processing"
                                    >
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    :disabled="processing"
                                >
                                    Move to trash
                                </Button>
                            </DialogFooter>
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </section>

        <!-- Balance Alert Banner if Remaining Balance > 0 -->
        <section
            v-if="Number(transaction.balance) > 0"
            class="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-amber-200 bg-amber-50/70 p-4 text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-200"
        >
            <div class="flex items-center gap-3">
                <div
                    class="rounded-full bg-amber-100 p-2 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300"
                >
                    <Clock class="size-5" />
                </div>
                <div>
                    <h3 class="font-semibold text-sm">
                        Outstanding Balance:
                        {{ formatPhp(transaction.balance) }}
                    </h3>
                    <p class="text-xs text-amber-700/80 dark:text-amber-400">
                        Customer has paid
                        {{ formatPhp(transaction.amount_paid) }} of
                        {{ formatPhp(transaction.amount) }}. Settle balance upon
                        document or ticket release.
                    </p>
                </div>
            </div>
            <Button
                size="sm"
                class="gap-1.5 bg-amber-600 text-white hover:bg-amber-700"
                @click="isCollectModalOpen = true"
            >
                <Banknote class="size-3.5" />
                <span>Collect {{ formatPhp(transaction.balance) }}</span>
            </Button>
        </section>

        <section class="grid gap-6 md:grid-cols-2">
            <!-- 1. Customer & General Info -->
            <article class="rounded-lg border bg-card p-5 sm:p-6">
                <h2 class="font-semibold">Customer & General Info</h2>
                <dl class="mt-5 space-y-4 text-sm">
                    <div>
                        <dt class="text-muted-foreground">Customer Name</dt>
                        <dd class="mt-1 text-base font-medium">
                            {{ transaction.customer_name }}
                        </dd>
                    </div>
                    <div
                        v-if="
                            transaction.customer?.contact_number ||
                            transaction.customer
                        "
                    >
                        <dt class="text-muted-foreground">Contact Number</dt>
                        <dd
                            class="mt-1 flex items-center gap-1.5 font-medium text-foreground"
                        >
                            <Phone class="size-3.5 text-muted-foreground" />
                            <span>{{
                                transaction.customer?.contact_number ||
                                'Not provided'
                            }}</span>
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
                                    {{ transaction.baggage_option.label }}
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

            <!-- 4. Payment & Collection History Card -->
            <article class="rounded-lg border bg-card p-5 sm:p-6 md:col-span-2">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b pb-4">
                    <div>
                        <h2 class="text-base font-semibold">Payment & Settlement History</h2>
                        <p class="text-xs text-muted-foreground">
                            Record of downpayments and pickup balance collections.
                        </p>
                    </div>
                    <Button
                        v-if="Number(transaction.balance) > 0"
                        size="sm"
                        class="gap-1.5 bg-emerald-600 text-white hover:bg-emerald-700"
                        @click="isCollectModalOpen = true"
                    >
                        <Banknote class="size-3.5" />
                        <span>Collect Balance</span>
                    </Button>
                </div>

                <!-- Balance Summary Bar -->
                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-md border bg-muted/30 p-3">
                        <span class="text-xs text-muted-foreground">Total Service Cost</span>
                        <p class="mt-0.5 text-lg font-bold">{{ formatPhp(transaction.amount) }}</p>
                    </div>
                    <div class="rounded-md border bg-emerald-50/50 p-3 dark:bg-emerald-950/20">
                        <span class="text-xs text-emerald-700 dark:text-emerald-300">Total Amount Collected</span>
                        <p class="mt-0.5 text-lg font-bold text-emerald-600 dark:text-emerald-400">
                            {{ formatPhp(transaction.amount_paid) }}
                        </p>
                    </div>
                    <div
                        class="rounded-md border p-3"
                        :class="Number(transaction.balance) > 0
                            ? 'bg-amber-50/60 border-amber-200 dark:bg-amber-950/20 dark:border-amber-900/50'
                            : 'bg-muted/30'"
                    >
                        <span
                            class="text-xs"
                            :class="Number(transaction.balance) > 0
                                ? 'text-amber-700 dark:text-amber-300'
                                : 'text-muted-foreground'"
                        >
                            Outstanding Balance
                        </span>
                        <p
                            class="mt-0.5 text-lg font-bold"
                            :class="Number(transaction.balance) > 0
                                ? 'text-amber-600 dark:text-amber-400'
                                : 'text-emerald-600 dark:text-emerald-400'"
                        >
                            {{ formatPhp(transaction.balance) }}
                        </p>
                    </div>
                </div>

                <!-- Payments Table -->
                <div v-if="transaction.payments && transaction.payments.length > 0" class="mt-4 overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b bg-muted/40 text-muted-foreground">
                            <tr>
                                <th class="px-3 py-2 font-medium">Date</th>
                                <th class="px-3 py-2 font-medium">Amount</th>
                                <th class="px-3 py-2 font-medium">Method</th>
                                <th class="px-3 py-2 font-medium">Received By</th>
                                <th class="px-3 py-2 font-medium">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="payment in transaction.payments" :key="payment.id">
                                <td class="px-3 py-2.5 whitespace-nowrap text-muted-foreground">
                                    {{ formatTransactionDate(payment.payment_date) }}
                                </td>
                                <td class="px-3 py-2.5 whitespace-nowrap font-semibold text-emerald-600 dark:text-emerald-400">
                                    {{ formatPhp(payment.amount) }}
                                </td>
                                <td class="px-3 py-2.5 whitespace-nowrap">
                                    <Badge variant="outline" class="text-[10px] uppercase">
                                        {{ formatPaymentMethod(payment.payment_method) }}
                                        <template v-if="payment.payment_method === 'split' && payment.cash_amount">
                                            (Cash: {{ formatPhp(payment.cash_amount) }} / GCash: {{ formatPhp(payment.gcash_amount) }})
                                        </template>
                                    </Badge>
                                </td>
                                <td class="px-3 py-2.5 whitespace-nowrap text-muted-foreground">
                                    {{ payment.user?.name || 'System' }}
                                </td>
                                <td class="px-3 py-2.5 text-muted-foreground">
                                    {{ payment.notes || '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="mt-4 rounded-md border border-dashed p-4 text-center text-xs text-muted-foreground">
                    No payment history entries recorded.
                </div>
            </article>

            <!-- 5. Notes Card -->
            <article class="rounded-lg border bg-card p-5 sm:p-6 md:col-span-2">
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
