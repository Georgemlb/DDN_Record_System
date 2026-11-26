<script setup lang="ts">
import {
    ArrowDownLeft,
    ArrowUpRight,
    Calculator,
    Smartphone,
    Wallet,
} from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatPhp } from '@/lib/transactionFormat';

const showBreakdown = ref(false);

const remittanceDirection = defineModel<'GCASH_CASH_OUT' | 'GCASH_CASH_IN'>(
    'remittanceDirection',
    { required: true },
);
const amount = defineModel<string | number>('amount', { required: true });
const remittanceFee = defineModel<string | number>('remittanceFee', {
    required: true,
});

defineProps<{
    errors: Record<string, string>;
    numAmount: number;
}>();
</script>

<template>
    <div class="grid gap-2 md:col-span-2">
        <Label>Remittance Direction</Label>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <button
                type="button"
                @click="remittanceDirection = 'GCASH_CASH_OUT'"
                class="flex flex-col items-start gap-1.5 rounded-lg border p-3.5 text-left transition-all"
                :class="
                    remittanceDirection === 'GCASH_CASH_OUT'
                        ? 'border-blue-600 bg-blue-50/70 text-blue-900 ring-1 ring-blue-600 dark:bg-blue-950/40 dark:text-blue-200'
                        : 'border-input text-muted-foreground hover:bg-muted'
                "
            >
                <div
                    class="flex items-center gap-2 font-semibold text-foreground"
                >
                    <ArrowDownLeft
                        class="size-4 text-blue-600 dark:text-blue-400"
                    />
                    <span>GCash Cash-Out</span>
                </div>
                <p class="text-xs text-muted-foreground">
                    Customer sends GCash (+GCash) → Staff dispenses physical
                    cash (-Drawer Cash)
                </p>
            </button>

            <button
                type="button"
                @click="remittanceDirection = 'GCASH_CASH_IN'"
                class="flex flex-col items-start gap-1.5 rounded-lg border p-3.5 text-left transition-all"
                :class="
                    remittanceDirection === 'GCASH_CASH_IN'
                        ? 'border-emerald-600 bg-emerald-50/70 text-emerald-900 ring-1 ring-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-200'
                        : 'border-input text-muted-foreground hover:bg-muted'
                "
            >
                <div
                    class="flex items-center gap-2 font-semibold text-foreground"
                >
                    <ArrowUpRight
                        class="size-4 text-emerald-600 dark:text-emerald-400"
                    />
                    <span>GCash Cash-In</span>
                </div>
                <p class="text-xs text-muted-foreground">
                    Customer gives physical cash (+Drawer Cash) → Staff sends
                    from GCash wallet (-GCash)
                </p>
            </button>
        </div>
    </div>

    <div class="grid gap-2">
        <Label for="remittance_amount">Remittance Principal Amount (PHP)</Label>
        <Input
            id="remittance_amount"
            name="amount"
            type="number"
            min="1"
            step="0.01"
            inputmode="decimal"
            v-model="amount"
            required
            placeholder="e.g. 350.00"
        />
        <p class="text-xs text-muted-foreground">
            Principal amount being converted / transferred.
        </p>
        <InputError :message="errors.amount" />
    </div>

    <div class="grid gap-2">
        <Label for="remittance_fee">Agency Service Fee (PHP)</Label>
        <Input
            id="remittance_fee"
            type="number"
            min="0"
            step="0.01"
            inputmode="decimal"
            v-model="remittanceFee"
            placeholder="0.00 (Optional service charge)"
        />
        <p class="text-xs text-muted-foreground">
            Office commission for remittance processing.
        </p>
        <InputError :message="errors.third_party_payment" />
    </div>

    <div class="rounded-lg border bg-muted/40 px-4 py-2.5 md:col-span-2">
        <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <div class="flex items-center gap-1.5">
                    <Calculator class="size-4 text-primary" />
                    <span class="text-xs text-muted-foreground">
                        Cash
                        <strong
                            :class="
                                remittanceDirection === 'GCASH_CASH_OUT'
                                    ? 'text-rose-600 dark:text-rose-400'
                                    : 'text-emerald-600 dark:text-emerald-400'
                            "
                        >
                            {{
                                remittanceDirection === 'GCASH_CASH_OUT'
                                    ? '-'
                                    : '+'
                            }}{{ formatPhp(numAmount) }}
                        </strong>
                        &middot; GCash
                        <strong
                            :class="
                                remittanceDirection === 'GCASH_CASH_OUT'
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : 'text-rose-600 dark:text-rose-400'
                            "
                        >
                            {{
                                remittanceDirection === 'GCASH_CASH_OUT'
                                    ? '+'
                                    : '-'
                            }}{{ formatPhp(numAmount) }}
                        </strong>
                        <template v-if="Number(remittanceFee) > 0">
                            &middot; Fee:
                            <strong
                                class="text-emerald-600 dark:text-emerald-400"
                            >
                                {{ formatPhp(Number(remittanceFee)) }}
                            </strong>
                        </template>
                    </span>
                </div>
            </div>
            <button
                type="button"
                class="text-xs font-medium text-primary hover:underline"
                @click="showBreakdown = !showBreakdown"
            >
                {{ showBreakdown ? 'Hide breakdown' : 'Show breakdown' }}
            </button>
        </div>

        <div v-if="showBreakdown" class="mt-3 space-y-3 border-t pt-3">
            <div class="grid grid-cols-1 gap-3 text-xs sm:grid-cols-2">
                <div class="space-y-1 rounded-md border bg-card p-3">
                    <div
                        class="flex items-center gap-1.5 font-medium text-muted-foreground"
                    >
                        <Wallet class="size-3.5" />
                        <span>Physical Cash Drawer</span>
                    </div>
                    <p
                        class="text-base font-bold"
                        :class="
                            remittanceDirection === 'GCASH_CASH_OUT'
                                ? 'text-rose-600 dark:text-rose-400'
                                : 'text-emerald-600 dark:text-emerald-400'
                        "
                    >
                        {{ remittanceDirection === 'GCASH_CASH_OUT' ? '-' : '+'
                        }}{{ formatPhp(numAmount) }}
                    </p>
                    <p class="text-[11px] text-muted-foreground">
                        {{
                            remittanceDirection === 'GCASH_CASH_OUT'
                                ? 'Outflow: Dispensed out to customer'
                                : 'Inflow: Cash collected into drawer'
                        }}
                    </p>
                </div>

                <div class="space-y-1 rounded-md border bg-card p-3">
                    <div
                        class="flex items-center gap-1.5 font-medium text-muted-foreground"
                    >
                        <Smartphone class="size-3.5 text-blue-500" />
                        <span>GCash Ledger Wallet</span>
                    </div>
                    <p
                        class="text-base font-bold"
                        :class="
                            remittanceDirection === 'GCASH_CASH_OUT'
                                ? 'text-emerald-600 dark:text-emerald-400'
                                : 'text-rose-600 dark:text-rose-400'
                        "
                    >
                        {{ remittanceDirection === 'GCASH_CASH_OUT' ? '+' : '-'
                        }}{{ formatPhp(numAmount) }}
                    </p>
                    <p class="text-[11px] text-muted-foreground">
                        {{
                            remittanceDirection === 'GCASH_CASH_OUT'
                                ? 'Inflow: Received in GCash account'
                                : 'Outflow: Transferred from GCash wallet'
                        }}
                    </p>
                </div>
            </div>

            <div
                v-if="Number(remittanceFee) > 0"
                class="flex items-center justify-between border-t pt-2 text-xs"
            >
                <span class="font-medium text-muted-foreground"
                    >DDN Agency Service Fee Earning:</span
                >
                <span
                    class="font-bold text-emerald-600 dark:text-emerald-400"
                    >{{ formatPhp(Number(remittanceFee)) }}</span
                >
            </div>
        </div>
    </div>
</template>
