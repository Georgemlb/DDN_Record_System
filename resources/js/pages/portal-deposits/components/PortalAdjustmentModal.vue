<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Loader2, Minus, Plus, SlidersHorizontal } from '@lucide/vue';
import { ref } from 'vue';
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

const isOpen = ref(false);
const todayDate = new Date().toISOString().split('T')[0];
const adjustmentDirection = ref<'credit' | 'debit'>('credit');

const adjustmentForm = useForm({
    amount: '',
    entry_date: todayDate,
    notes: '',
});

const open = () => {
    adjustmentForm.reset();
    adjustmentForm.entry_date = todayDate;
    adjustmentDirection.value = 'credit';
    isOpen.value = true;
};

const submitAdjustment = () => {
    const rawAmount = parseFloat(adjustmentForm.amount as string);

    if (isNaN(rawAmount) || rawAmount <= 0) {
        return;
    }

    const signedAmount =
        adjustmentDirection.value === 'debit' ? -rawAmount : rawAmount;

    adjustmentForm
        .transform((data) => ({
            ...data,
            amount: signedAmount.toFixed(2),
        }))
        .post('/portal-deposits/adjustment', {
            preserveScroll: true,
            onSuccess: () => {
                isOpen.value = false;
                adjustmentForm.reset();
            },
        });
};

defineExpose({ open });
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="sm:max-w-[460px]">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <SlidersHorizontal class="size-5 text-primary" />
                    <span>Manual Balance Adjustment</span>
                </DialogTitle>
                <DialogDescription>
                    Reconcile discrepancies between DDN records and the
                    third-party portal balance.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submitAdjustment" class="space-y-4 py-2">
                <div class="space-y-1.5">
                    <Label>Adjustment Type</Label>
                    <div class="grid grid-cols-2 gap-3">
                        <Button
                            type="button"
                            :variant="
                                adjustmentDirection === 'credit'
                                    ? 'default'
                                    : 'outline'
                            "
                            class="w-full gap-2"
                            @click="adjustmentDirection = 'credit'"
                        >
                            <Plus class="size-4" />
                            <span>Credit (+ Add)</span>
                        </Button>
                        <Button
                            type="button"
                            :variant="
                                adjustmentDirection === 'debit'
                                    ? 'destructive'
                                    : 'outline'
                            "
                            class="w-full gap-2"
                            @click="adjustmentDirection = 'debit'"
                        >
                            <Minus class="size-4" />
                            <span>Debit (- Deduct)</span>
                        </Button>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <Label for="adj_amount"
                        >Adjustment Amount (PHP)
                        <span class="text-destructive">*</span></Label
                    >
                    <Input
                        id="adj_amount"
                        v-model="adjustmentForm.amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        required
                        placeholder="0.00"
                    />
                    <span
                        v-if="adjustmentForm.errors.amount"
                        class="text-xs text-destructive"
                    >
                        {{ adjustmentForm.errors.amount }}
                    </span>
                </div>

                <div class="space-y-1.5">
                    <Label for="adj_date"
                        >Date <span class="text-destructive">*</span></Label
                    >
                    <Input
                        id="adj_date"
                        v-model="adjustmentForm.entry_date"
                        type="date"
                        required
                    />
                    <span
                        v-if="adjustmentForm.errors.entry_date"
                        class="text-xs text-destructive"
                    >
                        {{ adjustmentForm.errors.entry_date }}
                    </span>
                </div>

                <div class="space-y-1.5">
                    <Label for="adj_notes"
                        >Audit Reason / Explanation
                        <span class="text-destructive">*</span></Label
                    >
                    <Input
                        id="adj_notes"
                        v-model="adjustmentForm.notes"
                        required
                        placeholder="e.g. Portal monthly bonus credit, or fee discrepancy"
                    />
                    <span
                        v-if="adjustmentForm.errors.notes"
                        class="text-xs text-destructive"
                    >
                        {{ adjustmentForm.errors.notes }}
                    </span>
                </div>

                <DialogFooter class="pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isOpen = false"
                        :disabled="adjustmentForm.processing"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        :disabled="adjustmentForm.processing"
                        class="gap-2"
                    >
                        <Loader2
                            v-if="adjustmentForm.processing"
                            class="size-4 animate-spin"
                        />
                        <span>Apply Adjustment</span>
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
