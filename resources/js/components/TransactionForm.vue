<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import { Calculator, Info } from '@lucide/vue';
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
    BaggageOption,
    DdnSplitConfig,
    OnlineAssistanceType,
    PaymentMethod,
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
        psaDocumentTypes?: PsaDocumentType[];
        ddnSplitConfig?: DdnSplitConfig;
    }>(),
    {
        baggageOptions: () => [],
        onlineAssistanceTypes: () => [],
        psaDocumentTypes: () => ['BC', 'MC', 'DC', 'Cenomar'],
        ddnSplitConfig: () => ({ ddn: 0.6, employee: 0.4 }),
    },
);

const formType = ref<TransactionType>(props.transaction?.type ?? 'airline');
const paymentMethod = ref<PaymentMethod>(
    props.transaction?.payment_method ?? 'cash',
);
const transactionDate = ref<string>(
    props.transaction?.transaction_date ?? getTodayDateString(),
);
const customerName = ref<string>(props.transaction?.customer_name ?? '');
const pax = ref<number | string>(props.transaction?.pax ?? 1);
const provider = ref<string>(props.transaction?.provider ?? '');
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
const thirdPartyPayment = ref<number | string>(
    props.transaction?.third_party_payment ?? '0.00',
);
const notes = ref<string>(props.transaction?.notes ?? '');

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
            <div class="grid gap-2 md:col-span-2">
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

            <!-- 3. Customer / Person -->
            <div class="grid gap-2">
                <Label for="customer_name">Customer / Person</Label>
                <Input
                    id="customer_name"
                    name="customer_name"
                    v-model="customerName"
                    required
                    autocomplete="name"
                    placeholder="e.g. Person 1"
                />
                <InputError :message="errors.customer_name" />
            </div>

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
                    </SelectContent>
                </Select>
                <InputError :message="errors.payment_method" />
            </div>

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
                                }}] {{ option.label }} ({{
                                    formatPhp(option.price)
                                }})
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.baggage_option_id" />
                </div>

                <div class="grid gap-2 md:col-span-2">
                    <Label for="amount">Total Ticket Price (PHP)</Label>
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
                        Enter the total customer payment in Philippine pesos.
                    </p>
                    <InputError :message="errors.amount" />
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
