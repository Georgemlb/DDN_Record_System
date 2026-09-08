<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Banknote, CheckCircle } from '@lucide/vue';
import { computed, watch } from 'vue';
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
import { formatPhp, getTodayDateString } from '@/lib/transactionFormat';
import type { PaymentMethod, Transaction } from '@/types/transaction';

const props = defineProps<{
    open: boolean;
    transaction: Transaction | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const remainingBalance = computed(() => {
    if (!props.transaction) {
return 0;
}

    return Number(props.transaction.balance) || 0;
});

const form = useForm({
    amount: '' as string | number,
    payment_method: 'cash' as PaymentMethod,
    cash_amount: '' as string | number,
    gcash_amount: '' as string | number,
    payment_date: getTodayDateString(),
    notes: 'Balance collection on pickup',
});

// Reset form when modal opens with transaction
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen && props.transaction) {
            form.amount = remainingBalance.value;
            form.payment_method = 'cash';
            form.cash_amount = '';
            form.gcash_amount = '';
            form.payment_date = getTodayDateString();
            form.notes = 'Balance collection on pickup';
            form.clearErrors();
        }
    },
);

const handleCashChange = () => {
    const total = Number(form.amount) || 0;
    const cash = Number(form.cash_amount) || 0;
    form.gcash_amount = Math.max(0, Math.round((total - cash) * 100) / 100);
};

const handleGcashChange = () => {
    const total = Number(form.amount) || 0;
    const gcash = Number(form.gcash_amount) || 0;
    form.cash_amount = Math.max(0, Math.round((total - gcash) * 100) / 100);
};

const submitPayment = () => {
    if (!props.transaction) {
return;
}

    form.post(`/transactions/${props.transaction.id}/payments`, {
        preserveScroll: true,
        onSuccess: () => {
            emit('update:open', false);
        },
    });
};
</script>

<template>
    <Dialog :open="open" @update:open="(val) => emit('update:open', val)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Banknote class="size-5 text-emerald-600 dark:text-emerald-400" />
                    <span>Collect Remaining Balance</span>
                </DialogTitle>
                <DialogDescription>
                    Record payment received from the customer upon pickup or settlement.
                </DialogDescription>
            </DialogHeader>

            <div v-if="transaction" class="grid gap-4 py-2">
                <!-- Transaction Summary Card -->
                <div class="rounded-lg border bg-muted/40 p-3 text-xs">
                    <div class="flex justify-between font-medium">
                        <span class="text-muted-foreground">Reference:</span>
                        <span>{{ transaction.reference_number || `#${transaction.id}` }}</span>
                    </div>
                    <div class="mt-1 flex justify-between">
                        <span class="text-muted-foreground">Customer:</span>
                        <span class="font-semibold">{{ transaction.customer_name }}</span>
                    </div>
                    <div class="mt-2 grid grid-cols-3 gap-2 border-t pt-2 text-center">
                        <div>
                            <span class="text-[11px] text-muted-foreground">Total Price</span>
                            <p class="font-semibold">{{ formatPhp(transaction.amount) }}</p>
                        </div>
                        <div>
                            <span class="text-[11px] text-muted-foreground">Already Paid</span>
                            <p class="font-semibold text-emerald-600 dark:text-emerald-400">
                                {{ formatPhp(transaction.amount_paid) }}
                            </p>
                        </div>
                        <div>
                            <span class="text-[11px] text-muted-foreground">Balance Due</span>
                            <p class="font-bold text-rose-600 dark:text-rose-400">
                                {{ formatPhp(transaction.balance) }}
                            </p>
                        </div>
                    </div>
                </div>

                <form @submit.prevent="submitPayment" class="grid gap-3">
                    <!-- Amount to Pay -->
                    <div class="grid gap-1.5">
                        <Label for="collection_amount">Amount Collected (PHP)</Label>
                        <Input
                            id="collection_amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            :max="remainingBalance"
                            v-model="form.amount"
                            required
                        />
                        <InputError :message="form.errors.amount" />
                    </div>

                    <!-- Payment Method -->
                    <div class="grid gap-1.5">
                        <Label for="collection_method">Payment Method</Label>
                        <Select v-model="form.payment_method">
                            <SelectTrigger id="collection_method" class="w-full">
                                <SelectValue placeholder="Select method" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="cash">Cash</SelectItem>
                                <SelectItem value="gcash">GCash</SelectItem>
                                <SelectItem value="split">Split (Cash & GCash)</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.payment_method" />
                    </div>

                    <!-- Split Details if chosen -->
                    <template v-if="form.payment_method === 'split'">
                        <div class="grid grid-cols-2 gap-2">
                            <div class="grid gap-1">
                                <Label for="col_cash" class="text-xs">Cash (PHP)</Label>
                                <Input
                                    id="col_cash"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    v-model="form.cash_amount"
                                    @input="handleCashChange"
                                    required
                                />
                            </div>
                            <div class="grid gap-1">
                                <Label for="col_gcash" class="text-xs">GCash (PHP)</Label>
                                <Input
                                    id="col_gcash"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    v-model="form.gcash_amount"
                                    @input="handleGcashChange"
                                    required
                                />
                            </div>
                        </div>
                    </template>

                    <!-- Payment Date -->
                    <div class="grid gap-1.5">
                        <Label for="collection_date">Collection Date</Label>
                        <Input
                            id="collection_date"
                            type="date"
                            v-model="form.payment_date"
                            required
                        />
                        <InputError :message="form.errors.payment_date" />
                    </div>

                    <!-- Notes -->
                    <div class="grid gap-1.5">
                        <Label for="collection_notes">Notes (Optional)</Label>
                        <Input
                            id="collection_notes"
                            type="text"
                            v-model="form.notes"
                            placeholder="e.g. Final balance on ticket release"
                        />
                    </div>

                    <DialogFooter class="mt-3">
                        <Button
                            type="button"
                            variant="outline"
                            @click="emit('update:open', false)"
                            :disabled="form.processing"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            class="bg-emerald-600 hover:bg-emerald-700 text-white"
                            :disabled="form.processing"
                        >
                            <CheckCircle class="mr-1.5 size-4" />
                            <span>Confirm & Record Payment</span>
                        </Button>
                    </DialogFooter>
                </form>
            </div>
        </DialogContent>
    </Dialog>
</template>
