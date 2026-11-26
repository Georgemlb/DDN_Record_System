<script setup lang="ts">
import { Ship } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatPhp } from '@/lib/transactionFormat';

const pax = defineModel<number | string>('pax', { required: true });
const tripType = defineModel<'one_way' | 'round_trip'>('tripType', {
    required: true,
});
const provider = defineModel<string>('provider', { required: true });
const serviceDetails = defineModel<string>('serviceDetails', {
    required: true,
});
const amount = defineModel<string | number>('amount', { required: true });
const thirdPartyPayment = defineModel<string | number>('thirdPartyPayment', {
    required: true,
});

defineProps<{
    errors: Record<string, string>;
    ferrySuggestions: string[];
    portalBalance?: number;
    numAmount: number;
    numThirdParty: number;
    activeMarkupRuleDescription?: string | null;
}>();
</script>

<template>
    <div class="grid gap-2">
        <Label for="ferry_pax">Number of Passengers (Pax)</Label>
        <Input
            id="ferry_pax"
            name="pax"
            type="number"
            min="1"
            step="1"
            v-model="pax"
            required
            placeholder="e.g. 1"
        />
        <InputError :message="errors.pax" />
    </div>

    <div class="grid gap-2">
        <Label>Trip Type</Label>
        <div class="grid grid-cols-2 gap-2">
            <button
                type="button"
                @click="tripType = 'one_way'"
                class="flex items-center justify-center gap-1.5 rounded-md border p-2 text-xs font-medium transition-all"
                :class="
                    tripType === 'one_way'
                        ? 'border-primary bg-primary/10 text-primary ring-1 ring-primary'
                        : 'border-input text-muted-foreground hover:bg-muted'
                "
            >
                <span>One-way (1 leg)</span>
            </button>
            <button
                type="button"
                @click="tripType = 'round_trip'"
                class="flex items-center justify-center gap-1.5 rounded-md border p-2 text-xs font-medium transition-all"
                :class="
                    tripType === 'round_trip'
                        ? 'border-primary bg-primary/10 text-primary ring-1 ring-primary'
                        : 'border-input text-muted-foreground hover:bg-muted'
                "
            >
                <span>Round-trip (2 legs)</span>
            </button>
        </div>
        <InputError :message="errors.trip_type" />
    </div>

    <div class="grid gap-2">
        <Label for="ferry_provider">Ferry Provider / Category</Label>
        <Input
            id="ferry_provider"
            name="provider"
            v-model="provider"
            placeholder="All Ferries"
            list="ferries-list"
        />
        <datalist id="ferries-list">
            <option
                v-for="ferry in ferrySuggestions"
                :key="ferry"
                :value="ferry"
            />
        </datalist>
        <InputError :message="errors.provider" />
    </div>

    <div class="grid gap-2">
        <Label for="ferry_service_details">Route / Trip Details</Label>
        <Input
            id="ferry_service_details"
            name="service_details"
            v-model="serviceDetails"
            placeholder="e.g. Batangas to Calapan"
        />
        <InputError :message="errors.service_details" />
    </div>

    <div class="grid gap-2">
        <Label for="ferry_amount">Customer Ticket Price (PHP)</Label>
        <Input
            id="ferry_amount"
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
            Total amount collected from customer.
        </p>
        <InputError :message="errors.amount" />
    </div>

    <div class="grid gap-2">
        <div class="flex items-center justify-between">
            <Label for="ferry_third_party_payment">Portal Net Cost (PHP)</Label>
            <span
                v-if="portalBalance !== undefined"
                :class="[
                    'text-xs font-medium',
                    portalBalance < 10000
                        ? 'font-semibold text-amber-600 dark:text-amber-400'
                        : 'text-muted-foreground',
                ]"
            >
                Balance: {{ formatPhp(portalBalance) }}
            </span>
        </div>
        <Input
            id="ferry_third_party_payment"
            name="third_party_payment"
            type="number"
            min="0"
            step="0.01"
            inputmode="decimal"
            v-model="thirdPartyPayment"
            placeholder="0.00 (Optional)"
        />
        <p class="text-xs text-muted-foreground">
            Net ticket fare deducted from portal deposit. Auto-calculates with
            fixed ₱200 markup per pax/leg.
        </p>
        <InputError :message="errors.third_party_payment" />
    </div>

    <div
        v-if="numAmount > 0 && numThirdParty > 0"
        class="rounded-lg border bg-muted/40 p-4 md:col-span-2"
    >
        <div class="flex items-center justify-between">
            <div
                class="flex items-center gap-2 text-sm font-semibold text-primary"
            >
                <Ship class="size-4" />
                <span>Ferry Ticketing Margin Preview</span>
            </div>
            <span
                v-if="activeMarkupRuleDescription"
                class="text-xs font-medium text-muted-foreground"
            >
                {{ activeMarkupRuleDescription }}
            </span>
        </div>
        <div class="mt-2 grid grid-cols-3 gap-2 text-center text-xs">
            <div class="rounded border bg-background p-2">
                <span class="text-muted-foreground">Customer Paid</span>
                <p class="font-semibold text-foreground">
                    {{ formatPhp(numAmount) }}
                </p>
            </div>
            <div class="rounded border bg-background p-2">
                <span class="text-muted-foreground">Portal Cost</span>
                <p class="font-semibold text-blue-600 dark:text-blue-400">
                    -{{ formatPhp(numThirdParty) }}
                </p>
            </div>
            <div class="rounded border bg-background p-2">
                <span class="text-muted-foreground">DDN Margin</span>
                <p class="font-semibold text-emerald-600 dark:text-emerald-400">
                    {{ formatPhp(Math.max(0, numAmount - numThirdParty)) }}
                </p>
            </div>
        </div>
    </div>
</template>
