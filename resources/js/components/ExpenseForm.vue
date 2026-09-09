<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
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

const form = useForm({
    title: initialData?.title ?? '',
    expense_category_id: initialData?.expense_category_id
        ? Number(initialData.expense_category_id)
        : ('' as number | ''),
    amount: initialData?.amount ?? ('' as string | number),
    expense_date: props.expense?.expense_date ?? getTodayDateString(),
    payment_method: (initialData?.payment_method ??
        'cash') as ExpensePaymentMethod,
    payee: initialData?.payee ?? '',
    reference_number: initialData?.reference_number ?? '',
    recurring_frequency: (initialData?.recurring_frequency ??
        'none') as RecurringFrequency,
    notes: initialData?.notes ?? '',
    is_personal: Boolean(initialData?.is_personal ?? false),
    reimbursement_status: initialData?.reimbursement_status ?? 'none',
    reimbursed_at: initialData?.reimbursed_at ?? getTodayDateString(),
    reimbursement_method: (initialData?.reimbursement_method ??
        'cash') as ExpensePaymentMethod,
    reimbursement_notes: initialData?.reimbursement_notes ?? '',
});

watch(
    () => form.is_personal,
    (isPersonal) => {
        if (
            isPersonal &&
            (form.reimbursement_status === 'none' || !form.reimbursement_status)
        ) {
            form.reimbursement_status = 'pending';
        }

        if (!isPersonal) {
            form.reimbursement_status = 'none';
        }
    },
);

const isCategoryModalOpen = ref(false);

const onCategoryCreated = (newCategory: ExpenseCategory) => {
    if (!localCategories.value.some((c) => c.id === newCategory.id)) {
        localCategories.value.push(newCategory);
    }

    form.expense_category_id = newCategory.id;
};

watch(
    () => props.categories,
    (updated) => {
        localCategories.value = [...updated];
    },
);

const submit = () => {
    const isPersonal = Boolean(form.is_personal);
    const isReimbursed =
        isPersonal && form.reimbursement_status === 'reimbursed';

    const payload = {
        ...form.data(),
        expense_category_id: form.expense_category_id || '',
        payee: form.payee || null,
        reference_number: form.reference_number || null,
        notes: form.notes || null,
        is_personal: isPersonal,
        reimbursement_status: isPersonal ? form.reimbursement_status : 'none',
        reimbursed_at: isReimbursed ? form.reimbursed_at : null,
        reimbursement_method: isReimbursed ? form.reimbursement_method : null,
        reimbursement_notes: isReimbursed ? form.reimbursement_notes : null,
    };

    if (props.method === 'put') {
        form.transform(() => payload).put(props.action);
    } else {
        form.transform(() => payload).post(props.action);
    }
};
</script>

