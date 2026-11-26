<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Loader2, Minus, Pencil, Pin, Plus } from '@lucide/vue';
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
import type { GcashLedgerEntry } from '@/types/gcashLedger';

const isOpen = ref(false);
const entryToEdit = ref<GcashLedgerEntry | null>(null);
const editAdjustmentDirection = ref<'credit' | 'debit'>('credit');
const isAnchorOverride = ref(false);

const editForm = useForm({
    amount: '',
    entry_date: '',
    notes: '',
    running_balance: '',
    is_balance_anchor: false,
});

const open = (entry: GcashLedgerEntry) => {
    entryToEdit.value = entry;
    editForm.clearErrors();

    isAnchorOverride.value = Boolean(entry.is_balance_anchor);
    editForm.running_balance =
        entry.running_balance !== null && entry.running_balance !== undefined
            ? String(Math.abs(Number(entry.running_balance)))
            : '';
    editForm.is_balance_anchor = Boolean(entry.is_balance_anchor);

    const numAmount = Number(entry.amount);
    editAdjustmentDirection.value = numAmount < 0 ? 'debit' : 'credit';
    editForm.amount = String(Math.abs(numAmount));
    editForm.entry_date = String(entry.entry_date).split('T')[0];
    editForm.notes = entry.notes ?? '';

    isOpen.value = true;
};

const resetAnchorToAuto = () => {
    isAnchorOverride.value = false;
    editForm.is_balance_anchor = false;
};

const submitEdit = () => {
    if (!entryToEdit.value) {
        return;
    }

    const rawAmount = parseFloat(editForm.amount as string);

    if (isNaN(rawAmount) || rawAmount <= 0) {
        return;
    }

    const signedAmount =
        entryToEdit.value.type === 'adjustment' &&
        editAdjustmentDirection.value === 'debit'
            ? -rawAmount
            : rawAmount;

    editForm
        .transform((data) => ({
            ...data,
            amount: signedAmount.toFixed(2),
            is_balance_anchor: isAnchorOverride.value,
            running_balance:
                isAnchorOverride.value && data.running_balance
                    ? parseFloat(data.running_balance as string).toFixed(2)
                    : null,
        }))
        .put(`/gcash-ledger/${entryToEdit.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                isOpen.value = false;
                entryToEdit.value = null;
            },
        });
};

defineExpose({ open });
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="sm:max-w-[480px]">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Pencil class="size-5 text-primary" />
                    <span>Edit GCash Ledger Entry</span>
                </DialogTitle>
                <DialogDescription>
                    Update amount, entry date, notes, or running balance anchor.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submitEdit" class="space-y-4 py-2">
                <div
                    v-if="entryToEdit?.type === 'adjustment'"
                    class="space-y-1.5"
                >
                    <Label>Direction</Label>
                    <div class="grid grid-cols-2 gap-3">
                        <Button
                            type="button"
                            :variant="
                                editAdjustmentDirection === 'credit'
                                    ? 'default'
                                    : 'outline'
                            "
                            class="w-full gap-2"
                            @click="editAdjustmentDirection = 'credit'"
                        >
                            <Plus class="size-4" />
                            <span>Inflow / Credit (+)</span>
                        </Button>
                        <Button
                            type="button"
                            :variant="
                                editAdjustmentDirection === 'debit'
                                    ? 'destructive'
                                    : 'outline'
                            "
                            class="w-full gap-2"
                            @click="editAdjustmentDirection = 'debit'"
                        >
                            <Minus class="size-4" />
                            <span>Outflow / Debit (-)</span>
                        </Button>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <Label for="edit_amount">
                        Amount (PHP) <span class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="edit_amount"
                        v-model="editForm.amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        required
                        placeholder="0.00"
                    />
                    <span
                        v-if="editForm.errors.amount"
                        class="text-xs text-destructive"
                    >
                        {{ editForm.errors.amount }}
                    </span>
                </div>

                <div class="space-y-1.5">
                    <Label for="edit_date">
                        Date <span class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="edit_date"
                        v-model="editForm.entry_date"
                        type="date"
                        required
                    />
                    <span
                        v-if="editForm.errors.entry_date"
                        class="text-xs text-destructive"
                    >
                        {{ editForm.errors.entry_date }}
                    </span>
                </div>

                <div class="space-y-1.5">
                    <Label for="edit_notes">Notes / Reason</Label>
                    <Input
                        id="edit_notes"
                        v-model="editForm.notes"
                        placeholder="Audit reason or description"
                    />
                    <span
                        v-if="editForm.errors.notes"
                        class="text-xs text-destructive"
                    >
                        {{ editForm.errors.notes }}
                    </span>
                </div>

                <div
                    class="space-y-2.5 rounded-lg border border-amber-500/30 bg-amber-500/5 p-3.5 text-xs"
                >
                    <div class="flex items-center justify-between">
                        <label class="flex cursor-pointer items-center gap-2">
                            <input
                                type="checkbox"
                                v-model="isAnchorOverride"
                                class="size-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                            />
                            <span
                                class="flex items-center gap-1.5 font-semibold text-foreground"
                            >
                                <Pin
                                    class="size-3 text-amber-600 dark:text-amber-400"
                                />
                                Anchor Running Balance
                            </span>
                        </label>

                        <Button
                            v-if="entryToEdit?.is_balance_anchor"
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="h-6 px-2 text-[11px] text-muted-foreground hover:text-destructive"
                            @click="resetAnchorToAuto"
                        >
                            Reset to Auto
                        </Button>
                    </div>

                    <div v-if="isAnchorOverride" class="space-y-1.5 pt-1">
                        <Label for="edit_running_balance" class="text-xs">
                            Desired Running Balance on this row (PHP)
                        </Label>
                        <Input
                            id="edit_running_balance"
                            v-model="editForm.running_balance"
                            type="number"
                            step="0.01"
                            placeholder="0.00"
                            class="bg-background"
                        />
                        <p class="text-[11px] text-muted-foreground">
                            Locks this row's running balance to this exact
                            amount.
                        </p>
                    </div>
                </div>

                <DialogFooter class="pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isOpen = false"
                        :disabled="editForm.processing"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        :disabled="editForm.processing"
                        class="gap-2"
                    >
                        <Loader2
                            v-if="editForm.processing"
                            class="size-4 animate-spin"
                        />
                        <span>Save Changes</span>
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
