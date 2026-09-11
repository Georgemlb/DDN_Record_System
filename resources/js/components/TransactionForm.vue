<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowDownLeft,
    ArrowUpRight,
    Calculator,
    CheckCircle,
    Clock,
    Info,
    Ship,
    Smartphone,
    Wallet,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import GcashIcon from '@/components/icons/GcashIcon.vue';
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
    CarrierCategory,
    Customer,
    DdnSplitConfig,
    FlightType,
    OnlineAssistanceType,
    PaymentMethod,
    PaymentStatus,
    PsaDocumentType,
    PsaRate,
    Transaction,
    TransactionType,
    TripType,
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
        psaRates?: PsaRate[];
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
        psaRates: () => [],
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
const tripType = ref<TripType>(props.transaction?.trip_type ?? 'one_way');
const carrierCategory = ref<CarrierCategory>(
    props.transaction?.carrier_category ?? 'budget',
);
const provider = ref<string>(
    props.transaction?.provider ??
        (formType.value === 'ferry' ? 'All Ferries' : ''),
);
const returnProvider = ref<string>(props.transaction?.return_provider ?? '');
const hasDifferentReturnAirline = ref<boolean>(
    Boolean(props.transaction?.return_provider),
);
const flightType = ref<FlightType | ''>(
    props.transaction?.flight_type ?? 'local',
);
const serviceDetails = ref<string>(props.transaction?.service_details ?? '');
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

const getInitialDisbursementSource = (): 'gcash' | 'cash' | 'none' => {
    if (props.transaction?.disbursement_source === 'cash') return 'cash';
    if (props.transaction?.disbursement_source === 'none') return 'none';
    if (props.transaction?.disbursement_source === 'gcash') return 'gcash';
    if (formType.value === 'psa' || formType.value === 'translation')
        return 'cash';
    return 'gcash';
};

const disbursementSource = ref<'gcash' | 'cash' | 'none'>(
    getInitialDisbursementSource(),
);

const remittanceDirection = ref<'GCASH_CASH_OUT' | 'GCASH_CASH_IN'>(
    props.transaction?.service_details === 'GCASH_CASH_IN'
        ? 'GCASH_CASH_IN'
        : 'GCASH_CASH_OUT',
);

const remittanceFee = ref<number | string>(
    props.transaction?.ddn_amount !== null &&
        props.transaction?.ddn_amount !== undefined
        ? props.transaction.ddn_amount
        : (props.transaction?.third_party_payment ?? '0.00'),
);

watch(
    [formType, remittanceDirection],
    () => {
        if (formType.value === 'remittance') {
            paymentStatus.value = 'paid';
            paymentMethod.value =
                remittanceDirection.value === 'GCASH_CASH_OUT'
                    ? 'gcash'
                    : 'cash';
        }
    },
    { immediate: true },
);

