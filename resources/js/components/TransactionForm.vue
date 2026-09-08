<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import { AlertCircle, Calculator, CheckCircle, Clock, Info } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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
import { index } from '@/routes/transactions';
import type {
    AirlineMarkup,
    BaggageOption,
    Customer,
    DdnSplitConfig,
    FlightType,
    OnlineAssistanceType,
    PaymentMethod,
    PaymentStatus,
    PsaDocumentType,
    Transaction,
    TransactionType,
} from '@/types/transaction';

const props = withDefaults(
    defineProps<{
        action: string;
        method: 'post' | 'put';
        submitLabel: string;
        transaction?: Transaction;
        baggageOptions?: BaggageOption[];
        onlineAssistanceTypes?: OnlineAssistanceType[];
        airlineMarkups?: AirlineMarkup[];
        psaDocumentTypes?: PsaDocumentType[];
        ddnSplitConfig?: DdnSplitConfig;
        portalBalance?: number;
        recurringCustomers?: Customer[];
        initialCustomers?: Customer[];
    }>(),
    {
        baggageOptions: () => [],
        onlineAssistanceTypes: () => [],
        airlineMarkups: () => [],
        psaDocumentTypes: () => ['BC', 'MC', 'DC', 'Cenomar'],
        ddnSplitConfig: () => ({ ddn: 0.6, employee: 0.4 }),
        recurringCustomers: () => [],
        initialCustomers: () => [],
    },
);

const formType = ref<TransactionType>(props.transaction?.type ?? 'airline');
const paymentMethod = ref<PaymentMethod>(
    props.transaction?.payment_method ?? 'cash',
);
const paymentStatus = ref<PaymentStatus>(
    props.transaction?.payment_status ?? 'paid',
);
const transactionDate = ref<string>(
    props.transaction?.transaction_date ?? getTodayDateString(),
);
const customerId = ref<number | null>(props.transaction?.customer_id ?? null);
const customerName = ref<string>(props.transaction?.customer_name ?? '');
const customerContact = ref<string>(
    props.transaction?.customer?.contact_number ?? '',
);
const amountPaid = ref<number | string>(
    props.transaction?.amount_paid !== undefined &&
        props.transaction?.amount_paid !== null
        ? props.transaction.amount_paid
        : '',
);
const pax = ref<number | string>(props.transaction?.pax ?? 1);
const provider = ref<string>(props.transaction?.provider ?? '');
const flightType = ref<FlightType | ''>(
    props.transaction?.flight_type ?? 'local',
);
const baggageOptionId = ref<string>(
    props.transaction?.baggage_option_id
        ? String(props.transaction.baggage_option_id)
        : 'none',
);
const psaDocumentType = ref<PsaDocumentType | ''>(
    props.transaction?.psa_document_type ?? 'BC',
);
const onlineAssistanceTypeId = ref<string>(
    props.transaction?.online_assistance_type_id
        ? String(props.transaction.online_assistance_type_id)
        : props.onlineAssistanceTypes[0]?.id
          ? String(props.onlineAssistanceTypes[0].id)
          : '',
);
const amount = ref<number | string>(props.transaction?.amount ?? '');
const cashAmount = ref<number | string>(props.transaction?.cash_amount ?? '');
const gcashAmount = ref<number | string>(props.transaction?.gcash_amount ?? '');
const thirdPartyPayment = ref<number | string>(
    props.transaction?.third_party_payment ?? '0.00',
);
const notes = ref<string>(props.transaction?.notes ?? '');

// Auto-calculation for Airline Markups

watch(
    [provider, flightType, amount],
    ([newProvider, newFlightType, newAmount]) => {
        if (formType.value !== 'airline') {
            return;
        }

        const numAmt = Number(newAmount) || 0;

        if (!newProvider || !newFlightType || numAmt <= 0) {
            return;
        }

        const markup = props.airlineMarkups.find(
            (m) =>
                m.airline.toLowerCase() === newProvider.toLowerCase() &&
                m.flight_type === newFlightType,
        );

        if (markup) {
            const markupAmt = Number(markup.markup_amount) || 0;
            const calculatedCost = Math.max(0, numAmt - markupAmt);

            thirdPartyPayment.value = calculatedCost;
        }
    },
);

