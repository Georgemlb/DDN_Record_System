<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { CheckCircle2, HandCoins } from '@lucide/vue';
import { watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
    formatExpenseDate,
    formatExpensePaymentMethod,
    getTodayDateString,
} from '@/lib/expenseFormat';
import { formatPhp } from '@/lib/transactionFormat';
import type { Expense, ExpensePaymentMethod } from '@/types/expense';

const props = defineProps<{
    open: boolean;
    expense: Expense | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'success'): void;
}>();

const form = useForm({
    reimbursement_method: 'cash' as ExpensePaymentMethod,
    reimbursed_at: getTodayDateString(),
    reimbursement_notes: '',
});

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen && props.expense) {
            form.reimbursement_method = 'cash';
            form.reimbursed_at = getTodayDateString();
            form.reimbursement_notes = props.expense.payee
                ? `Repaid by ${props.expense.payee}`
                : 'Personal repayment';
            form.clearErrors();
        }
    },
);

const submitReimbursement = () => {
    if (!props.expense) {
        return;
    }

    form.post(`/expenses/${props.expense.id}/reimburse`, {
        preserveScroll: true,
        onSuccess: () => {
            emit('update:open', false);
            emit('success');
        },
    });
};
</script>

<template>
    <Dialog :open="open" @update:open="(val) => emit('update:open', val)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <HandCoins
                        class="size-5 text-amber-600 dark:text-amber-400"
                    />
                    <span>Record Reimbursement</span>
                </DialogTitle>
                <DialogDescription>
                    Record personal or family errand money returned back to the
                    agency.
                </DialogDescription>
            </DialogHeader>

            <div v-if="expense" class="grid gap-4 py-2">
                <!-- Expense Context Card -->
                <div class="rounded-lg border bg-muted/40 p-3 text-xs">
                    <div class="flex justify-between font-medium">
                        <span class="text-muted-foreground">Expense Item:</span>
                        <span class="font-semibold text-foreground">{{
                            expense.title
                        }}</span>
                    </div>
                    <div v-if="expense.payee" class="mt-1 flex justify-between">
                        <span class="text-muted-foreground"
                            >Payee / Person:</span
                        >
                        <span>{{ expense.payee }}</span>
                    </div>
                    <div class="mt-1 flex justify-between">
                        <span class="text-muted-foreground"
                            >Original Disbursed Date:</span
                        >
                        <span>{{
                            formatExpenseDate(expense.expense_date)
                        }}</span>
                    </div>
                    <div class="mt-1 flex justify-between">
                        <span class="text-muted-foreground"
                            >Disbursed Via:</span
                        >
                        <span class="capitalize">{{
                            formatExpensePaymentMethod(expense.payment_method)
                        }}</span>
                    </div>
                    <div
                        class="mt-2 flex items-center justify-between border-t pt-2"
                    >
                        <span class="font-medium text-muted-foreground"
                            >Reimbursement Due:</span
                        >
                        <span
                            class="text-base font-bold text-amber-600 dark:text-amber-400"
                        >
                            {{ formatPhp(expense.amount) }}
                        </span>
                    </div>
                </div>

                <form class="grid gap-4" @submit.prevent="submitReimbursement">
                    <!-- Method -->
                    <div class="grid gap-2">
                        <Label for="reimbursement_method">
                            Repaid Via <span class="text-destructive">*</span>
                        </Label>
                        <Select
                            :model-value="form.reimbursement_method"
                            @update:model-value="
                                (val) =>
                                    (form.reimbursement_method =
                                        val as ExpensePaymentMethod)
                            "
                        >
                            <SelectTrigger id="reimbursement_method">
                                <SelectValue
                                    placeholder="Select repayment channel"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="cash">
                                    Cash (Deposited into cash drawer)
                                </SelectItem>
                                <SelectItem value="gcash">
                                    GCash (Transferred to agency GCash)
                                </SelectItem>
                                <SelectItem value="bank_transfer">
                                    Bank Transfer
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-[11px] text-muted-foreground">
                            {{
                                form.reimbursement_method === 'cash'
                                    ? 'Cash will be added to today’s cash drawer in daily settlement.'
                                    : form.reimbursement_method === 'gcash'
                                      ? 'GCash will be added to the GCash balance in daily settlement.'
                                      : 'Bank balance will be updated.'
                            }}
                        </p>
                        <InputError
                            :message="form.errors.reimbursement_method"
                        />
                    </div>

                    <!-- Date -->
                    <div class="grid gap-2">
                        <Label for="reimbursed_at">
                            Date Repaid <span class="text-destructive">*</span>
                        </Label>
                        <Input
                            id="reimbursed_at"
                            v-model="form.reimbursed_at"
                            type="date"
                            :max="getTodayDateString()"
                            required
                        />
                        <InputError :message="form.errors.reimbursed_at" />
                    </div>

                    <!-- Notes -->
                    <div class="grid gap-2">
                        <Label for="reimbursement_notes"
                            >Notes (Optional)</Label
                        >
                        <Input
                            id="reimbursement_notes"
                            v-model="form.reimbursement_notes"
                            type="text"
                            placeholder="e.g., Repaid in full by Gel"
                        />
                        <InputError
                            :message="form.errors.reimbursement_notes"
                        />
                    </div>

                    <DialogFooter class="mt-2 gap-2 sm:gap-0">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="form.processing"
                            @click="emit('update:open', false)"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            class="bg-emerald-600 text-white hover:bg-emerald-700"
                            :disabled="form.processing"
                        >
                            <CheckCircle2 class="mr-1.5 size-4" />
                            {{
                                form.processing
                                    ? 'Saving...'
                                    : 'Confirm Reimbursement'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </div>
        </DialogContent>
    </Dialog>
</template>