// Auto-calculation for Airline & Ferry Markups
const calculatePortalCost = () => {
    const numAmt = Number(amount.value) || 0;

    if (numAmt <= 0) {
        return;
    }

    const numPax = Math.max(1, Number(pax.value) || 1);
    const legs = tripType.value === 'round_trip' ? 2 : 1;

    if (formType.value === 'airline') {
        let markupTotal = 0;

        if (flightType.value === 'local') {
            const ruleOutbound = props.airlineMarkups.find(
                (m) =>
                    (m.service_type ?? 'flight') === 'flight' &&
                    m.flight_type === 'local' &&
                    m.airline.toLowerCase() ===
                        provider.value.trim().toLowerCase(),
            );

            if (
                tripType.value === 'round_trip' &&
                hasDifferentReturnAirline.value &&
                returnProvider.value.trim()
            ) {
                const ruleReturn = props.airlineMarkups.find(
                    (m) =>
                        (m.service_type ?? 'flight') === 'flight' &&
                        m.flight_type === 'local' &&
                        m.airline.toLowerCase() ===
                            returnProvider.value.trim().toLowerCase(),
                );

                const getLegMarkup = (rule?: AirlineMarkup) => {
                    if (!rule) return 0;
                    if (rule.markup_type === 'percent') {
                        return (
                            ((numAmt / 2) * (Number(rule.markup_amount) || 0)) /
                            100
                        );
                    }
                    return (Number(rule.markup_amount) || 0) * numPax;
                };

                const outboundMarkup = getLegMarkup(ruleOutbound);
                const returnMarkup = getLegMarkup(ruleReturn);
                markupTotal = outboundMarkup + returnMarkup;
            } else if (ruleOutbound) {
                if (ruleOutbound.markup_type === 'percent') {
                    markupTotal =
                        (numAmt * (Number(ruleOutbound.markup_amount) || 0)) /
                        100;
                } else {
                    markupTotal =
                        (Number(ruleOutbound.markup_amount) || 0) *
                        numPax *
                        legs;
                }
            }
        } else if (flightType.value === 'international') {
            const categoryName =
                carrierCategory.value === 'full_service'
                    ? 'Full Service Carriers'
                    : 'Budget Carriers';
            const rule =
                props.airlineMarkups.find(
                    (m) =>
                        (m.service_type ?? 'flight') === 'flight' &&
                        m.flight_type === 'international' &&
                        m.airline.toLowerCase() === categoryName.toLowerCase(),
                ) ||
                props.airlineMarkups.find(
                    (m) =>
                        (m.service_type ?? 'flight') === 'flight' &&
                        m.flight_type === 'international' &&
                        m.airline.toLowerCase() ===
                            provider.value.trim().toLowerCase(),
                );

            if (rule) {
                if (rule.markup_type === 'percent') {
                    markupTotal =
                        (numAmt * (Number(rule.markup_amount) || 0)) / 100;
                } else {
                    markupTotal =
                        (Number(rule.markup_amount) || 0) * numPax * legs;
                }
            }
        }

        if (markupTotal > 0) {
            const portalCost = Math.max(0, numAmt - markupTotal);
            thirdPartyPayment.value = Math.round(portalCost * 100) / 100;
        }
    } else if (formType.value === 'ferry') {
        const rule =
            props.airlineMarkups.find(
                (m) =>
                    m.service_type === 'ferry' &&
                    m.airline.toLowerCase() ===
                        provider.value.trim().toLowerCase(),
            ) ||
            props.airlineMarkups.find(
                (m) =>
                    m.service_type === 'ferry' &&
                    m.airline.toLowerCase() === 'all ferries',
            ) ||
            props.airlineMarkups.find((m) => m.service_type === 'ferry');

        if (rule) {
            let markupTotal = 0;

            if (rule.markup_type === 'percent') {
                markupTotal =
                    (numAmt * (Number(rule.markup_amount) || 0)) / 100;
            } else {
                markupTotal = (Number(rule.markup_amount) || 0) * numPax * legs;
            }

            const portalCost = Math.max(0, numAmt - markupTotal);
            thirdPartyPayment.value = Math.round(portalCost * 100) / 100;
        }
    }
};

watch(
    [
        formType,
        provider,
        returnProvider,
        hasDifferentReturnAirline,
        flightType,
        tripType,
        carrierCategory,
        pax,
        amount,
    ],
    () => {
        calculatePortalCost();
    },
);

watch(tripType, (newTrip) => {
    if (newTrip !== 'round_trip') {
        hasDifferentReturnAirline.value = false;
        returnProvider.value = '';
    }
});

watch(hasDifferentReturnAirline, (hasDiff) => {
    if (!hasDiff) {
        returnProvider.value = '';
    }
});

watch(formType, (newType) => {
    if (
        newType === 'ferry' &&
        (!provider.value || provider.value === 'Cebu Pacific')
    ) {
        provider.value = 'All Ferries';
    } else if (newType === 'airline' && provider.value === 'All Ferries') {
        provider.value = 'Cebu Pacific';
    }
});

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

// Check if selected online assistance type requires disbursement
const selectedAssistanceHasDisbursement = computed(() => {
    if (formType.value !== 'online_assistance') {
        return true;
    }
    const selected = props.onlineAssistanceTypes.find(
        (t) => String(t.id) === String(onlineAssistanceTypeId.value),
    );
    return selected ? (selected.has_disbursement ?? true) : true;
});

