<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Loader2, Minus, Pencil, Pin, Plus, Wallet } from '@lucide/vue';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    FlightPortalDeposit,
    FlightPortalPaymentSource,
} from '@/types/flightPortal';

const isOpen = ref(false);
const depositToEdit = ref<FlightPortalDeposit | null>(null);
const editAdjustmentDirection = ref<'credit' | 'debit'>('credit');
const isAnchorOverride = ref(false);

const editForm = useForm({
    amount: '',
    entry_date: '',
    payment_source: 'bank_transfer' as FlightPortalPaymentSource,
    reference_number: '',
    notes: '',
    running_balance: '',
    is_balance_anchor: false,
});

const open = (deposit: FlightPortalDeposit) => {
    depositToEdit.value = deposit;
    editForm.clearErrors();

    isAnchorOverride.value = Boolean(deposit.is_balance_anchor);
    editForm.running_balance =
        deposit.running_balance !== null &&
        deposit.running_balance !== undefined
            ? String(Math.abs(Number(deposit.running_balance)))
            : '';
    editForm.is_balance_anchor = Boolean(deposit.is_balance_anchor);

    if (deposit.type === 'top_up') {
        editForm.amount = String(Math.abs(Number(deposit.amount)));
        editForm.entry_date = deposit.entry_date;
        editForm.payment_source = deposit.payment_source ?? 'bank_transfer';
        editForm.reference_number = deposit.reference_number ?? '';
        editForm.notes = deposit.notes ?? '';
    } else if (deposit.type === 'adjustment') {
        const numAmount = Number(deposit.amount);
        editAdjustmentDirection.value = numAmount < 0 ? 'debit' : 'credit';
        editForm.amount = String(Math.abs(numAmount));
        editForm.entry_date = deposit.entry_date;
        editForm.reference_number = deposit.reference_number ?? '';
        editForm.notes = deposit.notes ?? '';
    } else {
        editForm.amount = String(Math.abs(Number(deposit.amount)));
        editForm.entry_date = deposit.entry_date;
        editForm.reference_number = deposit.reference_number ?? '';
        editForm.notes = deposit.notes ?? '';
    }

    isOpen.value = true;
};

const resetAnchorToAuto = () => {
    isAnchorOverride.value = false;
    editForm.is_balance_anchor = false;
};

const submitEdit = () => {
    if (!depositToEdit.value) {
        return;
    }

    if (depositToEdit.value.type === 'adjustment') {
        const rawAmount = parseFloat(editForm.amount as string);

        if (isNaN(rawAmount) || rawAmount <= 0) {
            return;
        }

        const signedAmount =
            editAdjustmentDirection.value === 'debit' ? -rawAmount : rawAmount;

        editForm
            .transform((data) => ({
                ...data,
                amount: signedAmount.toFixed(2),
                is_balance_anchor: isAnchorOverride.value,
                running_balance: isAnchorOverride.value
                    ? parseFloat(
                          (data.running_balance as string) || '0',
                      ).toFixed(2)
                    : null,
            }))
            .put(`/portal-deposits/${depositToEdit.value.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    isOpen.value = false;
                    depositToEdit.value = null;
                },
            });
    } else {
        editForm
            .transform((data) => ({
                ...data,
                amount: parseFloat((editForm.amount as string) || '0').toFixed(
                    2,
                ),
                is_balance_anchor: isAnchorOverride.value,
                running_balance: isAnchorOverride.value
                    ? parseFloat(
                          (data.running_balance as string) || '0',
                      ).toFixed(2)
                    : null,
            }))
            .put(`/portal-deposits/${depositToEdit.value.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    isOpen.value = false;
                    depositToEdit.value = null;
                },
            });
    }
};

