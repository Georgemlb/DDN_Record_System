<script setup lang="ts">
import { Calculator, Info } from '@lucide/vue';
import GcashIcon from '@/components/icons/GcashIcon.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatPhp } from '@/lib/transactionFormat';
import type { OnlineAssistanceType } from '@/types/transaction';

const amount = defineModel<string | number>('amount', { required: true });
const thirdPartyPayment = defineModel<string | number>('thirdPartyPayment', {
    required: true,
});

defineProps<{
    errors: Record<string, string>;
    onlineAssistanceTypeId: string;
    onlineAssistanceTypes: OnlineAssistanceType[];
    selectedAssistanceHasDisbursement: boolean;
    numAmount: number;
    numThirdParty: number;
    computedOnlineNetMargin: number;
    computedOnlineDdnShare: number;
    computedOnlineEmployeeShare: number;
    handleOnlineAssistanceTypeChange: (value: unknown) => void;
}>();
</script>

<template>
    <div class="grid gap-2 md:col-span-2">
        <Label for="assistance_type_select">Online Assistance Type</Label>
        <Select
            :model-value="onlineAssistanceTypeId"
            @update:model-value="handleOnlineAssistanceTypeChange"
        >
            <SelectTrigger id="assistance_type_select" class="w-full">
                <SelectValue placeholder="Select assistance type" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem
                    v-for="typeOption in onlineAssistanceTypes"
                    :key="typeOption.id"
                    :value="String(typeOption.id)"
                >
                    {{ typeOption.name }} ({{ formatPhp(typeOption.price) }})
                    <template
                        v-if="
                            typeOption.has_disbursement &&
                            typeOption.disbursement_amount
                        "
                    >
                        — Fee: {{ formatPhp(typeOption.disbursement_amount) }}
                    </template>
                    <template v-else-if="typeOption.has_disbursement === false">
                        — (No Disb.)
                    </template>
                </SelectItem>
            </SelectContent>
        </Select>
        <InputError :message="errors.online_assistance_type_id" />
    </div>

    <div class="grid gap-2">
        <Label for="amount">Customer Payment (PHP)</Label>
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
        />
        <p class="text-xs text-muted-foreground">
            Total fee collected from the customer.
        </p>
        <InputError :message="errors.amount" />
    </div>

    <div v-if="selectedAssistanceHasDisbursement" class="grid gap-2">
        <Label
            for="online_third_party_payment"
            class="flex items-center gap-1.5 text-blue-600 dark:text-blue-400"
        >
            <GcashIcon class="size-4" />
            <span>Government Fee (Paid via GCash)</span>
        </Label>
        <Input
            id="online_third_party_payment"
            name="third_party_payment"
            type="number"
            min="0"
            step="0.01"
            inputmode="decimal"
            v-model="thirdPartyPayment"
            required
            placeholder="0.00"
        />
        <p class="text-[11px] text-muted-foreground">
            Amount to be automatically deducted from your live GCash Ledger upon
            submission.
        </p>
        <InputError :message="errors.third_party_payment" />
    </div>
    <div
        v-else
        class="rounded-lg border border-muted bg-muted/30 p-3 text-xs text-muted-foreground md:col-span-2"
    >
        <p class="font-medium text-foreground">
            ℹ️ Pure Online Assistance Service
        </p>
        <p class="mt-0.5">
            No government fee disbursement required. 100% of customer payment is
            retained for agency and employee split.
        </p>
    </div>

    <div class="rounded-lg border bg-muted/40 p-4 md:col-span-2">
        <div
            class="flex items-center justify-between gap-2 text-sm font-semibold text-primary"
        >
            <div class="flex items-center gap-2">
                <Calculator class="size-4" />
                <span>Net Service Margin & 60/40 Revenue Split</span>
            </div>
            <span class="text-xs font-normal text-muted-foreground">
                Office 60% / Employee 40%
            </span>
        </div>

        <div
            v-if="selectedAssistanceHasDisbursement && numThirdParty > 0"
            class="mt-3 grid grid-cols-3 gap-2 text-center text-xs"
        >
            <div class="rounded border bg-background p-2">
                <span class="text-muted-foreground">Customer Paid</span>
                <p class="font-bold text-foreground">
                    {{ formatPhp(numAmount) }}
                </p>
            </div>
            <div class="rounded border bg-background p-2">
                <span class="text-muted-foreground">Gov / GCash Fee</span>
                <p class="font-semibold text-blue-600 dark:text-blue-400">
                    -{{ formatPhp(numThirdParty) }}
                </p>
            </div>
            <div class="rounded border bg-background p-2">
                <span class="text-muted-foreground">Net Margin</span>
                <p class="font-semibold text-emerald-600 dark:text-emerald-400">
                    {{ formatPhp(computedOnlineNetMargin) }}
                </p>
            </div>
        </div>
        <div v-else class="mt-3 grid grid-cols-2 gap-2 text-center text-xs">
            <div class="rounded border bg-background p-2">
                <span class="text-muted-foreground">Customer Paid</span>
                <p class="font-bold text-foreground">
                    {{ formatPhp(numAmount) }}
                </p>
            </div>
            <div class="rounded border bg-background p-2">
                <span class="text-muted-foreground">Net Service Margin</span>
                <p class="font-semibold text-emerald-600 dark:text-emerald-400">
                    {{ formatPhp(computedOnlineNetMargin) }}
                </p>
            </div>
        </div>

        <div class="mt-3 grid gap-3 border-t pt-3 sm:grid-cols-2">
            <div class="rounded-md border bg-card p-3">
                <p class="text-xs text-muted-foreground">
                    DDN Office Share (60%)
                </p>
                <p class="mt-1 text-lg font-bold text-primary">
                    {{ formatPhp(computedOnlineDdnShare) }}
                </p>
            </div>
            <div class="rounded-md border bg-card p-3">
                <p class="text-xs text-muted-foreground">Employee Cut (40%)</p>
                <p
                    class="mt-1 text-lg font-bold text-emerald-600 dark:text-emerald-400"
                >
                    {{ formatPhp(computedOnlineEmployeeShare) }}
                </p>
            </div>
        </div>
        <p
            class="mt-2 flex items-center gap-1.5 text-[11px] text-muted-foreground"
        >
            <Info class="size-3.5 shrink-0" />
            <span>{{
                selectedAssistanceHasDisbursement && numThirdParty > 0
                    ? 'Shares are computed from net margin (Customer Payment minus Government Fee).'
                    : '100% of customer payment is split 60% Office / 40% Employee.'
            }}</span>
        </p>
    </div>
</template>