// Computed Remaining Balance
const computedRemainingBalance = computed(() => {
    const total = Number(amount.value) || 0;

    if (paymentStatus.value === 'paid') {
return 0;
}

    if (paymentStatus.value === 'unpaid') {
return total;
}

    const paid = Number(amountPaid.value) || 0;

    return Math.max(0, Math.round((total - paid) * 100) / 100);
});

const getPayableToday = () => {
    if (paymentStatus.value === 'paid') {
return Number(amount.value) || 0;
}

    if (paymentStatus.value === 'partial') {
return Number(amountPaid.value) || 0;
}

    return 0;
};

// Split payment logic
const handleCashChange = () => {
    const total = getPayableToday();
    const cash = Number(cashAmount.value) || 0;

    if (total > 0 && cash <= total) {
        gcashAmount.value = Math.max(0, Math.round((total - cash) * 100) / 100);
    }
};

const handleGcashChange = () => {
    const total = getPayableToday();
    const gcash = Number(gcashAmount.value) || 0;

    if (total > 0 && gcash <= total) {
        cashAmount.value = Math.max(0, Math.round((total - gcash) * 100) / 100);
    }
};

// Auto-fill amount when online assistance type changes (if creating or user selects new type)
const handleOnlineAssistanceTypeChange = (value: unknown) => {
    onlineAssistanceTypeId.value = value ? String(value) : '';
    const selected = props.onlineAssistanceTypes.find(
        (t) => String(t.id) === String(value),
    );

    if (selected) {
        amount.value = selected.price;
    }
};

// If online assistance is selected initially and amount is empty, populate from default option
watch(
    formType,
    (newType) => {
        if (
            newType === 'online_assistance' &&
            !amount.value &&
            onlineAssistanceTypeId.value
        ) {
            handleOnlineAssistanceTypeChange(onlineAssistanceTypeId.value);
        }
    },
    { immediate: true },
);

const filteredBaggageOptions = computed(() => {
    const currentProvider = provider.value.trim().toLowerCase();

    return props.baggageOptions.filter((option) => {
        // Always include currently selected option
        if (String(option.id) === baggageOptionId.value) {
            return true;
        }

        // Include universal options
        if (!option.airline) {
            return true;
        }

        // Include airline specific options
        return option.airline.toLowerCase() === currentProvider;
    });
});

const airlineSuggestions = computed(() => {
    const defaultAirlines = ['Cebu Pacific', 'Philippine Airlines', 'AirAsia'];
    const configuredAirlines = props.baggageOptions
        .map((opt) => opt.airline?.trim())
        .filter((airline): airline is string => Boolean(airline));

    return Array.from(new Set([...defaultAirlines, ...configuredAirlines]));
});

// Client-side computed previews
const numAmount = computed(() => Number(amount.value) || 0);
const numThirdParty = computed(() => Number(thirdPartyPayment.value) || 0);

const computedTranslationOrPsaDdnAmount = computed(() => {
    return Math.max(0, numAmount.value - numThirdParty.value);
});

const computedOnlineDdnShare = computed(() => {
    const ddnRate = props.ddnSplitConfig?.ddn ?? 0.6;

    return numAmount.value * ddnRate;
});

const computedOnlineEmployeeShare = computed(() => {
    const empRate = props.ddnSplitConfig?.employee ?? 0.4;

    return numAmount.value * empRate;
});