defineExpose({ open });
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="sm:max-w-[480px]">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Pencil class="size-5 text-primary" />
                    <span v-if="depositToEdit?.type === 'top_up'"
                        >Edit Flight Portal Top-Up</span
                    >
                    <span v-else-if="depositToEdit?.type === 'adjustment'"
                        >Edit Balance Adjustment</span
                    >
                    <span v-else>Edit Deposit Entry</span>
                </DialogTitle>
                <DialogDescription>
                    <span v-if="depositToEdit?.type === 'top_up'">
                        Update top-up details. Running balances and any linked
                        expense records will automatically synchronize.
                    </span>
                    <span v-else-if="depositToEdit?.type === 'adjustment'">
                        Update manual balance adjustment. Running balances will
                        be recalculated chronologically.
                    </span>
                    <span v-else> Update deposit ledger record. </span>
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submitEdit" class="space-y-4 py-2">
                <div
                    v-if="depositToEdit?.expense"
                    class="flex items-start gap-2.5 rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-3 text-xs text-emerald-900 dark:text-emerald-200"
                >
                    <Wallet
                        class="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                    />
                    <div>
                        <span class="font-semibold"
                            >Linked to Expense #{{
                                depositToEdit.expense.id
                            }}:</span
                        >
                        Updating this top-up will automatically update the
                        agency cash/expense ledger and daily cash settlement.
                    </div>
                </div>

                <template v-if="depositToEdit?.type === 'top_up'">
                    <div class="space-y-1.5">
                        <Label for="edit_topup_amount"
                            >Top-Up Amount (PHP)
                            <span class="text-destructive">*</span></Label
                        >
                        <Input
                            id="edit_topup_amount"
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

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label for="edit_topup_date"
                                >Date
                                <span class="text-destructive">*</span></Label
                            >
                            <Input
                                id="edit_topup_date"
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
                            <Label for="edit_topup_source"
                                >Payment Source
                                <span class="text-destructive">*</span></Label
                            >
                            <Select v-model="editForm.payment_source">
                                <SelectTrigger
                                    id="edit_topup_source"
                                    class="w-full"
                                >
                                    <SelectValue placeholder="Select Source" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="bank_transfer"
                                        >Bank Transfer</SelectItem
                                    >
                                    <SelectItem value="gcash">GCash</SelectItem>
                                    <SelectItem value="cash">Cash</SelectItem>
                                </SelectContent>
                            </Select>
                            <span
                                v-if="editForm.errors.payment_source"
                                class="text-xs text-destructive"
                            >
                                {{ editForm.errors.payment_source }}
                            </span>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="edit_topup_ref"
                            >Reference Number (Optional)</Label
                        >
                        <Input
                            id="edit_topup_ref"
                            v-model="editForm.reference_number"
                            placeholder="e.g. Bank Deposit Slip # or Ref ID"
                        />
                        <span
                            v-if="editForm.errors.reference_number"
                            class="text-xs text-destructive"
                        >
                            {{ editForm.errors.reference_number }}
                        </span>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="edit_topup_notes"
                            >Notes / Purpose (Optional)</Label
                        >
                        <Input
                            id="edit_topup_notes"
                            v-model="editForm.notes"
                            placeholder="e.g. Reload via BDO Online for peak season"
                        />
                        <span
                            v-if="editForm.errors.notes"
                            class="text-xs text-destructive"
                        >
                            {{ editForm.errors.notes }}
                        </span>
                    </div>
                </template>

                <template v-else-if="depositToEdit?.type === 'adjustment'">
                    <div class="space-y-1.5">
                        <Label>Adjustment Type</Label>
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
                                <span>Credit (+ Add)</span>
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
                                <span>Debit (- Deduct)</span>
                            </Button>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="edit_adj_amount"
                            >Adjustment Amount (PHP)
                            <span class="text-destructive">*</span></Label
                        >
                        <Input
                            id="edit_adj_amount"
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
                        <Label for="edit_adj_date"
                            >Date <span class="text-destructive">*</span></Label
                        >
                        <Input
                            id="edit_adj_date"
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
                        <Label for="edit_adj_notes"
                            >Audit Reason / Explanation
                            <span class="text-destructive">*</span></Label
                        >
                        <Input
                            id="edit_adj_notes"
                            v-model="editForm.notes"
                            required
                            placeholder="e.g. Portal monthly bonus credit, or fee discrepancy"
                        />
                        <span
                            v-if="editForm.errors.notes"
                            class="text-xs text-destructive"
                        >
                            {{ editForm.errors.notes }}
                        </span>
                    </div>
                </template>

                <template v-else>
                    <div class="space-y-1.5">
                        <Label for="edit_gen_amount"
                            >Amount (PHP)
                            <span class="text-destructive">*</span></Label
                        >
                        <Input
                            id="edit_gen_amount"
                            v-model="editForm.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            required
                        />
                        <span
                            v-if="editForm.errors.amount"
                            class="text-xs text-destructive"
                        >
                            {{ editForm.errors.amount }}
                        </span>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="edit_gen_date"
                            >Date <span class="text-destructive">*</span></Label
                        >
                        <Input
                            id="edit_gen_date"
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
                        <Label for="edit_gen_notes">Notes</Label>
                        <Input id="edit_gen_notes" v-model="editForm.notes" />
                        <span
                            v-if="editForm.errors.notes"
                            class="text-xs text-destructive"
                        >
                            {{ editForm.errors.notes }}
                        </span>
                    </div>
                </template>

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
                                Anchor Running Balance (Manual Calibration)
                            </span>
                        </label>

                        <Button
                            v-if="depositToEdit?.is_balance_anchor"
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
                            Setting an anchor sets this row's running balance
                            explicitly. Subsequent rows calculate forward from
                            this exact balance.
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
