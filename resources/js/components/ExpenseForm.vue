<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref, watch } from 'vue';
import ExpenseCategoryModal from '@/components/ExpenseCategoryModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    formatExpensePaymentMethod,
    formatRecurringFrequency,
    getTodayDateString,
} from '@/lib/expenseFormat';
import { index as expensesIndex } from '@/routes/expenses';
import type {
    Expense,
    ExpenseCategory,
    ExpensePaymentMethod,
    RecurringFrequency,
} from '@/types/expense';

const props = withDefaults(
    defineProps<{
        action: string;
        method: 'post' | 'put';
        submitLabel: string;
        expense?: Expense;
        sourceExpense?: Expense;
        categories: ExpenseCategory[];
        paymentMethods?: ExpensePaymentMethod[];
        recurringFrequencies?: RecurringFrequency[];
    }>(),
    {
        paymentMethods: () => ['cash', 'gcash', 'bank_transfer'],
        recurringFrequencies: () => ['none', 'weekly', 'monthly', 'annually'],
    },
);

const localCategories = ref<ExpenseCategory[]>([...props.categories]);

// If sourceExpense is provided (duplication), use its data but set date to today.
const initialData = props.expense ?? props.sourceExpense;

const title = ref<string>(initialData?.title ?? '');
const expenseCategoryId = ref<string>(
    initialData?.expense_category_id
        ? String(initialData.expense_category_id)
        : '',
);
const amount = ref<string | number>(initialData?.amount ?? '');
const expenseDate = ref<string>(
    props.expense?.expense_date ?? getTodayDateString(),
);
const paymentMethod = ref<ExpensePaymentMethod>(
    initialData?.payment_method ?? 'cash',
);
const payee = ref<string>(initialData?.payee ?? '');
const referenceNumber = ref<string>(initialData?.reference_number ?? '');
const recurringFrequency = ref<RecurringFrequency>(
    initialData?.recurring_frequency ?? 'none',
);
const notes = ref<string>(initialData?.notes ?? '');

const isCategoryModalOpen = ref(false);

const onCategoryCreated = (newCategory: ExpenseCategory) => {
    // Add to list if not already present
    if (!localCategories.value.some((c) => c.id === newCategory.id)) {
        localCategories.value.push(newCategory);
    }

    expenseCategoryId.value = String(newCategory.id);
};

// Update localCategories if prop changes
watch(
    () => props.categories,
    (updated) => {
        localCategories.value = [...updated];
    },
);
</script>