function onCustomerInput() {
    const list =
        props.recurringCustomers && props.recurringCustomers.length
            ? props.recurringCustomers
            : (props.initialCustomers ?? []);
    const trimmed = customerName.value.trim().toLowerCase();
    const match = list.find((c) => c.name.toLowerCase() === trimmed);

    if (match) {
        customerId.value = match.id;

        if (!customerContact.value && match.contact_number) {
            customerContact.value = match.contact_number;
        }
    } else {
        customerId.value = null;
    }
}

const psaDocumentTypeLabels: Record<PsaDocumentType, string> = {
    BC: 'BC — Birth Certificate',
    MC: 'MC — Marriage Certificate',
    DC: 'DC — Death Certificate',
    Cenomar: 'Cenomar — Certificate of No Marriage',
};
</script>

<template>
    <Form
        :action="action"
        :method="method"
        class="space-y-6"
        v-slot="{ errors, processing }"
    >
        <!-- Hidden inputs for custom select values and calculated fields -->
        <input type="hidden" name="type" :value="formType" />
        <input type="hidden" name="payment_method" :value="paymentMethod" />
        <input v-if="customerId" type="hidden" name="customer_id" :value="customerId" />
        <input type="hidden" name="payment_status" :value="paymentStatus" />
        <input
            type="hidden"
            name="amount_paid"
            :value="
                paymentStatus === 'paid'
                    ? (amount || 0)
                    : paymentStatus === 'unpaid'
                      ? 0
                      : (amountPaid || 0)
            "
        />
        <input
            v-if="formType === 'airline'"
            type="hidden"
            name="flight_type"
            :value="flightType"
        />
        <input
            v-if="formType === 'airline'"
            type="hidden"
            name="baggage_option_id"
            :value="baggageOptionId === 'none' ? '' : baggageOptionId"
        />
        <input
            v-if="formType === 'psa'"
            type="hidden"
            name="psa_document_type"
            :value="psaDocumentType"
        />
        <input
            v-if="formType === 'online_assistance'"
            type="hidden"
            name="online_assistance_type_id"
            :value="onlineAssistanceTypeId"
        />

        <div class="grid gap-6 md:grid-cols-2">
            <!-- 1. Transaction Type Selector -->
            <div class="grid gap-2">
                <Label for="type-select">Transaction / Service Type</Label>
                <Select v-model="formType">
                    <SelectTrigger id="type-select" class="w-full">
                        <SelectValue placeholder="Select transaction type" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="airline">
                            Airline Ticket / Travel
                        </SelectItem>
                        <SelectItem value="translation">
                            Document Translation
                        </SelectItem>
                        <SelectItem value="psa"> PSA Assistance </SelectItem>
                        <SelectItem value="online_assistance">
                            Online Assistance
                        </SelectItem>
                        <SelectItem value="printing"> Printing </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors.type" />
            </div>

            <!-- 2. Transaction Date (Defaults to Today) -->
            <div class="grid gap-2">
                <Label for="transaction_date">Transaction date</Label>
                <Input
                    id="transaction_date"
                    name="transaction_date"
                    type="date"
                    v-model="transactionDate"
                    required
                />
                <InputError :message="errors.transaction_date" />
            </div>

            <!-- 3. Customer / Person with Native Recurring Datalist Autocomplete -->
            <div class="grid gap-2">
                <Label for="customer_name">Customer / Person</Label>
                <Input
                    id="customer_name"
                    name="customer_name"
                    v-model="customerName"
                    list="recurring-customers-list"
                    required
                    placeholder="e.g. Juan Dela Cruz"
                    autocomplete="off"
                    @input="onCustomerInput"
                    @change="onCustomerInput"
                />
                <datalist id="recurring-customers-list">
                    <option
                        v-for="c in (recurringCustomers && recurringCustomers.length ? recurringCustomers : initialCustomers)"
                        :key="c.id"
                        :value="c.name"
                    >
                        {{ c.contact_number ? `${c.name} (${c.contact_number})` : c.name }}
                    </option>
                </datalist>
                <InputError :message="errors.customer_name" />
            </div>

            <!-- 4. Contact Number (Autofilled for recurring customer or entered manually) -->
            <div class="grid gap-2">
                <Label for="customer_contact" class="flex items-center justify-between">
                    <span>Contact Number</span>
                    <span class="text-xs font-normal text-muted-foreground">Optional</span>
                </Label>
                <Input
                    id="customer_contact"
                    name="customer_contact"
                    v-model="customerContact"
                    placeholder="e.g. 0917 123 4567"
                    autocomplete="tel"
                />
                <InputError :message="errors.customer_contact" />
            </div>

            <!-- 4. Payment Status (Paid / Downpayment / Unpaid) -->
            <div class="grid gap-2 md:col-span-2">
                <Label>Payment Status</Label>
                <div class="grid grid-cols-3 gap-2">
                    <button
                        type="button"
                        @click="paymentStatus = 'paid'"
                        class="flex items-center justify-center gap-1.5 rounded-md border p-2 text-xs font-medium transition-all"
                        :class="
                            paymentStatus === 'paid'
                                ? 'border-emerald-600 bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-300'
                                : 'border-input text-muted-foreground hover:bg-muted'
                        "
                    >
                        <CheckCircle class="size-3.5" />
                        <span>Fully Paid</span>
                    </button>

                    <button
                        type="button"
                        @click="paymentStatus = 'partial'"
                        class="flex items-center justify-center gap-1.5 rounded-md border p-2 text-xs font-medium transition-all"
                        :class="
                            paymentStatus === 'partial'
                                ? 'border-amber-600 bg-amber-50 text-amber-700 ring-1 ring-amber-600 dark:bg-amber-950/40 dark:text-amber-300'
                                : 'border-input text-muted-foreground hover:bg-muted'
                        "
                    >
                        <Clock class="size-3.5" />
                        <span>Downpayment / Partial</span>
                    </button>

                    <button
                        type="button"
                        @click="paymentStatus = 'unpaid'"
                        class="flex items-center justify-center gap-1.5 rounded-md border p-2 text-xs font-medium transition-all"
                        :class="
                            paymentStatus === 'unpaid'
                                ? 'border-rose-600 bg-rose-50 text-rose-700 ring-1 ring-rose-600 dark:bg-rose-950/40 dark:text-rose-300'
                                : 'border-input text-muted-foreground hover:bg-muted'
                        "
                    >
                        <AlertCircle class="size-3.5" />
                        <span>Unpaid (On Pickup)</span>
                    </button>
                </div>
                <InputError :message="errors.payment_status" />
            </div>

            <!-- Downpayment Amount & Remaining Balance (if Partial) -->
            <template v-if="paymentStatus === 'partial'">
                <div class="grid gap-2">
                    <Label for="amount_paid">Downpayment Paid Today (PHP)</Label>
                    <Input
                        id="amount_paid"
                        type="number"
                        min="0.01"
                        :max="amount ? Number(amount) : undefined"
                        step="0.01"
                        inputmode="decimal"
                        v-model="amountPaid"
                        required
                        placeholder="0.00"
                    />
                    <InputError :message="errors.amount_paid" />
                </div>

                <div
                    class="rounded-lg border bg-amber-50/50 p-3 text-xs dark:bg-amber-950/20"
                >
                    <span class="font-medium text-amber-800 dark:text-amber-300">
                        Remaining Balance Due on Pickup:
                    </span>
                    <p
                        class="mt-1 text-lg font-bold text-amber-600 dark:text-amber-400"
                    >
                        {{ formatPhp(computedRemainingBalance) }}
                    </p>
                    <p class="text-[11px] text-muted-foreground">
                        Staff can collect this balance later via the "Collect
                        Balance" action.
                    </p>
                </div>
            </template>

            <!-- 4. Payment Method -->
            <div class="grid gap-2 md:col-span-2">
                <Label for="payment_method_select">Payment method</Label>
                <Select v-model="paymentMethod">
                    <SelectTrigger id="payment_method_select" class="w-full">
                        <SelectValue placeholder="Select payment method" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="cash">Cash</SelectItem>
                        <SelectItem value="gcash">GCash</SelectItem>
                        <SelectItem value="split"
                            >Split (Cash & GCash)</SelectItem
                        >
                    </SelectContent>
                </Select>
                <InputError :message="errors.payment_method" />
            </div>

            <!-- SPLIT PAYMENT FIELDS -->
            <template v-if="paymentMethod === 'split'">
                <div class="grid gap-2">
                    <Label for="cash_amount">Cash Amount (PHP)</Label>
                    <Input
                        id="cash_amount"
                        name="cash_amount"
                        type="number"
                        min="0"
                        step="0.01"
                        inputmode="decimal"
                        v-model="cashAmount"
                        @input="handleCashChange"
                        required
                        placeholder="0.00"
                    />
                    <InputError :message="errors.cash_amount" />
                </div>
                <div class="grid gap-2">
                    <Label for="gcash_amount">GCash Amount (PHP)</Label>
                    <Input
                        id="gcash_amount"
                        name="gcash_amount"
                        type="number"
                        min="0"
                        step="0.01"
                        inputmode="decimal"
                        v-model="gcashAmount"
                        @input="handleGcashChange"
                        required
                        placeholder="0.00"
                    />
                    <InputError :message="errors.gcash_amount" />
                </div>
            </template>

            <!-- ============================================== -->
            <!-- 5. TYPE-SPECIFIC FIELDS                        -->
            <!-- ============================================== -->

            <!-- AIRLINE TICKET -->
            <template v-if="formType === 'airline'">
                <div class="grid gap-2">
                    <Label for="pax">Passengers (Pax)</Label>
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
                    <Label for="provider">Airline or provider</Label>
                    <Input
                        id="provider"
                        name="provider"
                        v-model="provider"
                        placeholder="e.g. Cebu Pacific"
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

                <div class="grid gap-2">
                    <Label for="flight_type_select">Flight Type</Label>
                    <Select v-model="flightType">
                        <SelectTrigger id="flight_type_select" class="w-full">
                            <SelectValue placeholder="Select flight type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="local">Local</SelectItem>
                            <SelectItem value="international"
                                >International</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.flight_type" />
                </div>

                <div class="grid gap-2 md:col-span-2">
                    <Label for="baggage_option_select"
                        >Additional Baggage</Label
                    >
                    <Select v-model="baggageOptionId">
                        <SelectTrigger
                            id="baggage_option_select"
                            class="w-full"
                        >
                            <SelectValue
                                placeholder="Select baggage option (Optional)"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">
                                None / Default
                            </SelectItem>
                            <SelectItem
                                v-for="option in filteredBaggageOptions"
                                :key="option.id"
                                :value="String(option.id)"
                            >
                                [{{
                                    option.airline
                                        ? option.airline
                                        : 'Universal'
                                }}] {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.baggage_option_id" />
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
                        <Label for="third_party_payment"
                            >Portal Net Cost (PHP)</Label
                        >
                        <span
                            v-if="props.portalBalance !== undefined"
                            :class="[
                                'text-xs font-medium',
                                props.portalBalance < 10000
                                    ? 'font-semibold text-amber-600 dark:text-amber-400'
                                    : 'text-muted-foreground',
                            ]"
                        >
                            Balance: {{ formatPhp(props.portalBalance) }}
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
                        Net ticket fare deducted from flight portal deposit.
                        Auto-calculates if markup rule is set.
                    </p>
                    <InputError :message="errors.third_party_payment" />
                </div>

                <!-- Auto Calculation Preview -->
                <div
                    v-if="numAmount > 0 && numThirdParty > 0"
                    class="rounded-lg border bg-muted/40 p-4 md:col-span-2"
                >
                    <div
                        class="flex items-center gap-2 text-sm font-semibold text-primary"
                    >
                        <Calculator class="size-4" />
                        <span>Ticketing Margin Preview</span>
                    </div>
                    <div
                        class="mt-2 grid grid-cols-3 gap-2 text-center text-xs"
                    >
                        <div class="rounded border bg-background p-2">
                            <span class="text-muted-foreground"
                                >Customer Paid</span
                            >
                            <p class="font-semibold text-foreground">
                                {{ formatPhp(numAmount) }}
                            </p>
                        </div>
                        <div class="rounded border bg-background p-2">
                            <span class="text-muted-foreground"
                                >Portal Cost</span
                            >
                            <p
                                class="font-semibold text-blue-600 dark:text-blue-400"
                            >
                                -{{ formatPhp(numThirdParty) }}
                            </p>
                        </div>
                        <div class="rounded border bg-background p-2">
                            <span class="text-muted-foreground"
                                >DDN Margin</span
                            >
                            <p
                                class="font-semibold text-emerald-600 dark:text-emerald-400"
                            >
                                {{
                                    formatPhp(
                                        Math.max(0, numAmount - numThirdParty),
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                </div>
            </template>

            <!-- DOCUMENT TRANSLATION -->
            <template v-if="formType === 'translation'">
                <div class="grid gap-2">
                    <Label for="amount"
                        >Total Price / Customer Payment (PHP)</Label
                    >
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
                    <InputError :message="errors.amount" />
                </div>

                <div class="grid gap-2">
                    <Label for="third_party_payment"
                        >Translator Payment (PHP)</Label
                    >
                    <Input
                        id="third_party_payment"
                        name="third_party_payment"
                        type="number"
                        min="0"
                        step="0.01"
                        inputmode="decimal"
                        v-model="thirdPartyPayment"
                        required
                        placeholder="0.00"
                    />
                    <InputError :message="errors.third_party_payment" />
                </div>

                <!-- Auto Calculation Preview -->
                <div class="rounded-lg border bg-muted/40 p-4 md:col-span-2">
                    <div
                        class="flex items-center gap-2 text-sm font-semibold text-primary"
                    >
                        <Calculator class="size-4" />
                        <span>DDN Amount Calculation</span>
                    </div>
                    <div
                        class="mt-3 flex flex-wrap items-baseline justify-between gap-2 border-t pt-3"
                    >
                        <span class="text-sm text-muted-foreground">
                            DDN Net (Price − Translator Payment):
                        </span>
                        <span class="text-lg font-bold">
                            {{ formatPhp(computedTranslationOrPsaDdnAmount) }}
                        </span>
                    </div>
                </div>
            </template>

            <!-- PSA ASSISTANCE -->
            <template v-if="formType === 'psa'">
                <div class="grid gap-2 md:col-span-2">
                    <Label for="psa_doc_select">PSA Document Type</Label>
                    <Select v-model="psaDocumentType">
                        <SelectTrigger id="psa_doc_select" class="w-full">
                            <SelectValue
                                placeholder="Select PSA Document Type"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="docType in psaDocumentTypes"
                                :key="docType"
                                :value="docType"
                            >
                                {{ psaDocumentTypeLabels[docType] || docType }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.psa_document_type" />
                </div>

                <div class="grid gap-2">
                    <Label for="amount"
                        >Total Price / Customer Payment (PHP)</Label
                    >
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
                    <InputError :message="errors.amount" />
                </div>

                <div class="grid gap-2">
                    <Label for="third_party_payment"
                        >PSA Officer Payment (PHP)</Label
                    >
                    <Input
                        id="third_party_payment"
                        name="third_party_payment"
                        type="number"
                        min="0"
                        step="0.01"
                        inputmode="decimal"
                        v-model="thirdPartyPayment"
                        required
                        placeholder="0.00"
                    />
                    <InputError :message="errors.third_party_payment" />
                </div>

                <!-- Auto Calculation Preview -->
                <div class="rounded-lg border bg-muted/40 p-4 md:col-span-2">
                    <div
                        class="flex items-center gap-2 text-sm font-semibold text-primary"
                    >
                        <Calculator class="size-4" />
                        <span>DDN Amount Calculation</span>
                    </div>
                    <div
                        class="mt-3 flex flex-wrap items-baseline justify-between gap-2 border-t pt-3"
                    >
                        <span class="text-sm text-muted-foreground">
                            DDN Net (Price − PSA Officer Payment):
                        </span>
                        <span class="text-lg font-bold">
                            {{ formatPhp(computedTranslationOrPsaDdnAmount) }}
                        </span>
                    </div>
                </div>
            </template>

            <!-- ONLINE ASSISTANCE -->
            <template v-if="formType === 'online_assistance'">
                <div class="grid gap-2 md:col-span-2">
                    <Label for="assistance_type_select"
                        >Online Assistance Type</Label
                    >
                    <Select
                        :model-value="onlineAssistanceTypeId"
                        @update:model-value="handleOnlineAssistanceTypeChange"
                    >
                        <SelectTrigger
                            id="assistance_type_select"
                            class="w-full"
                        >
                            <SelectValue placeholder="Select assistance type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="typeOption in onlineAssistanceTypes"
                                :key="typeOption.id"
                                :value="String(typeOption.id)"
                            >
                                {{ typeOption.name }} ({{
                                    formatPhp(typeOption.price)
                                }})
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.online_assistance_type_id" />
                </div>

                <div class="grid gap-2 md:col-span-2">
                    <Label for="amount"
                        >Total Price / Customer Payment (PHP)</Label
                    >
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
                    <InputError :message="errors.amount" />
                </div>

                <!-- 60/40 Split Calculation Preview -->
                <div class="rounded-lg border bg-muted/40 p-4 md:col-span-2">
                    <div
                        class="flex items-center gap-2 text-sm font-semibold text-primary"
                    >
                        <Calculator class="size-4" />
                        <span>Automatic 60/40 Revenue Split</span>
                    </div>
                    <div class="mt-3 grid gap-3 border-t pt-3 sm:grid-cols-2">
                        <div class="rounded-md border bg-card p-3">
                            <p class="text-xs text-muted-foreground">
                                DDN Share (60%)
                            </p>
                            <p class="mt-1 text-lg font-bold text-primary">
                                {{ formatPhp(computedOnlineDdnShare) }}
                            </p>
                        </div>
                        <div class="rounded-md border bg-card p-3">
                            <p class="text-xs text-muted-foreground">
                                Employee Share (40%)
                            </p>
                            <p class="mt-1 text-lg font-bold">
                                {{ formatPhp(computedOnlineEmployeeShare) }}
                            </p>
                        </div>
                    </div>
                    <p
                        class="mt-2 flex items-center gap-1.5 text-xs text-muted-foreground"
                    >
                        <Info class="size-3.5 shrink-0" />
                        Shares are automatically calculated from the total price
                        upon saving.
                    </p>
                </div>
            </template>

            <!-- PRINTING -->
            <template v-if="formType === 'printing'">
                <div class="grid gap-2 md:col-span-2">
                    <Label for="amount">Total Printing Price (PHP)</Label>
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
                        Enter the total amount charged to the customer.
                    </p>
                    <InputError :message="errors.amount" />
                </div>
            </template>

            <!-- 6. Notes (Common to all types) -->
            <div class="grid gap-2 md:col-span-2">
                <Label for="notes">Notes</Label>
                <textarea
                    id="notes"
                    name="notes"
                    rows="3"
                    v-model="notes"
                    class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                    placeholder="Optional notes about this transaction"
                ></textarea>
                <InputError :message="errors.notes" />
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 border-t pt-6">
            <Button type="submit" :disabled="processing">
                {{ submitLabel }}
            </Button>
            <Button variant="outline" as-child>
                <Link :href="index()">Cancel</Link>
            </Button>
        </div>
    </Form>
</template>
