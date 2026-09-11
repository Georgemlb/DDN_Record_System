<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertCircle,
    FileText,
    Headphones,
    Luggage,
    Pencil,
    Plus,
    Tag,
    Ticket,
    Trash,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import GcashIcon from '@/components/icons/GcashIcon.vue';
import { formatPhp } from '@/lib/transactionFormat';
import type { ExpenseCategory } from '@/types/expense';
import type {
    AirlineMarkup,
    BaggageOption,
    OnlineAssistanceType,
    PsaRate,
} from '@/types/transaction';

const props = defineProps<{
    baggageOptions: BaggageOption[];
    airlineMarkups: AirlineMarkup[];
    onlineAssistanceTypes: OnlineAssistanceType[];
    expenseCategories: ExpenseCategory[];
    psaRates: PsaRate[];
    currentTab:
        | 'baggage'
        | 'online-assistance'
        | 'expense-categories'
        | 'airline-markups'
        | 'psa-rates';
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Configurations',
                href: '/configurations',
            },
        ],
    },
});

// --------------------------------------------------------
// Tabs Navigation
// --------------------------------------------------------
type TabKey =
    | 'baggage'
    | 'airline-markups'
    | 'online-assistance'
    | 'expense-categories'
    | 'psa-rates';

const activeTab = ref<TabKey>(props.currentTab || 'baggage');

const tabs = [
    {
        key: 'baggage' as TabKey,
        label: 'Baggage Options',
        icon: Luggage,
        count: () => props.baggageOptions.length,
    },
    {
        key: 'airline-markups' as TabKey,
        label: 'Ticketing Markups',
        icon: Ticket,
        count: () => props.airlineMarkups.length,
    },
    {
        key: 'online-assistance' as TabKey,
        label: 'Online Assistance',
        icon: Headphones,
        count: () => props.onlineAssistanceTypes.length,
    },
    {
        key: 'psa-rates' as TabKey,
        label: 'PSA Rates',
        icon: FileText,
        count: () => props.psaRates.length,
    },
    {
        key: 'expense-categories' as TabKey,
        label: 'Expense Categories',
        icon: Tag,
        count: () => props.expenseCategories.length,
    },
];

