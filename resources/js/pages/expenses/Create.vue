<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ExpenseController from '@/actions/App/Http/Controllers/ExpenseController';
import ExpenseForm from '@/components/ExpenseForm.vue';
import { index as expensesIndex } from '@/routes/expenses';
import type {
    Expense,
    ExpenseCategory,
    ExpensePaymentMethod,
    RecurringFrequency,
} from '@/types/expense';

defineProps<{
    categories: ExpenseCategory[];
    sourceExpense?: Expense | null;
    paymentMethods?: ExpensePaymentMethod[];
    recurringFrequencies?: RecurringFrequency[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Expenses', href: expensesIndex() },
            { title: 'Record expense', href: '/expenses/create' },
        ],
    },
});
</script>

<template>
    <Head :title="sourceExpense ? 'Duplicate expense' : 'Record expense'" />

    <main class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
        <section>
            <p class="text-sm font-medium text-primary">Expenses</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">
                {{ sourceExpense ? 'Duplicate expense' : 'Record expense' }}
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                {{
                    sourceExpense
                        ? 'Create a new expense entry based on an existing record.'
                        : 'Record a business operational expenditure or disbursement.'
                }}
            </p>
        </section>

        <section class="max-w-3xl rounded-lg border bg-card p-5 sm:p-6">
            <ExpenseForm
                :key="sourceExpense?.id ?? 'new'"
                :action="ExpenseController.store().url"
                method="post"
                submit-label="Save expense"
                :source-expense="sourceExpense ?? undefined"
                :categories="categories"
                :payment-methods="paymentMethods"
                :recurring-frequencies="recurringFrequencies"
            />
        </section>
    </main>
</template>