// Auto-fill amount when online assistance type changes (if creating or user selects new type)
const handleOnlineAssistanceTypeChange = (value: unknown) => {
    onlineAssistanceTypeId.value = value ? String(value) : '';
    const selected = props.onlineAssistanceTypes.find(
        (t) => String(t.id) === String(value),
    );

    if (selected) {
        amount.value = selected.price;
        const nameLower = selected.name.toLowerCase();

        if (selected.has_disbursement === false) {
            thirdPartyPayment.value = 0;
            disbursementSource.value = 'none';
        } else if (
            selected.disbursement_amount !== null &&
            selected.disbursement_amount !== undefined &&
            selected.disbursement_amount !== ''
        ) {
            thirdPartyPayment.value = Number(selected.disbursement_amount);
            disbursementSource.value = 'gcash';
        } else if (nameLower.includes('police')) {
            thirdPartyPayment.value = 180;
            disbursementSource.value = 'gcash';
        } else if (nameLower.includes('nbi')) {
            thirdPartyPayment.value = 160;
            disbursementSource.value = 'gcash';
        } else {
            thirdPartyPayment.value = 0;
            disbursementSource.value = 'gcash';
        }
    }
};

// Auto-fill amount and third party fee when PSA document type changes
const applyPsaRate = (docType: string) => {
    if (!docType || props.psaRates.length === 0) {
        return;
    }

    const selected = props.psaRates.find(
        (r) => r.document_type.toLowerCase() === docType.toLowerCase(),
    );

    if (selected) {
        amount.value = selected.price;
        thirdPartyPayment.value = selected.officer_fee;
    }
};

const handlePsaDocumentTypeChange = (value: unknown) => {
    const docCode = (value ? String(value) : '') as PsaDocumentType;
    psaDocumentType.value = docCode;
    applyPsaRate(docCode);
};

// Automatically apply configured rates whenever formType or psaRates change
watch(
    formType,
    (newType) => {
        if (newType === 'online_assistance') {
            if (!props.transaction && onlineAssistanceTypeId.value) {
                handleOnlineAssistanceTypeChange(onlineAssistanceTypeId.value);
            }
        } else if (newType === 'psa') {
            disbursementSource.value = 'cash';
            if (!psaDocumentType.value && props.psaRates.length > 0) {
                psaDocumentType.value = props.psaRates[0]
                    .document_type as PsaDocumentType;
            }

            if (psaDocumentType.value) {
                applyPsaRate(psaDocumentType.value);
            }
        } else if (newType === 'translation') {
            if (!props.transaction?.disbursement_source) {
                disbursementSource.value = 'cash';
            }
        }
    },
    { immediate: true },
);

watch(
    () => props.psaRates,
    (rates) => {
        if (formType.value === 'psa' && rates && rates.length > 0) {
            if (!psaDocumentType.value) {
                psaDocumentType.value = rates[0]
                    .document_type as PsaDocumentType;
            }

            applyPsaRate(psaDocumentType.value);
        }
    },
    { immediate: true, deep: true },
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
    const configuredAirlines = props.airlineMarkups
        .filter(
            (m) =>
                (m.service_type ?? 'flight') === 'flight' &&
                m.flight_type === 'local',
        )
        .map((m) => m.airline.trim());
    const baggageAirlines = props.baggageOptions
        .map((opt) => opt.airline?.trim())
        .filter((airline): airline is string => Boolean(airline));
    const defaultAirlines = [
        'Cebu Pacific',
        'Philippine Airlines',
        'Philippines AirAsia',
        'Air Swift',
        'Sunlight Air',
    ];

    return Array.from(
        new Set([
            ...defaultAirlines,
            ...configuredAirlines,
            ...baggageAirlines,
        ]),
    );
});

const ferrySuggestions = computed(() => {
    return ['All Ferries'];
});

