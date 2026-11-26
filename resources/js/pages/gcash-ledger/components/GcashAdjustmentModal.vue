<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Loader2, Minus, Pin, Plus, SlidersHorizontal } from '@lucide/vue';
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
const isAdjAnchor = ref(false);

const adjustmentForm = useForm({
    amount: '',
    entry_date: todayDate,
    notes: '',
    from_drawer: false,
    is_balance_anchor: false,
    running_balance: '',
});

const open = () => {
    adjustmentForm.reset();
    adjustmentForm.entry_date = todayDate;
    adjustmentForm.from_drawer = false;
    adjustmentDirection.value = 'credit';
    isAdjAnchor.value = false;
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
            from_drawer:
                adjustmentDirection.value === 'credit'
                    ? data.from_drawer
                    : false,
            is_balance_anchor: isAdjAnchor.value,
            running_balance:
                isAdjAnchor.value && data.running_balance
                    ? parseFloat(data.running_balance as string).toFixed(2)
                    : null,
        }))
        .post('/gcash-ledger/adjustment', {
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
                    <span>GCash Adjustment / Anchor</span>
                </DialogTitle>
                <DialogDescription>
                    Record a manual adjustment or anchor your GCash running
                    balance to match your app count.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submitAdjustment" class="space-y-4 py-2">
                <div class="space-y-1.5">
                    <Label>Direction</Label>
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
                            <span>Inflow / Credit (+)</span>
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
                            <span>Outflow / Debit (-)</span>
                        </Button>
                    </div>
                </div>

                <div
                    v-if="adjustmentDirection === 'credit'"
                    class="rounded-lg border border-blue-500/20 bg-blue-500/5 p-3"
                >
                    <div class="flex items-start gap-2.5">
                        <input
                            id="adj_from_drawer"
                            v-model="adjustmentForm.from_drawer"
                            type="checkbox"
                            class="mt-0.5 size-4 rounded border-input text-primary shadow-xs focus:ring-primary"
                        />
                        <div class="space-y-0.5 text-xs">
                            <Label
                                for="adj_from_drawer"
                                class="cursor-pointer font-semibold text-foreground"
                            >
                                Funded from Cash Drawer (Top-Up)
                            </Label>
                            <p class="text-muted-foreground">
                                Records a physical cash withdrawal from the
                                drawer (-Cash Drawer) to reload into office
                                GCash (+GCash Wallet).
                            </p>
                        </div>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <Label for="adj_amount">
                        Amount (PHP) <span class="text-destructive">*</span>
                    </Label>
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
                    <Label for="adj_date">
                        Date <span class="text-destructive">*</span>
                    </Label>
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
                    <Label for="adj_notes">
                        Reason / Description
                        <span class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="adj_notes"
                        v-model="adjustmentForm.notes"
                        required
                        placeholder="e.g. End of day GCash calibration, or manual top-up"
                    />
                    <span
                        v-if="adjustmentForm.errors.notes"
                        class="text-xs text-destructive"
                    >
                        {{ adjustmentForm.errors.notes }}
                    </span>
                </div>

                <div
                    class="space-y-2.5 rounded-lg border border-amber-500/30 bg-amber-500/5 p-3.5 text-xs"
                >
                    <label class="flex cursor-pointer items-center gap-2">
                        <input
                            type="checkbox"
                            v-model="isAdjAnchor"
                            class="size-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                        />
                        <span
                            class="flex items-center gap-1.5 font-semibold text-foreground"
                        >
                            <Pin
                                class="size-3 text-amber-600 dark:text-amber-400"
                            />
                            Anchor Running Balance (Set Exact Balance)
                        </span>
                    </label>

                    <div v-if="isAdjAnchor" class="space-y-1.5 pt-1">
                        <Label for="adj_anchor_balance" class="text-xs">
                            Exact Desired Running Balance on this row (PHP)
                        </Label>
                        <Input
                            id="adj_anchor_balance"
                            v-model="adjustmentForm.running_balance"
                            type="number"
                            step="0.01"
                            placeholder="0.00"
                            class="bg-background"
                        />
                        <p class="text-[11px] text-muted-foreground">
                            Locks this row's running balance to this exact
                            amount. Subsequent transactions will continue from
                            here.
                        </p>
                    </div>
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
                        <span>Save Entry</span>
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