<template>
    <div>
        <form class="space-y-6" @submit.prevent="submit">
            <div class="grid gap-6 md:grid-cols-2">
                <!-- 1. Title / Description -->
                <div class="grid gap-2 md:col-span-2">
                    <Label for="title">
                        Expense Title / Description
                        <span class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="title"
                        v-model="form.title"
                        type="text"
                        required
                        placeholder="e.g., March Internet Bill, Bond Paper Reams, Office Lease"
                    />
                    <InputError :message="form.errors.title" />
                </div>

                <!-- 2. Category Selection -->
                <div class="grid gap-2 md:col-span-1">
                    <div class="flex items-center justify-between">
                        <Label for="expense_category_id">
                            Category <span class="text-destructive">*</span>
                        </Label>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                            @click="isCategoryModalOpen = true"
                        >
                            <Plus class="size-3.5" />
                            <span>New category</span>
                        </button>
                    </div>
                    <Select
                        :model-value="
                            form.expense_category_id
                                ? String(form.expense_category_id)
                                : ''
                        "
                        @update:model-value="
                            (val) => (form.expense_category_id = Number(val))
                        "
                    >
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
                    <InputError :message="form.errors.expense_category_id" />
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
                            v-model="form.amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            inputmode="decimal"
                            required
                            placeholder="0.00"
                            class="pl-7"
                        />
                    </div>
                    <InputError :message="form.errors.amount" />
                </div>

                <!-- 4. Expense Date -->
                <div class="grid gap-2 md:col-span-1">
                    <Label for="expense_date">
                        Expense Date <span class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="expense_date"
                        v-model="form.expense_date"
                        type="date"
                        :max="getTodayDateString()"
                        required
                    />
                    <p class="text-xs text-muted-foreground">
                        Date the disbursement occurred (today or past date).
                    </p>
                    <InputError :message="form.errors.expense_date" />
                </div>

                <!-- 5. Payment Method -->
                <div class="grid gap-2 md:col-span-1">
                    <Label for="payment_method">
                        Payment Method <span class="text-destructive">*</span>
                    </Label>
                    <Select
                        :model-value="form.payment_method"
                        @update:model-value="
                            (val) =>
                                (form.payment_method =
                                    val as ExpensePaymentMethod)
                        "
                    >
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
                    <InputError :message="form.errors.payment_method" />
                </div>

                <!-- 6. Payee / Vendor -->
                <div class="grid gap-2 md:col-span-1">
                    <Label for="payee">Payee / Vendor (optional)</Label>
                    <Input
                        id="payee"
                        v-model="form.payee"
                        type="text"
                        placeholder="e.g., Meralco, Shopee, National Bookstore"
                    />
                    <InputError :message="form.errors.payee" />
                </div>

                <!-- 7. Reference Number -->
                <div class="grid gap-2 md:col-span-1">
                    <Label for="reference_number"
                        >Receipt / Reference No. (optional)</Label
                    >
                    <Input
                        id="reference_number"
                        v-model="form.reference_number"
                        type="text"
                        placeholder="e.g., OR-2026-0012, Bank Ref #892"
                    />
                    <InputError :message="form.errors.reference_number" />
                </div>

                <!-- 8. Recurring Frequency -->
                <div class="grid gap-2 md:col-span-1">
                    <Label for="recurring_frequency">Recurring Frequency</Label>
                    <Select
                        :model-value="form.recurring_frequency"
                        @update:model-value="
                            (val) =>
                                (form.recurring_frequency =
                                    val as RecurringFrequency)
                        "
                    >
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
                    <InputError :message="form.errors.recurring_frequency" />
                </div>

                <!-- Personal / Errand Settings -->
                <div
                    class="rounded-lg border border-amber-200 bg-amber-50/50 p-4 md:col-span-2 dark:border-amber-900/50 dark:bg-amber-950/20"
                >
                    <div class="flex items-start space-x-3">
                        <input
                            id="is_personal"
                            v-model="form.is_personal"
                            type="checkbox"
                            class="mt-1 size-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500 dark:border-gray-600 dark:bg-gray-700"
                        />
                        <div class="grid gap-1">
                            <Label
                                for="is_personal"
                                class="cursor-pointer font-medium text-foreground"
                            >
                                Personal / Family Errand (To be reimbursed)
                            </Label>
                            <p class="text-xs text-muted-foreground">
                                Check this if this payment was for personal use
                                (e.g., Shopee order, personal online errand)
                                using agency funds that will be reimbursed. It
                                will be tracked separately so company
                                operational expenses are not inflated.
                            </p>
                        </div>
                    </div>

                    <!-- If marked personal, options for reimbursement status -->
                    <div
                        v-if="form.is_personal"
                        class="mt-4 grid gap-4 border-t border-amber-200/60 pt-3 sm:grid-cols-2 dark:border-amber-900/40"
                    >
                        <div class="grid gap-2">
                            <Label for="reimbursement_status"
                                >Reimbursement Status</Label
                            >
                            <Select
                                :model-value="form.reimbursement_status"
                                @update:model-value="
                                    (val) =>
                                        (form.reimbursement_status = val as any)
                                "
                            >
                                <SelectTrigger
                                    id="reimbursement_status"
                                    class="w-full bg-background"
                                >
                                    <SelectValue placeholder="Select status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="pending">
                                        Pending Reimbursement (Unpaid)
                                    </SelectItem>
                                    <SelectItem value="reimbursed">
                                        Already Reimbursed (Repaid)
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div
                            v-if="form.reimbursement_status === 'reimbursed'"
                            class="grid gap-2"
                        >
                            <Label for="reimbursed_at"
                                >Reimbursement Date</Label
                            >
                            <Input
                                id="reimbursed_at"
                                v-model="form.reimbursed_at"
                                type="date"
                                :max="getTodayDateString()"
                                class="bg-background"
                            />
                        </div>

                        <div
                            v-if="form.reimbursement_status === 'reimbursed'"
                            class="grid gap-2"
                        >
                            <Label for="reimbursement_method"
                                >Reimbursement Method</Label
                            >
                            <Select
                                :model-value="form.reimbursement_method"
                                @update:model-value="
                                    (val) =>
                                        (form.reimbursement_method = val as any)
                                "
                            >
                                <SelectTrigger
                                    id="reimbursement_method"
                                    class="w-full bg-background"
                                >
                                    <SelectValue
                                        placeholder="How was it repaid?"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="cash">
                                        Cash (Put into cash drawer)
                                    </SelectItem>
                                    <SelectItem value="gcash">
                                        GCash (Sent to agency GCash)
                                    </SelectItem>
                                    <SelectItem value="bank_transfer">
                                        Bank Transfer
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div
                            v-if="form.reimbursement_status === 'reimbursed'"
                            class="grid gap-2"
                        >
                            <Label for="reimbursement_notes"
                                >Reimbursement Notes</Label
                            >
                            <Input
                                id="reimbursement_notes"
                                v-model="form.reimbursement_notes"
                                type="text"
                                placeholder="e.g., Repaid in cash by Gel"
                                class="bg-background"
                            />
                        </div>
                    </div>
                </div>

                <!-- 9. Notes -->
                <div class="grid gap-2 md:col-span-2">
                    <Label for="notes">Notes</Label>
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        rows="3"
                        class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                        placeholder="Additional details or context about this disbursement"
                    ></textarea>
                    <InputError :message="form.errors.notes" />
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex flex-wrap items-center gap-3 border-t pt-6">
                <Button type="submit" :disabled="form.processing">
                    {{ submitLabel }}
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="expensesIndex()">Cancel</Link>
                </Button>
            </div>
        </form>

        <!-- Quick Category Modal -->
        <ExpenseCategoryModal
            v-model:is-open="isCategoryModalOpen"
            @category-created="onCategoryCreated"
        />
    </div>
</template>
