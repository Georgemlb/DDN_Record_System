<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Loader2, Plus } from '@lucide/vue';
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
import type { FlightPortalPaymentSource } from '@/types/flightPortal';

const isOpen = ref(false);
const todayDate = new Date().toISOString().split('T')[0];

const topUpForm = useForm({
    amount: '',
    entry_date: todayDate,
    payment_source: 'bank_transfer' as FlightPortalPaymentSource,
    reference_number: '',
    notes: '',
    record_as_expense: true,
});

const open = () => {
    topUpForm.reset();
    topUpForm.entry_date = todayDate;
    topUpForm.payment_source = 'bank_transfer';
    topUpForm.record_as_expense = true;
    isOpen.value = true;
};

const submitTopUp = () => {
    topUpForm.post('/portal-deposits/top-up', {
        preserveScroll: true,
        onSuccess: () => {
            isOpen.value = false;
            topUpForm.reset();
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
                    <Plus class="size-5 text-emerald-600" />
                    <span>Add Flight Portal Top-Up</span>
                </DialogTitle>
                <DialogDescription>
                    Deposit funds to your third-party flight booking portal to
                    increase your available ticketing balance.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submitTopUp" class="space-y-4 py-2">
                <div class="space-y-1.5">
                    <Label for="topup_amount"
                        >Top-Up Amount (PHP)
                        <span class="text-destructive">*</span></Label
                    >
                    <Input
                        id="topup_amount"
                        v-model="topUpForm.amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        required
                        placeholder="0.00"
                    />
                    <span
                        v-if="topUpForm.errors.amount"
                        class="text-xs text-destructive"
                    >
                        {{ topUpForm.errors.amount }}
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label for="topup_date"
                            >Date <span class="text-destructive">*</span></Label
                        >
                        <Input
                            id="topup_date"
                            v-model="topUpForm.entry_date"
                            type="date"
                            required
                        />
                        <span
                            v-if="topUpForm.errors.entry_date"
                            class="text-xs text-destructive"
                        >
                            {{ topUpForm.errors.entry_date }}
                        </span>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="topup_source"
                            >Payment Source
                            <span class="text-destructive">*</span></Label
                        >
                        <Select v-model="topUpForm.payment_source">
                            <SelectTrigger id="topup_source" class="w-full">
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
                            v-if="topUpForm.errors.payment_source"
                            class="text-xs text-destructive"
                        >
                            {{ topUpForm.errors.payment_source }}
                        </span>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <Label for="topup_ref">Reference Number (Optional)</Label>
                    <Input
                        id="topup_ref"
                        v-model="topUpForm.reference_number"
                        placeholder="e.g. Bank Deposit Slip # or Ref ID"
                    />
                    <span
                        v-if="topUpForm.errors.reference_number"
                        class="text-xs text-destructive"
                    >
                        {{ topUpForm.errors.reference_number }}
                    </span>
                </div>

                <div class="space-y-1.5">
                    <Label for="topup_notes">Notes / Purpose (Optional)</Label>
                    <Input
                        id="topup_notes"
                        v-model="topUpForm.notes"
                        placeholder="e.g. Reload via BDO Online for peak season"
                    />
                    <span
                        v-if="topUpForm.errors.notes"
                        class="text-xs text-destructive"
                    >
                        {{ topUpForm.errors.notes }}
                    </span>
                </div>

                <div class="rounded-lg border bg-muted/30 p-3">
                    <label class="flex cursor-pointer items-start gap-2.5">
                        <input
                            type="checkbox"
                            v-model="topUpForm.record_as_expense"
                            class="mt-0.5 size-4 rounded border-gray-300 text-primary focus:ring-primary"
                        />
                        <div class="space-y-0.5">
                            <span class="text-sm leading-none font-medium"
                                >Record as Drawer Cash-Out / Fund Transfer</span
                            >
                            <p class="text-xs text-muted-foreground">
                                Records cash-out under "Flight Portal Reload" in
                                the Daily Cash Settlement. Excluded from
                                operating expenses and net profit.
                            </p>
                        </div>
                    </label>
                </div>

                <DialogFooter class="pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isOpen = false"
                        :disabled="topUpForm.processing"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        :disabled="topUpForm.processing"
                        class="gap-2"
                    >
                        <Loader2
                            v-if="topUpForm.processing"
                            class="size-4 animate-spin"
                        />
                        <span>Save Top-Up</span>
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
