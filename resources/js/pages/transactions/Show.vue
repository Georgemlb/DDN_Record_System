<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import TransactionController from '@/actions/App/Http/Controllers/TransactionController';
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
import { formatPhp, formatTransactionDate } from '@/lib/transactionFormat';
import { edit, index } from '@/routes/transactions';
import type { Transaction } from '@/types/transaction';

defineProps<{
    transaction: Transaction;
}>();
</script>

<template>
    <Head :title="`Transaction: ${transaction.customer_name}`" />

    <main class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
        <section class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-primary">
                    Transaction details
                </p>
                <h1
                    class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl"
                >
                    {{ transaction.customer_name }}
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Recorded on
                    {{ formatTransactionDate(transaction.transaction_date) }}
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
                                    >Delete this transaction?</DialogTitle
                                >
                                <DialogDescription
                                    >This permanently removes the record for
                                    {{ transaction.customer_name }}. This action
                                    cannot be undone.</DialogDescription
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
                                    >Delete transaction</Button
                                >
                            </DialogFooter>
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </section>

        <section class="grid max-w-4xl gap-4 md:grid-cols-2">
            <article class="rounded-lg border bg-card p-5 sm:p-6">
                <h2 class="font-semibold">Customer / person</h2>
                <dl class="mt-5 space-y-4">
                    <div>
                        <dt class="text-sm text-muted-foreground">Name</dt>
                        <dd class="mt-1 font-medium">
                            {{ transaction.customer_name }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted-foreground">
                            Passengers
                        </dt>
                        <dd class="mt-1 font-medium">
                            {{ transaction.pax }}
                            {{ transaction.pax === 1 ? 'pax' : 'pax' }}
                        </dd>
                    </div>
                </dl>
            </article>
            <article class="rounded-lg border bg-card p-5 sm:p-6">
                <h2 class="font-semibold">Transaction information</h2>
                <dl class="mt-5 space-y-4">
                    <div>
                        <dt class="text-sm text-muted-foreground">
                            Transaction date
                        </dt>
                        <dd class="mt-1 font-medium">
                            {{
                                formatTransactionDate(
                                    transaction.transaction_date,
                                )
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted-foreground">
                            Airline or provider
                        </dt>
                        <dd class="mt-1 font-medium">
                            {{ transaction.provider || '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted-foreground">
                            Additional service or details
                        </dt>
                        <dd class="mt-1 font-medium whitespace-pre-wrap">
                            {{ transaction.service_details || '—' }}
                        </dd>
                    </div>
                </dl>
            </article>
            <article
                class="rounded-lg border border-primary/20 bg-primary p-5 text-primary-foreground sm:p-6"
            >
                <h2 class="font-semibold text-primary-foreground/75">
                    Total amount
                </h2>
                <p class="mt-3 text-3xl font-semibold tracking-tight">
                    {{ formatPhp(transaction.amount) }}
                </p>
            </article>
            <article class="rounded-lg border bg-card p-5 sm:p-6">
                <h2 class="font-semibold">Notes</h2>
                <p
                    class="mt-3 text-sm whitespace-pre-wrap text-muted-foreground"
                >
                    {{ transaction.notes || '—' }}
                </p>
            </article>
        </section>

        <Button variant="outline" class="w-fit" as-child
            ><Link :href="index()">Back to transactions</Link></Button
        >
    </main>
</template>