<template>
    <div>
        <Form
            :action="action"
            :method="method"
            :data="{
                title,
                expense_category_id: expenseCategoryId
                    ? Number(expenseCategoryId)
                    : '',
                amount,
                expense_date: expenseDate,
                payment_method: paymentMethod,
                payee: payee || null,
                reference_number: referenceNumber || null,
                recurring_frequency: recurringFrequency,
                notes: notes || null,
            }"
            v-slot="{ errors, processing }"
            class="space-y-6"
        >
            <div class="grid gap-6 md:grid-cols-2">
                <!-- 1. Title / Description -->
                <div class="grid gap-2 md:col-span-2">
                    <Label for="title">
                        Expense Title / Description
                        <span class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="title"
                        name="title"
                        type="text"
                        v-model="title"
                        required
                        placeholder="e.g., March Internet Bill, Bond Paper Reams, Office Lease"
                    />
                    <InputError :message="errors.title" />
                </div>

                <!-- 2. Category Selection -->
                <div class="grid gap-2 md:col-span-1">
                    <div class="flex items-center justify-between">
                        <Label for="expense_category_id">
                            Category <span class="text-destructive">*</span>
                        </Label>
                        <button
                            type="button"
                            @click="isCategoryModalOpen = true"
                            class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                        >
                            <Plus class="size-3.5" />
                            <span>New category</span>
                        </button>
                    </div>
                    <Select v-model="expenseCategoryId">
                        <SelectTrigger id="expense_category_id" class="w-full">
                            <SelectValue
                                placeholder="Select an expense category"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="cat in localCategories"
                                :key="cat.id"
                                :value="String(cat.id)"
                            >
                                {{ cat.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.expense_category_id" />
                </div>

                <!-- 3. Amount -->
                <div class="grid gap-2 md:col-span-1">
                    <Label for="amount">
                        Amount (PHP) <span class="text-destructive">*</span>
                    </Label>
                    <div class="relative">
                        <span
                            class="absolute top-1/2 left-3 -translate-y-1/2 text-sm font-semibold text-muted-foreground"
                        >
                            ₱
                        </span>
                        <Input
                            id="amount"
                            name="amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            inputmode="decimal"
                            v-model="amount"
                            required
                            placeholder="0.00"
                            class="pl-7"
                        />
                    </div>
                    <InputError :message="errors.amount" />
                </div>

                <!-- 4. Expense Date -->
                <div class="grid gap-2 md:col-span-1">
                    <Label for="expense_date">
                        Expense Date <span class="text-destructive">*</span>
                    </Label>
                    <div class="relative">
                        <Input
                            id="expense_date"
                            name="expense_date"
                            type="date"
                            v-model="expenseDate"
                            :max="getTodayDateString()"
                            required
                        />
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Date the disbursement occurred (today or past date).
                    </p>
                    <InputError :message="errors.expense_date" />
                </div>

                <!-- 5. Payment Method -->
                <div class="grid gap-2 md:col-span-1">
                    <Label for="payment_method">
                        Payment Method <span class="text-destructive">*</span>
                    </Label>
                    <Select v-model="paymentMethod">
                        <SelectTrigger id="payment_method" class="w-full">
                            <SelectValue placeholder="Select payment method" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="method in paymentMethods"
                                :key="method"
                                :value="method"
                            >
                                {{ formatExpensePaymentMethod(method) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.payment_method" />
                </div>

                <!-- 6. Payee / Vendor -->
                <div class="grid gap-2 md:col-span-1">
                    <Label for="payee">Payee / Vendor (optional)</Label>
                    <Input
                        id="payee"
                        name="payee"
                        type="text"
                        v-model="payee"
                        placeholder="e.g., Meralco, Shopee, National Bookstore"
                    />
                    <InputError :message="errors.payee" />
                </div>

                <!-- 7. Reference Number -->
                <div class="grid gap-2 md:col-span-1">
                    <Label for="reference_number"
                        >Receipt / Reference No. (optional)</Label
                    >
                    <Input
                        id="reference_number"
                        name="reference_number"
                        type="text"
                        v-model="referenceNumber"
                        placeholder="e.g., OR-2026-0012, Bank Ref #892"
                    />
                    <InputError :message="errors.reference_number" />
                </div>

                <!-- 8. Recurring Frequency -->
                <div class="grid gap-2 md:col-span-1">
                    <Label for="recurring_frequency">Recurring Frequency</Label>
                    <Select v-model="recurringFrequency">
                        <SelectTrigger id="recurring_frequency" class="w-full">
                            <SelectValue placeholder="Select frequency" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="freq in recurringFrequencies"
                                :key="freq"
                                :value="freq"
                            >
                                {{ formatRecurringFrequency(freq) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-xs text-muted-foreground">
                        Identifies routine expenses for reporting and quick
                        copying.
                    </p>
                    <InputError :message="errors.recurring_frequency" />
                </div>

                <!-- 9. Notes -->
                <div class="grid gap-2 md:col-span-2">
                    <Label for="notes">Notes</Label>
                    <textarea
                        id="notes"
                        name="notes"
                        rows="3"
                        v-model="notes"
                        class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                        placeholder="Additional details or context about this disbursement"
                    ></textarea>
                    <InputError :message="errors.notes" />
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex flex-wrap items-center gap-3 border-t pt-6">
                <Button type="submit" :disabled="processing">
                    {{ submitLabel }}
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="expensesIndex()">Cancel</Link>
                </Button>
            </div>
        </Form>

        <!-- Quick Category Modal -->
        <ExpenseCategoryModal
            v-model:is-open="isCategoryModalOpen"
            @category-created="onCategoryCreated"
        />
    </div>
</template>