const switchTab = (key: TabKey) => {
    activeTab.value = key;
    router.get(
        '/configurations',
        { tab: key },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

watch(
    () => props.currentTab,
    (newTab) => {
        if (newTab && newTab !== activeTab.value) {
            activeTab.value = newTab;
        }
    },
);

// --------------------------------------------------------
// Reusable Delete Confirmation Modal
// --------------------------------------------------------
const isDeleteModalOpen = ref(false);
const isDeleting = ref(false);
const itemToDelete = ref<{
    id: number;
    title: string;
    endpoint: string;
    description: string;
} | null>(null);

const confirmDelete = (
    id: number,
    title: string,
    endpoint: string,
    description: string,
) => {
    itemToDelete.value = { id, title, endpoint, description };
    isDeleteModalOpen.value = true;
};

const executeDelete = () => {
    if (!itemToDelete.value) {
        return;
    }

    isDeleting.value = true;

    router.delete(`${itemToDelete.value.endpoint}/${itemToDelete.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            isDeleteModalOpen.value = false;
            itemToDelete.value = null;
        },
    });
};

// --------------------------------------------------------
// Baggage Modal & Form
// --------------------------------------------------------
const isBaggageModalOpen = ref(false);
const editingBaggageId = ref<number | null>(null);

const baggageForm = useForm({
    airline: '',
    label: '',
    weight_kg: 20,
    sort_order: 0,
    is_active: true,
});

const hasDuplicateBaggageWarning = computed(() => {
    const airline = baggageForm.airline.trim().toLowerCase();
    const weight = Number(baggageForm.weight_kg);

    return props.baggageOptions.some((opt) => {
        if (editingBaggageId.value && opt.id === editingBaggageId.value) {
            return false;
        }

        const optAirline = (opt.airline ?? '').trim().toLowerCase();

        return optAirline === airline && opt.weight_kg === weight;
    });
});

const openCreateBaggage = () => {
    editingBaggageId.value = null;
    baggageForm.reset();
    baggageForm.clearErrors();
    isBaggageModalOpen.value = true;
};

const openEditBaggage = (option: BaggageOption) => {
    editingBaggageId.value = option.id;
    baggageForm.clearErrors();
    baggageForm.airline = option.airline ?? '';
    baggageForm.label = option.label;
    baggageForm.weight_kg = option.weight_kg;
    baggageForm.sort_order = option.sort_order ?? 0;
    baggageForm.is_active = option.is_active ?? true;
    isBaggageModalOpen.value = true;
};

const submitBaggage = () => {
    if (editingBaggageId.value) {
        baggageForm.put(
            `/configurations/baggage-options/${editingBaggageId.value}`,
            {
                onSuccess: () => {
                    isBaggageModalOpen.value = false;
                },
            },
        );
    } else {
        baggageForm.post('/configurations/baggage-options', {
            onSuccess: () => {
                isBaggageModalOpen.value = false;
            },
        });
    }
};

// --------------------------------------------------------
// Online Assistance Modal & Form
// --------------------------------------------------------
const isAssistanceModalOpen = ref(false);
const editingAssistanceId = ref<number | null>(null);

const assistanceForm = useForm({
    name: '',
    price: 0,
    has_disbursement: false,
    disbursement_amount: null as number | null,
    sort_order: 0,
    is_active: true,
});

watch(
    () => assistanceForm.has_disbursement,
    (checked) => {
        if (!checked) {
            assistanceForm.disbursement_amount = null;
        }
    },
);

const openCreateAssistance = () => {
    editingAssistanceId.value = null;
    assistanceForm.reset();
    assistanceForm.has_disbursement = false;
    assistanceForm.disbursement_amount = null;
    assistanceForm.clearErrors();
    isAssistanceModalOpen.value = true;
};

const openEditAssistance = (item: OnlineAssistanceType) => {
    editingAssistanceId.value = item.id;
    assistanceForm.clearErrors();
    assistanceForm.name = item.name;
    assistanceForm.price = Number(item.price);
    const hasDisb = Boolean(item.has_disbursement);
    assistanceForm.has_disbursement = hasDisb;
    assistanceForm.disbursement_amount = hasDisb
        ? (item.disbursement_amount !== null ? Number(item.disbursement_amount) : null)
        : null;
    assistanceForm.sort_order = item.sort_order ?? 0;
    assistanceForm.is_active = item.is_active ?? true;
    isAssistanceModalOpen.value = true;
};

const modalAssistanceMargin = computed(() => {
    const price = Number(assistanceForm.price) || 0;
    const hasDisb = Boolean(assistanceForm.has_disbursement);
    const disb = hasDisb
        ? Number(assistanceForm.disbursement_amount) || 0
        : 0;
    return Math.max(0, price - disb);
});

function getAssistanceNetShares(item: OnlineAssistanceType) {
    const price = Number(item.price) || 0;
    const hasDisb = Boolean(item.has_disbursement);
    const disb = hasDisb ? Number(item.disbursement_amount || 0) : 0;
    const margin = Math.max(0, price - disb);
    return {
        margin,
        ddn: margin * 0.6,
        employee: margin * 0.4,
    };
}

const submitAssistance = () => {
    if (editingAssistanceId.value) {
        assistanceForm.put(
            `/configurations/online-assistance-types/${editingAssistanceId.value}`,
            {
                onSuccess: () => {
                    isAssistanceModalOpen.value = false;
                },
            },
        );
    } else {
        assistanceForm.post('/configurations/online-assistance-types', {
            onSuccess: () => {
                isAssistanceModalOpen.value = false;
            },
        });
    }
};

// --------------------------------------------------------
// Ticketing Markup Modal & Form (Flight & Ferry)
// --------------------------------------------------------
const isMarkupModalOpen = ref(false);
const editingMarkupId = ref<number | null>(null);

const markupCategoryFilter = ref<
    'all' | 'domestic' | 'international' | 'ferry'
>('all');

const domesticMarkupsCount = computed(
    () =>
        props.airlineMarkups.filter(
            (m) =>
                (m.service_type ?? 'flight') === 'flight' &&
                m.flight_type === 'local',
        ).length,
);
const internationalMarkupsCount = computed(
    () =>
        props.airlineMarkups.filter(
            (m) =>
                (m.service_type ?? 'flight') === 'flight' &&
                m.flight_type === 'international',
        ).length,
);
const ferryMarkupsCount = computed(
    () => props.airlineMarkups.filter((m) => m.service_type === 'ferry').length,
);

const filteredMarkups = computed(() => {
    if (markupCategoryFilter.value === 'domestic') {
        return props.airlineMarkups.filter(
            (m) =>
                (m.service_type ?? 'flight') === 'flight' &&
                m.flight_type === 'local',
        );
    }

    if (markupCategoryFilter.value === 'international') {
        return props.airlineMarkups.filter(
            (m) =>
                (m.service_type ?? 'flight') === 'flight' &&
                m.flight_type === 'international',
        );
    }

    if (markupCategoryFilter.value === 'ferry') {
        return props.airlineMarkups.filter((m) => m.service_type === 'ferry');
    }

    return props.airlineMarkups;
});

const markupForm = useForm({
    service_type: 'flight' as 'flight' | 'ferry',
    airline: '',
    flight_type: 'local' as 'local' | 'international' | null,
    markup_type: 'fixed' as 'fixed' | 'percent',
    markup_amount: 0,
    is_active: true,
});

const openCreateMarkup = (
    defaultService: 'flight' | 'ferry' = 'flight',
    defaultFlightType: 'local' | 'international' = 'local',
) => {
    editingMarkupId.value = null;
    markupForm.reset();
    markupForm.clearErrors();
    markupForm.service_type = defaultService;
    markupForm.airline = defaultService === 'ferry' ? 'All Ferries' : '';
    markupForm.flight_type =
        defaultService === 'ferry' ? null : defaultFlightType;
    markupForm.markup_type = 'fixed';
    markupForm.markup_amount = 0;
    markupForm.is_active = true;
    isMarkupModalOpen.value = true;
};

const openEditMarkup = (markup: AirlineMarkup) => {
    editingMarkupId.value = markup.id;
    markupForm.clearErrors();
    markupForm.service_type = markup.service_type ?? 'flight';
    markupForm.airline = markup.airline;
    markupForm.flight_type =
        markup.flight_type ??
        (markup.service_type === 'ferry' ? null : 'local');
    markupForm.markup_type = markup.markup_type ?? 'fixed';
    markupForm.markup_amount = Number(markup.markup_amount);
    markupForm.is_active = markup.is_active ?? true;
    isMarkupModalOpen.value = true;
};

const submitMarkup = () => {
    if (markupForm.service_type === 'ferry') {
        markupForm.flight_type = null;
    }

    if (editingMarkupId.value) {
        markupForm.put(
            `/configurations/airline-markups/${editingMarkupId.value}`,
            {
                onSuccess: () => {
                    isMarkupModalOpen.value = false;
                },
            },
        );
    } else {
        markupForm.post('/configurations/airline-markups', {
            onSuccess: () => {
                isMarkupModalOpen.value = false;
            },
        });
    }
};

// --------------------------------------------------------
// Expense Category Modal & Form
// --------------------------------------------------------
const isCategoryModalOpen = ref(false);
const editingCategoryId = ref<number | null>(null);

const categoryForm = useForm({
    name: '',
    description: '',
    sort_order: 0,
    is_active: true,
});

const openCreateCategory = () => {
    editingCategoryId.value = null;
    categoryForm.reset();
    categoryForm.clearErrors();
    isCategoryModalOpen.value = true;
};

const openEditCategory = (cat: ExpenseCategory) => {
    editingCategoryId.value = cat.id;
    categoryForm.clearErrors();
    categoryForm.name = cat.name;
    categoryForm.description = cat.description ?? '';
    categoryForm.sort_order = cat.sort_order ?? 0;
    categoryForm.is_active = cat.is_active ?? true;
    isCategoryModalOpen.value = true;
};

const submitCategory = () => {
    if (editingCategoryId.value) {
        categoryForm.put(
            `/configurations/expense-categories/${editingCategoryId.value}`,
            {
                onSuccess: () => {
                    isCategoryModalOpen.value = false;
                },
            },
        );
    } else {
        categoryForm.post('/configurations/expense-categories', {
            onSuccess: () => {
                isCategoryModalOpen.value = false;
            },
        });
    }
};

// --------------------------------------------------------
// PSA Rate Modal & Form
// --------------------------------------------------------
const isPsaModalOpen = ref(false);
const editingPsaId = ref<number | null>(null);

const psaForm = useForm({
    document_type: '',
    name: '',
    price: 0,
    officer_fee: 0,
    sort_order: 0,
    is_active: true,
});

const computedPsaNetIncome = computed(() => {
    const p = Number(psaForm.price) || 0;
    const f = Number(psaForm.officer_fee) || 0;

    return Math.max(0, Math.round((p - f) * 100) / 100);
});

const openCreatePsa = () => {
    editingPsaId.value = null;
    psaForm.reset();
    psaForm.clearErrors();
    isPsaModalOpen.value = true;
};

const openEditPsa = (rate: PsaRate) => {
    editingPsaId.value = rate.id;
    psaForm.clearErrors();
    psaForm.document_type = rate.document_type;
    psaForm.name = rate.name;
    psaForm.price = Number(rate.price);
    psaForm.officer_fee = Number(rate.officer_fee);
    psaForm.sort_order = rate.sort_order ?? 0;
    psaForm.is_active = rate.is_active ?? true;
    isPsaModalOpen.value = true;
};

const submitPsa = () => {
    if (editingPsaId.value) {
        psaForm.put(`/configurations/psa-rates/${editingPsaId.value}`, {
            onSuccess: () => {
                isPsaModalOpen.value = false;
            },
        });
    } else {
        psaForm.post('/configurations/psa-rates', {
            onSuccess: () => {
                isPsaModalOpen.value = false;
            },
        });
    }
};
</script>

<template>
    <Head title="Configurations" />

    <main class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
        <!-- Top Title & Description -->
        <section class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-primary">
                    Master Data & Settings
                </p>
                <h1
                    class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl"
                >
                    Configurations
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Manage master dropdown options for transactions, operational
                    services, and expense tracking.
                </p>
            </div>
        </section>

        <!-- Segmented Tab Navigation -->
        <section class="flex border-b border-border">
            <div class="inline-flex gap-2 pb-px">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    @click="switchTab(tab.key)"
                    :class="[
                        'flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
                        activeTab === tab.key
                            ? 'border-primary text-primary'
                            : 'border-transparent text-muted-foreground hover:border-muted-foreground/30 hover:text-foreground',
                    ]"
                >
                    <component :is="tab.icon" class="size-4" />
                    <span>{{ tab.label }}</span>
                    <Badge
                        variant="secondary"
                        class="py-0.2 ml-1 px-1.5 text-xs font-normal"
                    >
                        {{ tab.count() }}
                    </Badge>
                </button>
            </div>
        </section>

        <!-- ============================================== -->
        <!-- TAB 1: BAGGAGE OPTIONS                         -->
        <!-- ============================================== -->
        <section v-if="activeTab === 'baggage'" class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold tracking-tight">
                        Baggage Options
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Configure weight tiers and default pricing per airline
                        or universal.
                    </p>
                </div>
                <Button @click="openCreateBaggage">
                    <Plus class="mr-1.5 size-4" /> Add Option
                </Button>
            </div>

            <Card>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b bg-muted/50 text-left text-muted-foreground"
                            >
                                <th class="w-16 p-4 text-center font-medium">
                                    Order
                                </th>
                                <th class="p-4 font-medium">Airline</th>
                                <th class="p-4 font-medium">Label</th>
                                <th class="p-4 text-right font-medium">
                                    Weight
                                </th>
                                <th class="p-4 text-center font-medium">
                                    Status
                                </th>
                                <th class="p-4 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="baggageOptions.length === 0">
                                <td
                                    colspan="7"
                                    class="p-8 text-center text-muted-foreground"
                                >
                                    No baggage options configured yet.
                                </td>
                            </tr>
                            <tr
                                v-for="option in baggageOptions"
                                :key="option.id"
                                class="border-b transition-colors hover:bg-muted/50"
                            >
                                <td
                                    class="p-4 text-center align-middle font-mono text-xs text-muted-foreground"
                                >
                                    #{{ option.sort_order ?? 0 }}
                                </td>
                                <td class="p-4 align-middle">
                                    <span
                                        v-if="option.airline"
                                        class="font-medium text-foreground"
                                    >
                                        {{ option.airline }}
                                    </span>
                                    <span
                                        v-else
                                        class="text-muted-foreground italic"
                                    >
                                        Universal (All Airlines)
                                    </span>
                                </td>
                                <td class="p-4 align-middle font-medium">
                                    {{ option.label }}
                                </td>
                                <td class="p-4 text-right align-middle">
                                    {{ option.weight_kg }} KG
                                </td>
                                <td class="p-4 text-center align-middle">
                                    <Badge
                                        :variant="
                                            option.is_active
                                                ? 'default'
                                                : 'secondary'
                                        "
                                    >
                                        {{
                                            option.is_active
                                                ? 'Active'
                                                : 'Inactive'
                                        }}
                                    </Badge>
                                </td>
                                <td class="p-4 text-right align-middle">
                                    <div class="flex justify-end gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            title="Edit"
                                            @click="openEditBaggage(option)"
                                        >
                                            <Pencil class="size-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            title="Delete"
                                            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                            @click="
                                                confirmDelete(
                                                    option.id,
                                                    option.label,
                                                    '/configurations/baggage-options',
                                                    'This baggage option will be permanently removed if it is not associated with existing transactions.',
                                                )
                                            "
                                        >
                                            <Trash class="size-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </section>

        <!-- ============================================== -->
        <!-- TAB 1.5: TICKETING MARKUPS (FLIGHT & FERRY)    -->
        <!-- ============================================== -->
        <section v-if="activeTab === 'airline-markups'" class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold tracking-tight">
                        Ticketing Markups
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Configure pricing markups for domestic flights,
                        international flights, and ferries.
                    </p>
                </div>
                <Button @click="openCreateMarkup('flight', 'local')">
                    <Plus class="mr-1.5 size-4" /> Add Markup
                </Button>
            </div>

            <!-- Category Filter Pills -->
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    @click="markupCategoryFilter = 'all'"
                    class="rounded-full px-3 py-1.5 text-xs font-medium transition-all"
                    :class="
                        markupCategoryFilter === 'all'
                            ? 'bg-primary text-primary-foreground shadow-xs'
                            : 'bg-muted text-muted-foreground hover:bg-muted/80'
                    "
                >
                    All ({{ airlineMarkups.length }})
                </button>
                <button
                    type="button"
                    @click="markupCategoryFilter = 'domestic'"
                    class="rounded-full px-3 py-1.5 text-xs font-medium transition-all"
                    :class="
                        markupCategoryFilter === 'domestic'
                            ? 'bg-primary text-primary-foreground shadow-xs'
                            : 'bg-muted text-muted-foreground hover:bg-muted/80'
                    "
                >
                    Domestic Flights ({{ domesticMarkupsCount }})
                </button>
                <button
                    type="button"
                    @click="markupCategoryFilter = 'international'"
                    class="rounded-full px-3 py-1.5 text-xs font-medium transition-all"
                    :class="
                        markupCategoryFilter === 'international'
                            ? 'bg-primary text-primary-foreground shadow-xs'
                            : 'bg-muted text-muted-foreground hover:bg-muted/80'
                    "
                >
                    International Flights ({{ internationalMarkupsCount }})
                </button>
                <button
                    type="button"
                    @click="markupCategoryFilter = 'ferry'"
                    class="rounded-full px-3 py-1.5 text-xs font-medium transition-all"
                    :class="
                        markupCategoryFilter === 'ferry'
                            ? 'bg-primary text-primary-foreground shadow-xs'
                            : 'bg-muted text-muted-foreground hover:bg-muted/80'
                    "
                >
                    Ferries ({{ ferryMarkupsCount }})
                </button>
            </div>

            <Card>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b bg-muted/50 text-left text-muted-foreground"
                            >
                                <th class="p-4 font-medium">
                                    Provider / Carrier
                                </th>
                                <th class="p-4 font-medium">
                                    Category / Route
                                </th>
                                <th class="p-4 font-medium">Markup Type</th>
                                <th class="p-4 text-right font-medium">
                                    Markup Value
                                </th>
                                <th class="p-4 text-center font-medium">
                                    Status
                                </th>
                                <th class="p-4 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="filteredMarkups.length === 0">
                                <td
                                    colspan="6"
                                    class="p-8 text-center text-muted-foreground"
                                >
                                    No ticketing markups found for this filter.
                                </td>
                            </tr>
                            <tr
                                v-for="markup in filteredMarkups"
                                :key="markup.id"
                                class="border-b transition-colors hover:bg-muted/50"
                            >
                                <td
                                    class="p-4 align-middle font-medium text-foreground"
                                >
                                    {{ markup.airline }}
                                </td>
                                <td class="p-4 align-middle">
                                    <Badge
                                        variant="outline"
                                        :class="
                                            markup.service_type === 'ferry'
                                                ? 'border-cyan-500/30 bg-cyan-50 text-cyan-700 dark:bg-cyan-950/40 dark:text-cyan-300'
                                                : markup.flight_type ===
                                                    'international'
                                                  ? 'border-purple-500/30 bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-300'
                                                  : 'border-blue-500/30 bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300'
                                        "
                                    >
                                        {{
                                            markup.service_type === 'ferry'
                                                ? 'Ferry'
                                                : markup.flight_type ===
                                                    'international'
                                                  ? 'International Flight'
                                                  : 'Domestic Flight'
                                        }}
                                    </Badge>
                                </td>
                                <td class="p-4 align-middle">
                                    <Badge
                                        variant="secondary"
                                        class="font-normal"
                                    >
                                        {{
                                            markup.markup_type === 'percent'
                                                ? 'Percent (%)'
                                                : 'Fixed (₱)'
                                        }}
                                    </Badge>
                                </td>
                                <td
                                    class="p-4 text-right align-middle font-semibold"
                                >
                                    {{
                                        markup.markup_type === 'percent'
                                            ? `${markup.markup_amount}%`
                                            : formatPhp(markup.markup_amount)
                                    }}
                                </td>
                                <td class="p-4 text-center align-middle">
                                    <Badge
                                        :variant="
                                            markup.is_active
                                                ? 'default'
                                                : 'secondary'
                                        "
                                    >
                                        {{
                                            markup.is_active
                                                ? 'Active'
                                                : 'Inactive'
                                        }}
                                    </Badge>
                                </td>
                                <td class="p-4 text-right align-middle">
                                    <div class="flex justify-end gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            title="Edit"
                                            @click="openEditMarkup(markup)"
                                        >
                                            <Pencil class="size-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            title="Delete"
                                            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                            @click="
                                                confirmDelete(
                                                    markup.id,
                                                    markup.airline,
                                                    '/configurations/airline-markups',
                                                    'This ticketing markup rule will be permanently deleted.',
                                                )
                                            "
                                        >
                                            <Trash class="size-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </section>

        <!-- ============================================== -->
        <!-- TAB 2: ONLINE ASSISTANCE TYPES                 -->
        <!-- ============================================== -->
        <section v-if="activeTab === 'online-assistance'" class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold tracking-tight">
                        Online Assistance Types
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Configure digital assistance service types and base fee
                        amounts.
                    </p>
                </div>
                <Button @click="openCreateAssistance">
                    <Plus class="mr-1.5 size-4" /> Add Type
                </Button>
            </div>

            <Card>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b bg-muted/40 text-xs text-muted-foreground transition-colors hover:bg-muted/50"
                            >
                                <th class="w-16 p-4 text-center font-medium">
                                    Order
                                </th>
                                <th class="p-4 font-medium">Service Name</th>
                                <th class="p-4 text-right font-medium">
                                    Base Price
                                </th>
                                <th class="p-4 text-center font-medium">
                                    Disbursement
                                </th>
                                <th class="p-4 text-right font-medium">
                                    Net Income
                                </th>
                                <th class="p-4 text-center font-medium">
                                    Status
                                </th>
                                <th class="p-4 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="onlineAssistanceTypes.length === 0">
                                <td
                                    colspan="7"
                                    class="p-8 text-center text-muted-foreground"
                                >
                                    No online assistance types configured yet.
                                </td>
                            </tr>
                            <tr
                                v-for="item in onlineAssistanceTypes"
                                :key="item.id"
                                class="border-b transition-colors hover:bg-muted/50"
                            >
                                <td
                                    class="p-4 text-center align-middle font-mono text-xs text-muted-foreground"
                                >
                                    #{{ item.sort_order ?? 0 }}
                                </td>
                                <td
                                    class="p-4 align-middle font-medium text-foreground"
                                >
                                    {{ item.name }}
                                </td>
                                <td
                                    class="p-4 text-right align-middle font-medium"
                                >
                                    {{ formatPhp(item.price) }}
                                </td>
                                <td class="p-4 text-center align-middle">
                                    <div
                                        v-if="Boolean(item.has_disbursement)"
                                        class="inline-flex items-center gap-1.5 font-medium text-xs text-blue-600 dark:text-blue-400"
                                    >
                                        <GcashIcon class="size-3.5" />
                                        <span>{{ formatPhp(item.disbursement_amount || 0) }}</span>
                                    </div>
                                    <Badge
                                        v-else
                                        variant="outline"
                                        class="text-muted-foreground border-dashed"
                                    >
                                        No Disbursement
                                    </Badge>
                                </td>
                                <td class="p-4 text-right align-middle">
                                    <div class="font-semibold text-emerald-600 dark:text-emerald-400">
                                        {{ formatPhp(getAssistanceNetShares(item).ddn) }}
                                    </div>
                                    <div class="text-[11px] text-muted-foreground">
                                        Emp 40%: {{ formatPhp(getAssistanceNetShares(item).employee) }}
                                    </div>
                                </td>
                                <td class="p-4 text-center align-middle">
                                    <Badge
                                        :variant="
                                            item.is_active
                                                ? 'default'
                                                : 'secondary'
                                        "
                                    >
                                        {{
                                            item.is_active
                                                ? 'Active'
                                                : 'Inactive'
                                        }}
                                    </Badge>
                                </td>
                                <td class="p-4 text-right align-middle">
                                    <div class="flex justify-end gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            title="Edit"
                                            @click="openEditAssistance(item)"
                                        >
                                            <Pencil class="size-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            title="Delete"
                                            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                            @click="
                                                confirmDelete(
                                                    item.id,
                                                    item.name,
                                                    '/configurations/online-assistance-types',
                                                    'This online assistance type will be permanently removed if it is not associated with existing transactions.',
                                                )
                                            "
                                        >
                                            <Trash class="size-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </section>

        <!-- ============================================== -->
        <!-- TAB 3: EXPENSE CATEGORIES                      -->
        <!-- ============================================== -->
        <section v-if="activeTab === 'expense-categories'" class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold tracking-tight">
                        Expense Categories
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Categorize expenditures and disbursements for clear
                        financial reporting.
                    </p>
                </div>
                <Button @click="openCreateCategory">
                    <Plus class="mr-1.5 size-4" /> Add Category
                </Button>
            </div>

            <Card>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b bg-muted/50 text-left text-muted-foreground"
                            >
                                <th class="w-16 p-4 text-center font-medium">
                                    Order
                                </th>
                                <th class="p-4 font-medium">Category Name</th>
                                <th class="p-4 font-medium">Description</th>
                                <th class="p-4 text-center font-medium">
                                    Status
                                </th>
                                <th class="p-4 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="expenseCategories.length === 0">
                                <td
                                    colspan="5"
                                    class="p-8 text-center text-muted-foreground"
                                >
                                    No expense categories configured yet.
                                </td>
                            </tr>
                            <tr
                                v-for="cat in expenseCategories"
                                :key="cat.id"
                                class="border-b transition-colors hover:bg-muted/50"
                            >
                                <td
                                    class="p-4 text-center align-middle font-mono text-xs text-muted-foreground"
                                >
                                    #{{ cat.sort_order ?? 0 }}
                                </td>
                                <td
                                    class="p-4 align-middle font-medium text-foreground"
                                >
                                    {{ cat.name }}
                                </td>
                                <td
                                    class="p-4 align-middle text-muted-foreground"
                                >
                                    {{ cat.description || '—' }}
                                </td>
                                <td class="p-4 text-center align-middle">
                                    <Badge
                                        :variant="
                                            cat.is_active
                                                ? 'default'
                                                : 'secondary'
                                        "
                                    >
                                        {{
                                            cat.is_active
                                                ? 'Active'
                                                : 'Inactive'
                                        }}
                                    </Badge>
                                </td>
                                <td class="p-4 text-right align-middle">
                                    <div class="flex justify-end gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            title="Edit"
                                            @click="openEditCategory(cat)"
                                        >
                                            <Pencil class="size-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            title="Delete"
                                            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                            @click="
                                                confirmDelete(
                                                    cat.id,
                                                    cat.name,
                                                    '/configurations/expense-categories',
                                                    'This expense category will be permanently removed if it is not associated with existing expenses.',
                                                )
                                            "
                                        >
                                            <Trash class="size-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </section>

        <!-- ============================================== -->
        <!-- TAB 5: PSA RATES                               -->
        <!-- ============================================== -->
        <section v-if="activeTab === 'psa-rates'" class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold tracking-tight">
                        PSA Document Rates
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Configure fixed customer prices and PSA officer payouts
                        for PSA assistance (Birth, Marriage, Death, CENOMAR).
                    </p>
                </div>
                <Button @click="openCreatePsa">
                    <Plus class="mr-1.5 size-4" /> Add Rate
                </Button>
            </div>

            <Card>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b bg-muted/50 text-left text-muted-foreground"
                            >
                                <th class="w-16 p-4 text-center font-medium">
                                    Order
                                </th>
                                <th class="p-4 font-medium">Code</th>
                                <th class="p-4 font-medium">Document Name</th>
                                <th class="p-4 text-right font-medium">
                                    Customer Price
                                </th>
                                <th class="p-4 text-right font-medium">
                                    PSA Officer Fee
                                </th>
                                <th class="p-4 text-right font-medium">
                                    Net DDN Income
                                </th>
                                <th class="p-4 text-center font-medium">
                                    Status
                                </th>
                                <th class="p-4 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="psaRates.length === 0">
                                <td
                                    colspan="8"
                                    class="p-8 text-center text-muted-foreground"
                                >
                                    No PSA rates configured yet. Click "Add
                                    Rate" to create one.
                                </td>
                            </tr>
                            <tr
                                v-for="rate in psaRates"
                                :key="rate.id"
                                class="border-b transition-colors hover:bg-muted/50"
                            >
                                <td
                                    class="p-4 text-center align-middle font-mono text-xs text-muted-foreground"
                                >
                                    #{{ rate.sort_order ?? 0 }}
                                </td>
                                <td class="p-4 align-middle font-medium">
                                    <Badge
                                        variant="outline"
                                        class="font-mono text-xs font-semibold"
                                    >
                                        {{ rate.document_type }}
                                    </Badge>
                                </td>
                                <td
                                    class="p-4 align-middle font-medium text-foreground"
                                >
                                    {{ rate.name }}
                                </td>
                                <td
                                    class="p-4 text-right align-middle font-medium"
                                >
                                    {{ formatPhp(rate.price) }}
                                </td>
                                <td
                                    class="p-4 text-right align-middle text-muted-foreground"
                                >
                                    {{ formatPhp(rate.officer_fee) }}
                                </td>
                                <td
                                    class="p-4 text-right align-middle font-semibold text-emerald-600 dark:text-emerald-400"
                                >
                                    {{
                                        formatPhp(
                                            rate.net_income ??
                                                Number(rate.price) -
                                                    Number(rate.officer_fee),
                                        )
                                    }}
                                </td>
                                <td class="p-4 text-center align-middle">
                                    <Badge
                                        :variant="
                                            rate.is_active
                                                ? 'default'
                                                : 'secondary'
                                        "
                                    >
                                        {{
                                            rate.is_active
                                                ? 'Active'
                                                : 'Inactive'
                                        }}
                                    </Badge>
                                </td>
                                <td class="p-4 text-right align-middle">
                                    <div class="flex justify-end gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            title="Edit"
                                            @click="openEditPsa(rate)"
                                        >
                                            <Pencil class="size-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            title="Delete"
                                            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                            @click="
                                                confirmDelete(
                                                    rate.id,
                                                    rate.name,
                                                    '/configurations/psa-rates',
                                                    'This PSA rate will be deleted.',
                                                )
                                            "
                                        >
                                            <Trash class="size-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </section>

        <!-- ============================================== -->
        <!-- MODAL: BAGGAGE OPTION (CREATE / EDIT)          -->
        <!-- ============================================== -->
        <Dialog v-model:open="isBaggageModalOpen">
            <DialogContent class="sm:max-w-md">
                <form @submit.prevent="submitBaggage">
                    <DialogHeader>
                        <DialogTitle>
                            {{
                                editingBaggageId
                                    ? 'Edit Baggage Option'
                                    : 'Add Baggage Option'
                            }}
                        </DialogTitle>
                        <DialogDescription>
                            {{
                                editingBaggageId
                                    ? 'Update details for this baggage allowance tier.'
                                    : 'Add a new baggage allowance tier for airline bookings.'
                            }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-4 py-4">
                        <div class="space-y-1.5">
                            <Label for="baggage-airline"
                                >Airline (Optional)</Label
                            >
                            <Input
                                id="baggage-airline"
                                v-model="baggageForm.airline"
                                placeholder="e.g. Cebu Pacific (Leave empty for all)"
                            />
                            <p class="text-xs text-muted-foreground">
                                Leave blank to make this option available for
                                all airlines.
                            </p>
                            <div
                                v-if="baggageForm.errors.airline"
                                class="text-xs text-destructive"
                            >
                                {{ baggageForm.errors.airline }}
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="baggage-label">Label</Label>
                            <Input
                                id="baggage-label"
                                v-model="baggageForm.label"
                                placeholder="e.g. +20 KG"
                                required
                            />
                            <div
                                v-if="baggageForm.errors.label"
                                class="text-xs text-destructive"
                            >
                                {{ baggageForm.errors.label }}
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label for="baggage-weight">Weight (KG)</Label>
                                <Input
                                    id="baggage-weight"
                                    type="number"
                                    min="0"
                                    v-model="baggageForm.weight_kg"
                                    required
                                />
                                <div
                                    v-if="baggageForm.errors.weight_kg"
                                    class="text-xs text-destructive"
                                >
                                    {{ baggageForm.errors.weight_kg }}
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="hasDuplicateBaggageWarning"
                            class="flex items-center gap-2 rounded-md border border-amber-500/20 bg-amber-500/10 p-2.5 text-xs text-amber-700 dark:text-amber-400"
                        >
                            <AlertCircle class="size-4 shrink-0" />
                            <span>
                                Note: A baggage option for
                                {{
                                    baggageForm.airline
                                        ? baggageForm.airline
                                        : 'Universal'
                                }}
                                with {{ baggageForm.weight_kg }} KG already
                                exists.
                            </span>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="baggage-sort">Sort Order</Label>
                            <Input
                                id="baggage-sort"
                                type="number"
                                v-model="baggageForm.sort_order"
                            />
                            <div
                                v-if="baggageForm.errors.sort_order"
                                class="text-xs text-destructive"
                            >
                                {{ baggageForm.errors.sort_order }}
                            </div>
                        </div>

                        <div class="flex items-center space-x-2 pt-1">
                            <Checkbox
                                id="baggage-active"
                                :checked="baggageForm.is_active"
                                @update:checked="baggageForm.is_active = $event"
                            />
                            <Label
                                for="baggage-active"
                                class="cursor-pointer font-normal"
                            >
                                Active (Visible in transaction forms)
                            </Label>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="isBaggageModalOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="baggageForm.processing"
                        >
                            {{
                                editingBaggageId
                                    ? 'Save Changes'
                                    : 'Create Option'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- ============================================== -->
        <!-- MODAL: TICKETING MARKUP (CREATE / EDIT)        -->
        <!-- ============================================== -->
        <Dialog v-model:open="isMarkupModalOpen">
            <DialogContent class="sm:max-w-md">
                <form @submit.prevent="submitMarkup">
                    <DialogHeader>
                        <DialogTitle>
                            {{
                                editingMarkupId
                                    ? 'Edit Ticketing Markup'
                                    : 'Add Ticketing Markup'
                            }}
                        </DialogTitle>
                        <DialogDescription>
                            Configure dynamic markup for flights or ferries.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-4 py-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1.5">
                                <Label for="markup-service">Service</Label>
                                <select
                                    id="markup-service"
                                    v-model="markupForm.service_type"
                                    @change="
                                        if (
                                            markupForm.service_type === 'ferry'
                                        ) {
                                            markupForm.flight_type = null;
                                            if (!markupForm.airline)
                                                markupForm.airline =
                                                    'All Ferries';
                                        } else if (!markupForm.flight_type) {
                                            markupForm.flight_type = 'local';
                                        }
                                    "
                                    class="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                    required
                                >
                                    <option value="flight">Flight</option>
                                    <option value="ferry">Ferry</option>
                                </select>
                                <div
                                    v-if="markupForm.errors.service_type"
                                    class="text-xs text-destructive"
                                >
                                    {{ markupForm.errors.service_type }}
                                </div>
                            </div>

                            <div
                                v-if="markupForm.service_type === 'flight'"
                                class="space-y-1.5"
                            >
                                <Label for="markup-flight">Flight Type</Label>
                                <select
                                    id="markup-flight"
                                    v-model="markupForm.flight_type"
                                    class="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                    required
                                >
                                    <option value="local">
                                        Domestic (Local)
                                    </option>
                                    <option value="international">
                                        International
                                    </option>
                                </select>
                                <div
                                    v-if="markupForm.errors.flight_type"
                                    class="text-xs text-destructive"
                                >
                                    {{ markupForm.errors.flight_type }}
                                </div>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="markup-airline">
                                {{
                                    markupForm.service_type === 'ferry'
                                        ? 'Ferry Provider / Category'
                                        : markupForm.flight_type ===
                                            'international'
                                          ? 'Carrier Category or Airline'
                                          : 'Airline Name'
                                }}
                            </Label>
                            <Input
                                id="markup-airline"
                                v-model="markupForm.airline"
                                :placeholder="
                                    markupForm.service_type === 'ferry'
                                        ? 'e.g. All Ferries'
                                        : markupForm.flight_type ===
                                            'international'
                                          ? 'e.g. Budget Carriers or Full Service Carriers'
                                          : 'e.g. Cebu Pacific'
                                "
                                required
                            />
                            <div
                                v-if="markupForm.errors.airline"
                                class="text-xs text-destructive"
                            >
                                {{ markupForm.errors.airline }}
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1.5">
                                <Label for="markup-type">Markup Type</Label>
                                <select
                                    id="markup-type"
                                    v-model="markupForm.markup_type"
                                    class="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                    required
                                >
                                    <option value="fixed">Fixed (₱)</option>
                                    <option value="percent">
                                        Percentage (%)
                                    </option>
                                </select>
                                <div
                                    v-if="markupForm.errors.markup_type"
                                    class="text-xs text-destructive"
                                >
                                    {{ markupForm.errors.markup_type }}
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <Label for="markup-amount">
                                    {{
                                        markupForm.markup_type === 'percent'
                                            ? 'Markup Rate (%)'
                                            : 'Markup Amount (₱)'
                                    }}
                                </Label>
                                <Input
                                    id="markup-amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    v-model="markupForm.markup_amount"
                                    required
                                />
                                <div
                                    v-if="markupForm.errors.markup_amount"
                                    class="text-xs text-destructive"
                                >
                                    {{ markupForm.errors.markup_amount }}
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center space-x-2 pt-1">
                            <Checkbox
                                id="markup-active"
                                :checked="markupForm.is_active"
                                @update:checked="markupForm.is_active = $event"
                            />
                            <Label
                                for="markup-active"
                                class="cursor-pointer font-normal"
                            >
                                Active (Visible in transaction forms)
                            </Label>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="isMarkupModalOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="markupForm.processing">
                            {{
                                editingMarkupId
                                    ? 'Save Changes'
                                    : 'Create Markup'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- ============================================== -->
        <!-- MODAL: ONLINE ASSISTANCE (CREATE / EDIT)       -->
        <!-- ============================================== -->
        <Dialog v-model:open="isAssistanceModalOpen">
            <DialogContent class="sm:max-w-md">
                <form @submit.prevent="submitAssistance">
                    <DialogHeader>
                        <DialogTitle>
                            {{
                                editingAssistanceId
                                    ? 'Edit Online Assistance Type'
                                    : 'Add Online Assistance Type'
                            }}
                        </DialogTitle>
                        <DialogDescription>
                            {{
                                editingAssistanceId
                                    ? 'Update details for this online assistance service.'
                                    : 'Add a new service type for online assistance transactions.'
                            }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-4 py-4">
                        <div class="space-y-1.5">
                            <Label for="assistance-name">Service Name</Label>
                            <Input
                                id="assistance-name"
                                v-model="assistanceForm.name"
                                placeholder="e.g. Visa Processing"
                                required
                            />
                            <div
                                v-if="assistanceForm.errors.name"
                                class="text-xs text-destructive"
                            >
                                {{ assistanceForm.errors.name }}
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="assistance-price">Base Price (₱)</Label>
                            <Input
                                id="assistance-price"
                                type="number"
                                step="0.01"
                                min="0"
                                v-model="assistanceForm.price"
                                required
                            />
                            <div
                                v-if="assistanceForm.errors.price"
                                class="text-xs text-destructive"
                            >
                                {{ assistanceForm.errors.price }}
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="assistance-sort">Sort Order</Label>
                            <Input
                                id="assistance-sort"
                                type="number"
                                v-model="assistanceForm.sort_order"
                            />
                            <div
                                v-if="assistanceForm.errors.sort_order"
                                class="text-xs text-destructive"
                            >
                                {{ assistanceForm.errors.sort_order }}
                            </div>
                        </div>

                        <div class="rounded-lg border p-3 bg-muted/30 space-y-3">
                            <div class="flex items-start space-x-2">
                                <input
                                    type="checkbox"
                                    id="assistance-disbursement"
                                    v-model="assistanceForm.has_disbursement"
                                    class="peer size-4 mt-0.5 shrink-0 rounded-sm border-input bg-background text-primary shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                />
                                <div class="space-y-0.5">
                                    <Label
                                        for="assistance-disbursement"
                                        class="cursor-pointer font-medium"
                                    >
                                        Requires Government Fee Disbursement
                                    </Label>
                                    <p class="text-xs text-muted-foreground">
                                        Enable if this service involves paying a government/third-party fee (e.g., NBI or Police Clearance via GCash). Disable if this service has no government disbursement.
                                    </p>
                                </div>
                            </div>

                            <!-- Disbursement Amount (Paid via GCash) -->
                            <div
                                v-if="Boolean(assistanceForm.has_disbursement)"
                                class="border-t pt-3 space-y-1.5"
                            >
                                <Label
                                    for="assistance-disbursement-amount"
                                    class="flex items-center gap-1.5 text-xs font-semibold text-blue-600 dark:text-blue-400"
                                >
                                    <GcashIcon class="size-3.5" />
                                    <span>Disbursement Amount (Paid via GCash)</span>
                                </Label>
                                <Input
                                    id="assistance-disbursement-amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    v-model="assistanceForm.disbursement_amount"
                                    placeholder="e.g. 160.00"
                                />
                                <p class="text-[11px] text-muted-foreground">
                                    Default fee deducted from GCash when entering transactions. Staff can still adjust this on the transaction form.
                                </p>
                                <div
                                    v-if="assistanceForm.errors.disbursement_amount"
                                    class="text-xs text-destructive"
                                >
                                    {{ assistanceForm.errors.disbursement_amount }}
                                </div>
                            </div>
                        </div>

                        <!-- Live Calculation Preview -->
                        <div class="rounded-lg border bg-card p-3 space-y-2 text-xs">
                            <div class="flex justify-between text-muted-foreground">
                                <span>Base Price:</span>
                                <span class="font-medium text-foreground">{{ formatPhp(assistanceForm.price || 0) }}</span>
                            </div>
                            <div
                                v-if="Boolean(assistanceForm.has_disbursement)"
                                class="flex justify-between text-muted-foreground"
                            >
                                <span>Disbursement (GCash):</span>
                                <span class="font-medium text-blue-600 dark:text-blue-400">
                                    - {{ formatPhp(assistanceForm.disbursement_amount || 0) }}
                                </span>
                            </div>
                            <div class="flex justify-between border-t pt-1.5 font-semibold">
                                <span>Net Service Margin:</span>
                                <span class="text-foreground">{{ formatPhp(modalAssistanceMargin) }}</span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 pt-1 border-t">
                                <div class="rounded bg-muted/50 p-2">
                                    <p class="text-[10px] text-muted-foreground">DDN Office Share (60%)</p>
                                    <p class="font-bold text-sm text-emerald-600 dark:text-emerald-400">
                                        {{ formatPhp(modalAssistanceMargin * 0.6) }}
                                    </p>
                                </div>
                                <div class="rounded bg-muted/50 p-2">
                                    <p class="text-[10px] text-muted-foreground">Employee Cut (40%)</p>
                                    <p class="font-bold text-sm text-foreground">
                                        {{ formatPhp(modalAssistanceMargin * 0.4) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center space-x-2 pt-1">
                            <input
                                type="checkbox"
                                id="assistance-active"
                                v-model="assistanceForm.is_active"
                                class="peer size-4 shrink-0 rounded-sm border-input bg-background text-primary shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            />
                            <Label
                                for="assistance-active"
                                class="cursor-pointer font-normal"
                            >
                                Active (Visible in transaction forms)
                            </Label>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="isAssistanceModalOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="assistanceForm.processing"
                        >
                            {{
                                editingAssistanceId
                                    ? 'Save Changes'
                                    : 'Create Type'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- ============================================== -->
        <!-- MODAL: EXPENSE CATEGORY (CREATE / EDIT)        -->
        <!-- ============================================== -->
        <Dialog v-model:open="isCategoryModalOpen">
            <DialogContent class="sm:max-w-md">
                <form @submit.prevent="submitCategory">
                    <DialogHeader>
                        <DialogTitle>
                            {{
                                editingCategoryId
                                    ? 'Edit Expense Category'
                                    : 'Add Expense Category'
                            }}
                        </DialogTitle>
                        <DialogDescription>
                            {{
                                editingCategoryId
                                    ? 'Update details for this expenditure category.'
                                    : 'Create a new category for tracking business expenses.'
                            }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-4 py-4">
                        <div class="space-y-1.5">
                            <Label for="category-name">Category Name</Label>
                            <Input
                                id="category-name"
                                v-model="categoryForm.name"
                                placeholder="e.g. Utilities"
                                required
                            />
                            <div
                                v-if="categoryForm.errors.name"
                                class="text-xs text-destructive"
                            >
                                {{ categoryForm.errors.name }}
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="category-desc"
                                >Description (Optional)</Label
                            >
                            <Input
                                id="category-desc"
                                v-model="categoryForm.description"
                                placeholder="e.g. Electricity, water, and internet bills"
                            />
                            <div
                                v-if="categoryForm.errors.description"
                                class="text-xs text-destructive"
                            >
                                {{ categoryForm.errors.description }}
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="category-sort">Sort Order</Label>
                            <Input
                                id="category-sort"
                                type="number"
                                v-model="categoryForm.sort_order"
                            />
                            <div
                                v-if="categoryForm.errors.sort_order"
                                class="text-xs text-destructive"
                            >
                                {{ categoryForm.errors.sort_order }}
                            </div>
                        </div>

                        <div class="flex items-center space-x-2 pt-1">
                            <Checkbox
                                id="category-active"
                                :checked="categoryForm.is_active"
                                @update:checked="
                                    categoryForm.is_active = $event
                                "
                            />
                            <Label
                                for="category-active"
                                class="cursor-pointer font-normal"
                            >
                                Active (Visible in expense forms)
                            </Label>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="isCategoryModalOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="categoryForm.processing"
                        >
                            {{
                                editingCategoryId
                                    ? 'Save Changes'
                                    : 'Create Category'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- ============================================== -->
        <!-- MODAL: PSA RATE (CREATE / EDIT)                -->
        <!-- ============================================== -->
        <Dialog v-model:open="isPsaModalOpen">
            <DialogContent class="sm:max-w-md">
                <form @submit.prevent="submitPsa">
                    <DialogHeader>
                        <DialogTitle>
                            {{
                                editingPsaId
                                    ? 'Edit PSA Document Rate'
                                    : 'Add PSA Document Rate'
                            }}
                        </DialogTitle>
                        <DialogDescription>
                            {{
                                editingPsaId
                                    ? 'Update fixed price and PSA officer payment.'
                                    : 'Set up a new fixed rate for PSA document processing.'
                            }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-4 py-4">
                        <div class="grid grid-cols-3 gap-3">
                            <div class="col-span-1 space-y-1.5">
                                <Label for="psa-code">Code</Label>
                                <Input
                                    id="psa-code"
                                    v-model="psaForm.document_type"
                                    placeholder="e.g. BC"
                                    required
                                />
                                <div
                                    v-if="psaForm.errors.document_type"
                                    class="text-xs text-destructive"
                                >
                                    {{ psaForm.errors.document_type }}
                                </div>
                            </div>

                            <div class="col-span-2 space-y-1.5">
                                <Label for="psa-name">Document Name</Label>
                                <Input
                                    id="psa-name"
                                    v-model="psaForm.name"
                                    placeholder="e.g. Birth Certificate"
                                    required
                                />
                                <div
                                    v-if="psaForm.errors.name"
                                    class="text-xs text-destructive"
                                >
                                    {{ psaForm.errors.name }}
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1.5">
                                <Label for="psa-price"
                                    >Customer Price (PHP)</Label
                                >
                                <Input
                                    id="psa-price"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    v-model="psaForm.price"
                                    required
                                />
                                <div
                                    v-if="psaForm.errors.price"
                                    class="text-xs text-destructive"
                                >
                                    {{ psaForm.errors.price }}
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <Label for="psa-officer-fee"
                                    >Officer Fee (PHP)</Label
                                >
                                <Input
                                    id="psa-officer-fee"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    v-model="psaForm.officer_fee"
                                    required
                                />
                                <div
                                    v-if="psaForm.errors.officer_fee"
                                    class="text-xs text-destructive"
                                >
                                    {{ psaForm.errors.officer_fee }}
                                </div>
                            </div>
                        </div>

                        <!-- Live Net Income Calculation Preview -->
                        <div class="rounded-lg border bg-muted/40 p-3 text-xs">
                            <div
                                class="flex items-center justify-between text-muted-foreground"
                            >
                                <span>Customer Price:</span>
                                <span>{{ formatPhp(psaForm.price) }}</span>
                            </div>
                            <div
                                class="mt-1 flex items-center justify-between text-muted-foreground"
                            >
                                <span>PSA Officer Payment:</span>
                                <span
                                    >-{{ formatPhp(psaForm.officer_fee) }}</span
                                >
                            </div>
                            <div
                                class="mt-2 flex items-center justify-between border-t pt-2 font-semibold"
                            >
                                <span class="text-foreground"
                                    >Net DDN Income:</span
                                >
                                <span
                                    class="text-sm font-bold text-emerald-600 dark:text-emerald-400"
                                >
                                    {{ formatPhp(computedPsaNetIncome) }}
                                </span>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="psa-sort">Sort Order</Label>
                            <Input
                                id="psa-sort"
                                type="number"
                                v-model="psaForm.sort_order"
                            />
                            <div
                                v-if="psaForm.errors.sort_order"
                                class="text-xs text-destructive"
                            >
                                {{ psaForm.errors.sort_order }}
                            </div>
                        </div>

                        <div class="flex items-center space-x-2 pt-1">
                            <Checkbox
                                id="psa-active"
                                :checked="psaForm.is_active"
                                @update:checked="psaForm.is_active = $event"
                            />
                            <Label
                                for="psa-active"
                                class="cursor-pointer font-normal"
                            >
                                Active (Visible in transaction forms)
                            </Label>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="isPsaModalOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="psaForm.processing">
                            {{ editingPsaId ? 'Save Changes' : 'Create Rate' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- ============================================== -->
        <!-- MODAL: DELETE CONFIRMATION                     -->
        <!-- ============================================== -->
        <Dialog v-model:open="isDeleteModalOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Delete Confirmation</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete
                        <strong class="text-foreground">{{
                            itemToDelete?.title
                        }}</strong
                        >?
                        {{ itemToDelete?.description }}
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="mt-4 gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="isDeleting"
                        @click="isDeleteModalOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="isDeleting"
                        @click="executeDelete"
                    >
                        {{ isDeleting ? 'Deleting...' : 'Delete' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </main>
</template>
