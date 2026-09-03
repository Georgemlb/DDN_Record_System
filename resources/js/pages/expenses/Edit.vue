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

const props = defineProps<{
    expense: Expense;
    categories: ExpenseCategory[];
    paymentMethods?: ExpensePaymentMethod[];
    recurringFrequencies?: RecurringFrequency[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Expenses', href: expensesIndex() },
            {
                title: 'Edit expense',
                href: '#',
            },
        ],
    },
});
</script>

<template>
    <Head :title="`Edit: ${props.expense.title}`" />

    <main class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
        <section>
            <p class="text-sm font-medium text-primary">Expenses</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">
                Edit expense
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Update the disbursement details for {{ props.expense.title }}.
            </p>
        </section>

        <section class="max-w-3xl rounded-lg border bg-card p-5 sm:p-6">
            <ExpenseForm
                :action="ExpenseController.update(props.expense).url"
                method="put"
                submit-label="Save changes"
                :expense="props.expense"
                :categories="categories"
                :payment-methods="paymentMethods"
                :recurring-frequencies="recurringFrequencies"
            />
        </section>
    </main>
</template>