const activeMarkupRuleDescription = computed(() => {
    const numAmt = Number(amount.value) || 0;
    const numPax = Math.max(1, Number(pax.value) || 1);
    const legs = tripType.value === 'round_trip' ? 2 : 1;

    if (formType.value === 'airline') {
        if (flightType.value === 'local') {
            const rule = props.airlineMarkups.find(
                (m) =>
                    (m.service_type ?? 'flight') === 'flight' &&
                    m.flight_type === 'local' &&
                    m.airline.toLowerCase() ===
                        provider.value.trim().toLowerCase(),
            );

            if (
                tripType.value === 'round_trip' &&
                hasDifferentReturnAirline.value &&
                returnProvider.value.trim()
            ) {
                const ruleReturn = props.airlineMarkups.find(
                    (m) =>
                        (m.service_type ?? 'flight') === 'flight' &&
                        m.flight_type === 'local' &&
                        m.airline.toLowerCase() ===
                            returnProvider.value.trim().toLowerCase(),
                );

                const outAmt = Number(rule?.markup_amount) || 0;
                const retAmt = Number(ruleReturn?.markup_amount) || 0;
                const totalPerPax = outAmt + retAmt;
                const totalEarnings = totalPerPax * numPax;

                return `Mixed Roundtrip: Outbound (${rule?.airline || provider.value}: ₱${outAmt}) + Return (${ruleReturn?.airline || returnProvider.value}: ₱${retAmt}) = ₱${totalPerPax}/pax × ${numPax} = ₱${totalEarnings.toLocaleString()} DDN Markup`;
            }

            if (rule) {
                if (rule.markup_type === 'percent') {
                    const earned =
                        (numAmt * (Number(rule.markup_amount) || 0)) / 100;

                    return `Rule: ${rule.airline} ${rule.markup_amount}% = ₱${earned.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} DDN Markup`;
                }

                const base = Number(rule.markup_amount) || 0;

                return `Rule: ${rule.airline} (₱${base} × ${numPax} pax × ${legs} leg${legs > 1 ? 's' : ''}) = ₱${(base * numPax * legs).toLocaleString()} DDN Markup`;
            }
        } else if (flightType.value === 'international') {
            const categoryName =
                carrierCategory.value === 'full_service'
                    ? 'Full Service Carriers'
                    : 'Budget Carriers';
            const rule =
                props.airlineMarkups.find(
                    (m) =>
                        (m.service_type ?? 'flight') === 'flight' &&
                        m.flight_type === 'international' &&
                        m.airline.toLowerCase() === categoryName.toLowerCase(),
                ) ||
                props.airlineMarkups.find(
                    (m) =>
                        (m.service_type ?? 'flight') === 'flight' &&
                        m.flight_type === 'international' &&
                        m.airline.toLowerCase() ===
                            provider.value.trim().toLowerCase(),
                );

            if (rule) {
                if (rule.markup_type === 'percent') {
                    const earned =
                        (numAmt * (Number(rule.markup_amount) || 0)) / 100;

                    return `Rule: ${rule.airline} ${rule.markup_amount}% = ₱${earned.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} DDN Markup`;
                }

                const base = Number(rule.markup_amount) || 0;

                return `Rule: ${rule.airline} (₱${base} × ${numPax} pax × ${legs} leg${legs > 1 ? 's' : ''}) = ₱${(base * numPax * legs).toLocaleString()} DDN Markup`;
            }
        }
    } else if (formType.value === 'ferry') {
        const rule =
            props.airlineMarkups.find(
                (m) =>
                    m.service_type === 'ferry' &&
                    m.airline.toLowerCase() ===
                        provider.value.trim().toLowerCase(),
            ) ||
            props.airlineMarkups.find(
                (m) =>
                    m.service_type === 'ferry' &&
                    m.airline.toLowerCase() === 'all ferries',
            ) ||
            props.airlineMarkups.find((m) => m.service_type === 'ferry');

        if (rule) {
            if (rule.markup_type === 'percent') {
                const earned =
                    (numAmt * (Number(rule.markup_amount) || 0)) / 100;

                return `Rule: ${rule.airline} ${rule.markup_amount}% = ₱${earned.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} DDN Markup`;
            }

            const base = Number(rule.markup_amount) || 0;

            return `Rule: ${rule.airline} (₱${base} × ${numPax} pax × ${legs} leg${legs > 1 ? 's' : ''}) = ₱${(base * numPax * legs).toLocaleString()} DDN Markup`;
        }
    }

    return null;
});

// Client-side computed previews
const numAmount = computed(() => Number(amount.value) || 0);
const numThirdParty = computed(() => Number(thirdPartyPayment.value) || 0);

const computedTranslationOrPsaDdnAmount = computed(() => {
    return Math.max(0, numAmount.value - numThirdParty.value);
});

