<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TransactionController from '@/actions/App/Http/Controllers/TransactionController';
import TransactionForm from '@/components/TransactionForm.vue';
import type { Transaction } from '@/types/transaction';

const props = defineProps<{
    transaction: Transaction;
}>();

defineOptions({ layout: { breadcrumbs: [] } });
</script>

<template>
    <Head title="Edit transaction" />

    <main class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
        <section>
            <p class="text-sm font-medium text-primary">Transactions</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">
                Edit transaction
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Update the details for {{ props.transaction.customer_name }}.
            </p>
        </section>

        <section class="max-w-3xl rounded-lg border bg-card p-5 sm:p-6">
            <TransactionForm
                :action="TransactionController.update(props.transaction).url"
                method="put"
                submit-label="Save changes"
                :transaction="props.transaction"
            />
        </section>
    </main>
</template>
