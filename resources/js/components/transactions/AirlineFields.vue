<script setup lang="ts">
import { Calculator } from '@lucide/vue';
import { ref } from 'vue';
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

const showBreakdown = ref(false);

const pax = defineModel<number | string>('pax', { required: true });
const tripType = defineModel<'one_way' | 'round_trip'>('tripType', {
    required: true,
});
const flightType = defineModel<string>('flightType', {
    required: true,
});
const carrierCategory = defineModel<string>('carrierCategory', {
    required: true,
});
const provider = defineModel<string>('provider', { required: true });
const hasDifferentReturnAirline = defineModel<boolean>(
    'hasDifferentReturnAirline',
    { required: true },
);
const returnProvider = defineModel<string>('returnProvider', {
    required: true,
});
const serviceDetails = defineModel<string>('serviceDetails', {
    required: true,
});
const amount = defineModel<string | number>('amount', { required: true });
const thirdPartyPayment = defineModel<string | number>('thirdPartyPayment', {
    required: true,
});

defineProps<{
    errors: Record<string, string>;
    airlineSuggestions: string[];
    portalBalance?: number;
    numAmount: number;
    numThirdParty: number;
    activeMarkupRuleDescription?: string | null;
}>();
</script>

<template>
    <div class="grid gap-2">
        <Label for="pax">Number of Passengers (Pax)</Label>
        <Input
            id="pax"
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
        <Label for="flight_type_select">Flight Type</Label>
        <Select v-model="flightType">
            <SelectTrigger id="flight_type_select" class="w-full">
                <SelectValue placeholder="Select flight type" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="local">Domestic (Local)</SelectItem>
                <SelectItem value="international">International</SelectItem>
            </SelectContent>
        </Select>
        <InputError :message="errors.flight_type" />
    </div>

    <div v-if="flightType === 'international'" class="grid gap-2">
        <Label for="carrier_category_select">Carrier Category</Label>
        <Select v-model="carrierCategory">
            <SelectTrigger id="carrier_category_select" class="w-full">
                <SelectValue placeholder="Select carrier category" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="budget">
                    Budget Carrier (₱1,250 Markup)
                </SelectItem>
                <SelectItem value="full_service">
                    Full Service Carrier (₱1,500 Markup)
                </SelectItem>
            </SelectContent>
        </Select>
        <InputError :message="errors.carrier_category" />
    </div>

    <div class="grid gap-2">
        <Label for="provider">
            {{
                tripType === 'round_trip' && hasDifferentReturnAirline
                    ? 'Outbound Airline or Provider'
                    : 'Airline or Provider'
            }}
        </Label>
        <Input
            id="provider"
            name="provider"
            v-model="provider"
            :placeholder="
                flightType === 'international'
                    ? 'e.g. Scoot, PAL, Singapore Airlines'
                    : 'e.g. Cebu Pacific'
            "
            list="airlines"
        />
        <datalist id="airlines">
            <option
                v-for="airline in airlineSuggestions"
                :key="airline"
                :value="airline"
            />
        </datalist>
        <InputError :message="errors.provider" />
    </div>

    <div
        v-if="tripType === 'round_trip'"
        class="flex items-center gap-2 pt-1 pb-1 md:col-span-2"
    >
        <input
            id="has_different_return_airline"
            type="checkbox"
            v-model="hasDifferentReturnAirline"
            class="size-4 rounded border-input text-primary focus:ring-primary"
        />
        <Label
            for="has_different_return_airline"
            class="cursor-pointer text-xs font-medium text-foreground"
        >
            Different airline for return flight (e.g. Outbound PAL, Return Cebu
            Pacific)
        </Label>
    </div>

    <div
        v-if="tripType === 'round_trip' && hasDifferentReturnAirline"
        class="grid gap-2 md:col-span-2"
    >
        <Label
            for="return_provider"
            class="flex items-center gap-1.5 font-medium text-blue-600 dark:text-blue-400"
        >
            <span>Return Airline or Provider</span>
        </Label>
        <Input
            id="return_provider"
            name="return_provider"
            v-model="returnProvider"
            :placeholder="
                flightType === 'international'
                    ? 'e.g. Singapore Airlines, Scoot'
                    : 'e.g. Philippine Airlines'
            "
            list="return-airlines"
        />
        <datalist id="return-airlines">
            <option
                v-for="airline in airlineSuggestions"
                :key="airline"
                :value="airline"
            />
        </datalist>
        <p class="text-[11px] text-muted-foreground">
            Markup will be calculated independently for each leg (Outbound +
            Return) based on each carrier's rule.
        </p>
        <InputError :message="errors.return_provider" />
    </div>

    <div
        class="grid gap-2"
        :class="flightType === 'international' ? '' : 'md:col-span-2'"
    >
        <Label for="airline_service_details">Flight Route / Details</Label>
        <Input
            id="airline_service_details"
            name="service_details"
            v-model="serviceDetails"
            placeholder="e.g. Manila to Cebu (MNL-CEB)"
        />
        <InputError :message="errors.service_details" />
    </div>

    <div class="grid gap-2">
        <Label for="amount">Customer Ticket Price (PHP)</Label>
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
            Total amount collected from customer.
        </p>
        <InputError :message="errors.amount" />
    </div>

    <div class="grid gap-2">
        <div class="flex items-center justify-between">
            <Label for="third_party_payment">Portal Net Cost (PHP)</Label>
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
            id="third_party_payment"
            name="third_party_payment"
            type="number"
            min="0"
            step="0.01"
            inputmode="decimal"
            v-model="thirdPartyPayment"
            placeholder="0.00 (Optional)"
        />
        <p class="text-xs text-muted-foreground">
            Net ticket fare deducted from flight portal deposit. Auto-calculates
            if markup rule is set.
        </p>
        <InputError :message="errors.third_party_payment" />
    </div>

    <div
        v-if="numAmount > 0 && numThirdParty > 0"
        class="rounded-lg border bg-muted/40 px-4 py-2.5 md:col-span-2"
    >
        <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
            <div class="flex items-center gap-2">
                <Calculator class="size-4 text-primary" />
                <span class="text-muted-foreground">DDN Margin:</span>
                <span class="font-bold text-emerald-600 dark:text-emerald-400">
                    {{ formatPhp(Math.max(0, numAmount - numThirdParty)) }}
                </span>
                <span
                    v-if="activeMarkupRuleDescription"
                    class="text-xs text-muted-foreground"
                >
                    ({{ activeMarkupRuleDescription }})
                </span>
            </div>
            <button
                type="button"
                class="text-xs font-medium text-primary hover:underline"
                @click="showBreakdown = !showBreakdown"
            >
                {{ showBreakdown ? 'Hide breakdown' : 'Show breakdown' }}
            </button>
        </div>
        <div
            v-if="showBreakdown"
            class="mt-3 grid grid-cols-3 gap-2 border-t pt-3 text-center text-xs"
        >
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