const computedOnlineNetMargin = computed(() => {
    return Math.max(0, numAmount.value - numThirdParty.value);
});

const computedOnlineDdnShare = computed(() => {
    const ddnRate = props.ddnSplitConfig?.ddn ?? 0.6;

    return computedOnlineNetMargin.value * ddnRate;
});

const computedOnlineEmployeeShare = computed(() => {
    const empRate = props.ddnSplitConfig?.employee ?? 0.4;

    return computedOnlineNetMargin.value * empRate;
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
        <input
            v-if="customerId"
            type="hidden"
            name="customer_id"
            :value="customerId"
        />
        <input type="hidden" name="payment_status" :value="paymentStatus" />
        <input
            type="hidden"
            name="amount_paid"
            :value="
                paymentStatus === 'paid'
                    ? amount || 0
                    : paymentStatus === 'unpaid'
                      ? 0
                      : amountPaid || 0
            "
        />
        <input
            v-if="formType === 'airline'"
            type="hidden"
            name="flight_type"
            :value="flightType"
        />
        <input
            v-if="formType === 'airline' || formType === 'ferry'"
            type="hidden"
            name="trip_type"
            :value="tripType"
        />
        <input
            v-if="formType === 'airline' && flightType === 'international'"
            type="hidden"
            name="carrier_category"
            :value="carrierCategory"
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
            v-if="formType === 'psa'"
            type="hidden"
            name="amount"
            :value="amount"
        />
        <input
            v-if="formType === 'psa'"
            type="hidden"
            name="third_party_payment"
            :value="thirdPartyPayment"
        />
        <input
            v-if="formType === 'online_assistance'"
            type="hidden"
            name="online_assistance_type_id"
            :value="onlineAssistanceTypeId"
        />
        <input
            v-if="
                formType === 'online_assistance' ||
                formType === 'translation' ||
                formType === 'psa'
            "
            type="hidden"
            name="disbursement_source"
            :value="
                formType === 'psa'
                    ? 'cash'
                    : formType === 'online_assistance' &&
                        !selectedAssistanceHasDisbursement
                      ? 'none'
                      : disbursementSource
            "
        />

        <input
            v-if="formType === 'remittance'"
            type="hidden"
            name="service_details"
            :value="remittanceDirection"
        />
        <input
            v-if="formType === 'remittance'"
            type="hidden"
            name="third_party_payment"
            :value="remittanceFee"
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
                            Airline Ticket / Flight
                        </SelectItem>
                        <SelectItem value="ferry">
                            Ferry Ticket / Booking
                        </SelectItem>
                        <SelectItem value="translation">
                            Document Translation
                        </SelectItem>
                        <SelectItem value="psa"> PSA Assistance </SelectItem>
                        <SelectItem value="online_assistance">
                            Online Assistance
                        </SelectItem>
                        <SelectItem value="remittance">
                            GCash Remittance / Cash Exchange
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
                        v-for="c in recurringCustomers &&
                        recurringCustomers.length
                            ? recurringCustomers
                            : initialCustomers"
                        :key="c.id"
                        :value="c.name"
                    >
                        {{
                            c.contact_number
                                ? `${c.name} (${c.contact_number})`
                                : c.name
                        }}
                    </option>
                </datalist>
                <InputError :message="errors.customer_name" />
            </div>

            <!-- 4. Contact Number (Autofilled for recurring customer or entered manually) -->
            <div class="grid gap-2">
                <Label
                    for="customer_contact"
                    class="flex items-center justify-between"
                >
                    <span>Contact Number</span>
                    <span class="text-xs font-normal text-muted-foreground"
                        >Optional</span
                    >
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
            <div
                v-if="formType === 'remittance'"
                class="flex items-center justify-between rounded-lg border bg-blue-50/50 p-3.5 text-xs md:col-span-2 dark:bg-blue-950/20"
            >
                <div class="flex items-center gap-2">
                    <Smartphone
                        class="size-4 shrink-0 text-blue-600 dark:text-blue-400"
                    />
                    <span>
                        <strong class="font-semibold text-foreground"
                            >Remittance Flow:</strong
                        >
                        {{
                            remittanceDirection === 'GCASH_CASH_OUT'
                                ? 'Customer GCash Transfer (+GCash) & Drawer Cash Dispensed (-Cash)'
                                : 'Customer Physical Cash (+Cash) & GCash Wallet Sent (-GCash)'
                        }}
                    </span>
                </div>
                <span
                    class="shrink-0 rounded bg-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300"
                >
                    Settles Immediately (Paid)
                </span>
            </div>

            <div
                v-if="formType !== 'remittance'"
                class="grid gap-2 md:col-span-2"
            >
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
                    <Label for="amount_paid"
                        >Downpayment Paid Today (PHP)</Label
                    >
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
                    <span
                        class="font-medium text-amber-800 dark:text-amber-300"
                    >
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
            <div
                v-if="formType !== 'remittance'"
                class="grid gap-2 md:col-span-2"
            >
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
            <!-- AIRLINE TICKETING -->
            <template v-if="formType === 'airline'">
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
                            <SelectItem value="local"
                                >Domestic (Local)</SelectItem
                            >
                            <SelectItem value="international"
                                >International</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.flight_type" />
                </div>

                <!-- Carrier Category for International Flights -->
                <div v-if="flightType === 'international'" class="grid gap-2">
                    <Label for="carrier_category_select"
                        >Carrier Category</Label
                    >
                    <Select v-model="carrierCategory">
                        <SelectTrigger
                            id="carrier_category_select"
                            class="w-full"
                        >
                            <SelectValue
                                placeholder="Select carrier category"
                            />
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
                            tripType === 'round_trip' &&
                            hasDifferentReturnAirline
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

                <!-- Different Airline for Return (Round-trip only) -->
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
                        Different airline for return flight (e.g. Outbound PAL,
                        Return Cebu Pacific)
                    </Label>
                </div>

                <!-- Return Airline Input -->
                <div
                    v-if="
                        tripType === 'round_trip' && hasDifferentReturnAirline
                    "
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
                        Markup will be calculated independently for each leg
                        (Outbound + Return) based on each carrier's rule.
                    </p>
                    <InputError :message="errors.return_provider" />
                </div>

                <div
                    class="grid gap-2"
                    :class="
                        flightType === 'international' ? '' : 'md:col-span-2'
                    "
                >
                    <Label for="airline_service_details"
                        >Flight Route / Details</Label
                    >
                    <Input
                        id="airline_service_details"
                        name="service_details"
                        v-model="serviceDetails"
                        placeholder="e.g. Manila to Cebu (MNL-CEB)"
                    />
                    <InputError :message="errors.service_details" />
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
                    <div class="flex items-center justify-between">
                        <div
                            class="flex items-center gap-2 text-sm font-semibold text-primary"
                        >
                            <Calculator class="size-4" />
                            <span>Flight Ticketing Margin Preview</span>
                        </div>
                        <span
                            v-if="activeMarkupRuleDescription"
                            class="text-xs font-medium text-muted-foreground"
                        >
                            {{ activeMarkupRuleDescription }}
                        </span>
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

            <!-- FERRY TICKETING -->
            <template v-if="formType === 'ferry'">
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
                    <Label for="ferry_provider"
                        >Ferry Provider / Category</Label
                    >
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
                    <Label for="ferry_service_details"
                        >Route / Trip Details</Label
                    >
                    <Input
                        id="ferry_service_details"
                        name="service_details"
                        v-model="serviceDetails"
                        placeholder="e.g. Batangas to Calapan"
                    />
                    <InputError :message="errors.service_details" />
                </div>

                <div class="grid gap-2">
                    <Label for="ferry_amount"
                        >Customer Ticket Price (PHP)</Label
                    >
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
                        <Label for="ferry_third_party_payment"
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
                        Net ticket fare deducted from portal deposit.
                        Auto-calculates with fixed ₱200 markup per pax/leg.
                    </p>
                    <InputError :message="errors.third_party_payment" />
                </div>

                <!-- Ferry Calculation Preview -->
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

                <!-- Disbursement Source for Translation -->
                <div class="grid gap-2 md:col-span-2">
                    <Label
                        for="translation_disbursement_source"
                        class="flex items-center gap-1.5 font-medium"
                    >
                        <Wallet class="size-4 text-muted-foreground" />
                        <span>Disbursement Source (Payment to Translator)</span>
                    </Label>
                    <Select v-model="disbursementSource">
                        <SelectTrigger
                            id="translation_disbursement_source"
                            class="w-full"
                        >
                            <SelectValue
                                placeholder="Select disbursement source"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="cash">
                                Cash Drawer (Paid in cash from physical drawer)
                            </SelectItem>
                            <SelectItem value="gcash">
                                GCash Wallet (Paid via GCash — recorded in GCash
                                Ledger)
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-xs text-muted-foreground">
                        Specify where the translator fee is disbursed from. If
                        GCash is selected, it will automatically record a
                        disbursement outflow in your live GCash Ledger.
                    </p>
                    <InputError :message="errors.disbursement_source" />
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
                    <Select
                        :model-value="psaDocumentType"
                        @update:model-value="handlePsaDocumentTypeChange"
                    >
                        <SelectTrigger id="psa_doc_select" class="w-full">
                            <SelectValue
                                placeholder="Select PSA Document Type"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <template v-if="props.psaRates.length > 0">
                                <SelectItem
                                    v-for="rate in props.psaRates"
                                    :key="rate.id"
                                    :value="rate.document_type"
                                >
                                    {{ rate.document_type }} —
                                    {{ rate.name }} ({{
                                        formatPhp(rate.price)
                                    }})
                                </SelectItem>
                            </template>
                            <template v-else>
                                <SelectItem
                                    v-for="docType in psaDocumentTypes"
                                    :key="docType"
                                    :value="docType"
                                >
                                    {{
                                        psaDocumentTypeLabels[docType] ||
                                        docType
                                    }}
                                </SelectItem>
                            </template>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.psa_document_type" />
                </div>

                <!-- PSA Margin & Net Breakdown Preview (Price is automated from configuration) -->
                <div class="rounded-lg border bg-muted/40 p-4 md:col-span-2">
                    <div
                        class="flex items-center justify-between gap-2 text-sm font-semibold text-primary"
                    >
                        <div class="flex items-center gap-2">
                            <Calculator class="size-4" />
                            <span>Configured PSA Fixed Price & Breakdown</span>
                        </div>
                        <span class="text-xs font-normal text-muted-foreground">
                            Automatically set from configuration
                        </span>
                    </div>
                    <div
                        class="mt-3 grid grid-cols-3 gap-2 text-center text-xs"
                    >
                        <div class="rounded border bg-background p-2">
                            <span class="text-muted-foreground"
                                >Customer Price</span
                            >
                            <p class="font-bold text-foreground">
                                {{ formatPhp(numAmount) }}
                            </p>
                        </div>
                        <div class="rounded border bg-background p-2">
                            <span class="text-muted-foreground"
                                >PSA Officer Fee</span
                            >
                            <p
                                class="font-semibold text-blue-600 dark:text-blue-400"
                            >
                                -{{ formatPhp(numThirdParty) }}
                            </p>
                        </div>
                        <div class="rounded border bg-background p-2">
                            <span class="text-muted-foreground"
                                >Net DDN Income</span
                            >
                            <p
                                class="font-semibold text-emerald-600 dark:text-emerald-400"
                            >
                                {{
                                    formatPhp(computedTranslationOrPsaDdnAmount)
                                }}
                            </p>
                        </div>
                    </div>
                    <p
                        class="mt-2 text-center text-[11px] text-muted-foreground"
                    >
                        Customer price and PSA officer payment are fixed
                        according to branch configuration.
                    </p>
                    <InputError :message="errors.amount" />
                    <InputError :message="errors.third_party_payment" />
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
                                <template
                                    v-if="
                                        typeOption.has_disbursement &&
                                        typeOption.disbursement_amount
                                    "
                                >
                                    — Fee:
                                    {{
                                        formatPhp(
                                            typeOption.disbursement_amount,
                                        )
                                    }}
                                </template>
                                <template
                                    v-else-if="
                                        typeOption.has_disbursement === false
                                    "
                                >
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

                <!-- Government Fee / GCash Disbursement Input -->
                <div
                    v-if="selectedAssistanceHasDisbursement"
                    class="grid gap-2"
                >
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
                        Amount to be automatically deducted from your live GCash
                        Ledger upon submission.
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
                        No government fee disbursement required. 100% of
                        customer payment is retained for agency and employee
                        split.
                    </p>
                </div>

                <!-- Net Margin & 60/40 Split Calculation Preview -->
                <div class="rounded-lg border bg-muted/40 p-4 md:col-span-2">
                    <div
                        class="flex items-center justify-between gap-2 text-sm font-semibold text-primary"
                    >
                        <div class="flex items-center gap-2">
                            <Calculator class="size-4" />
                            <span
                                >Net Service Margin & 60/40 Revenue Split</span
                            >
                        </div>
                        <span class="text-xs font-normal text-muted-foreground">
                            Office 60% / Employee 40%
                        </span>
                    </div>

                    <div
                        v-if="
                            selectedAssistanceHasDisbursement &&
                            numThirdParty > 0
                        "
                        class="mt-3 grid grid-cols-3 gap-2 text-center text-xs"
                    >
                        <div class="rounded border bg-background p-2">
                            <span class="text-muted-foreground"
                                >Customer Paid</span
                            >
                            <p class="font-bold text-foreground">
                                {{ formatPhp(numAmount) }}
                            </p>
                        </div>
                        <div class="rounded border bg-background p-2">
                            <span class="text-muted-foreground"
                                >Gov / GCash Fee</span
                            >
                            <p
                                class="font-semibold text-blue-600 dark:text-blue-400"
                            >
                                -{{ formatPhp(numThirdParty) }}
                            </p>
                        </div>
                        <div class="rounded border bg-background p-2">
                            <span class="text-muted-foreground"
                                >Net Margin</span
                            >
                            <p
                                class="font-semibold text-emerald-600 dark:text-emerald-400"
                            >
                                {{ formatPhp(computedOnlineNetMargin) }}
                            </p>
                        </div>
                    </div>
                    <div
                        v-else
                        class="mt-3 grid grid-cols-2 gap-2 text-center text-xs"
                    >
                        <div class="rounded border bg-background p-2">
                            <span class="text-muted-foreground"
                                >Customer Paid</span
                            >
                            <p class="font-bold text-foreground">
                                {{ formatPhp(numAmount) }}
                            </p>
                        </div>
                        <div class="rounded border bg-background p-2">
                            <span class="text-muted-foreground"
                                >Net Service Margin</span
                            >
                            <p
                                class="font-semibold text-emerald-600 dark:text-emerald-400"
                            >
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
                            <p class="text-xs text-muted-foreground">
                                Employee Cut (40%)
                            </p>
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
                            selectedAssistanceHasDisbursement &&
                            numThirdParty > 0
                                ? 'Shares are computed from net margin (Customer Payment minus Government Fee).'
                                : '100% of customer payment is split 60% Office / 40% Employee.'
                        }}</span>
                    </p>
                </div>
            </template>

            <!-- REMITTANCE / CASH EXCHANGE -->
            <template v-if="formType === 'remittance'">
                <!-- Direction selector -->
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
                                Customer sends GCash (+GCash) → Staff dispenses
                                physical cash (-Drawer Cash)
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
                                Customer gives physical cash (+Drawer Cash) →
                                Staff sends from GCash wallet (-GCash)
                            </p>
                        </button>
                    </div>
                </div>

                <!-- Principal Amount & Fee -->
                <div class="grid gap-2">
                    <Label for="remittance_amount"
                        >Remittance Principal Amount (PHP)</Label
                    >
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

                <!-- Dual Cash Drawer & GCash Wallet Flow Preview Card -->
                <div
                    class="space-y-3 rounded-lg border bg-muted/40 p-4 md:col-span-2"
                >
                    <div
                        class="flex items-center gap-2 text-sm font-semibold text-primary"
                    >
                        <Calculator class="size-4" />
                        <span>Simultaneous Dual Movement Preview</span>
                    </div>

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
                                {{
                                    remittanceDirection === 'GCASH_CASH_OUT'
                                        ? '-'
                                        : '+'
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
                                {{
                                    remittanceDirection === 'GCASH_CASH_OUT'
                                        ? '+'
                                        : '-'
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
