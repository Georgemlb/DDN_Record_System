<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Headphones,
    Luggage,
    Pencil,
    Plus,
    Tag,
    Trash,
} from '@lucide/vue';
import { ref, watch } from 'vue';
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
import { formatPhp } from '@/lib/transactionFormat';
import type { ExpenseCategory } from '@/types/expense';
import type { BaggageOption, OnlineAssistanceType } from '@/types/transaction';

const props = defineProps<{
    baggageOptions: BaggageOption[];
    onlineAssistanceTypes: OnlineAssistanceType[];
    expenseCategories: ExpenseCategory[];
    currentTab: 'baggage' | 'online-assistance' | 'expense-categories';
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
type TabKey = 'baggage' | 'online-assistance' | 'expense-categories';

const activeTab = ref<TabKey>(props.currentTab || 'baggage');

const tabs = [
    {
        key: 'baggage' as TabKey,
        label: 'Baggage Options',
        icon: Luggage,
        count: () => props.baggageOptions.length,
    },
    {
        key: 'online-assistance' as TabKey,
        label: 'Online Assistance',
        icon: Headphones,
        count: () => props.onlineAssistanceTypes.length,
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
// Baggage Modal & Form
// --------------------------------------------------------
const isBaggageModalOpen = ref(false);
const editingBaggageId = ref<number | null>(null);

const baggageForm = useForm({
    airline: '',
    label: '',
    weight_kg: 20,
    price: 0,
    sort_order: 0,
    is_active: true,
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
    baggageForm.price = Number(option.price);
    baggageForm.sort_order = option.sort_order ?? 0;
    baggageForm.is_active = option.is_active ?? true;
    isBaggageModalOpen.value = true;
};

const submitBaggage = () => {
    if (editingBaggageId.value) {
        baggageForm.put(`/configurations/baggage-options/${editingBaggageId.value}`, {
            onSuccess: () => {
                isBaggageModalOpen.value = false;
            },
        });
    } else {
        baggageForm.post('/configurations/baggage-options', {
            onSuccess: () => {
                isBaggageModalOpen.value = false;
            },
        });
    }
};

const deleteBaggage = (id: number) => {
    if (confirm('Are you sure you want to delete this baggage option?')) {
        router.delete(`/configurations/baggage-options/${id}`, {
            preserveScroll: true,
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
    sort_order: 0,
    is_active: true,
});

const openCreateAssistance = () => {
    editingAssistanceId.value = null;
    assistanceForm.reset();
    assistanceForm.clearErrors();
    isAssistanceModalOpen.value = true;
};

const openEditAssistance = (item: OnlineAssistanceType) => {
    editingAssistanceId.value = item.id;
    assistanceForm.clearErrors();
    assistanceForm.name = item.name;
    assistanceForm.price = Number(item.price);
    assistanceForm.sort_order = item.sort_order ?? 0;
    assistanceForm.is_active = item.is_active ?? true;
    isAssistanceModalOpen.value = true;
};

const submitAssistance = () => {
    if (editingAssistanceId.value) {
        assistanceForm.put(`/configurations/online-assistance-types/${editingAssistanceId.value}`, {
            onSuccess: () => {
                isAssistanceModalOpen.value = false;
            },
        });
    } else {
        assistanceForm.post('/configurations/online-assistance-types', {
            onSuccess: () => {
                isAssistanceModalOpen.value = false;
            },
        });
    }
};

const deleteAssistance = (id: number) => {
    if (confirm('Are you sure you want to delete this online assistance type?')) {
        router.delete(`/configurations/online-assistance-types/${id}`, {
            preserveScroll: true,
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
        categoryForm.put(`/configurations/expense-categories/${editingCategoryId.value}`, {
            onSuccess: () => {
                isCategoryModalOpen.value = false;
            },
        });
    } else {
        categoryForm.post('/configurations/expense-categories', {
            onSuccess: () => {
                isCategoryModalOpen.value = false;
            },
        });
    }
};

const deleteCategory = (id: number) => {
    if (confirm('Are you sure you want to delete this expense category?')) {
        router.delete(`/configurations/expense-categories/${id}`, {
            preserveScroll: true,
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
                <p class="text-sm font-medium text-primary">Master Data & Settings</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">
                    Configurations
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Manage master dropdown options for transactions, operational services, and expense tracking.
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
                        class="ml-1 px-1.5 py-0.2 text-xs font-normal"
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
                    <h2 class="text-lg font-semibold tracking-tight">Baggage Options</h2>
                    <p class="text-sm text-muted-foreground">
                        Configure weight tiers and default pricing per airline or universal.
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
                            <tr class="border-b bg-muted/50 text-left text-muted-foreground">
                                <th class="p-4 font-medium">Airline</th>
                                <th class="p-4 font-medium">Label</th>
                                <th class="p-4 font-medium text-right">Weight</th>
                                <th class="p-4 font-medium text-right">Price</th>
                                <th class="p-4 font-medium text-center">Status</th>
                                <th class="p-4 font-medium text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="baggageOptions.length === 0">
                                <td colspan="6" class="p-8 text-center text-muted-foreground">
                                    No baggage options configured yet.
                                </td>
                            </tr>
                            <tr
                                v-for="option in baggageOptions"
                                :key="option.id"
                                class="border-b transition-colors hover:bg-muted/50"
                            >
                                <td class="p-4 align-middle">
                                    <span v-if="option.airline" class="font-medium text-foreground">
                                        {{ option.airline }}
                                    </span>
                                    <span v-else class="italic text-muted-foreground">
                                        Universal (All Airlines)
                                    </span>
                                </td>
                                <td class="p-4 align-middle font-medium">{{ option.label }}</td>
                                <td class="p-4 align-middle text-right">{{ option.weight_kg }} KG</td>
                                <td class="p-4 align-middle text-right font-medium">
                                    {{ formatPhp(option.price) }}
                                </td>
                                <td class="p-4 align-middle text-center">
                                    <Badge :variant="option.is_active ? 'default' : 'secondary'">
                                        {{ option.is_active ? 'Active' : 'Inactive' }}
                                    </Badge>
                                </td>
                                <td class="p-4 align-middle text-right">
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
                                            @click="deleteBaggage(option.id)"
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
                    <h2 class="text-lg font-semibold tracking-tight">Online Assistance Types</h2>
                    <p class="text-sm text-muted-foreground">
                        Configure digital assistance service types and base fee amounts.
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
                            <tr class="border-b bg-muted/50 text-left text-muted-foreground">
                                <th class="p-4 font-medium">Service Name</th>
                                <th class="p-4 font-medium text-right">Base Price</th>
                                <th class="p-4 font-medium text-center">Status</th>
                                <th class="p-4 font-medium text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="onlineAssistanceTypes.length === 0">
                                <td colspan="4" class="p-8 text-center text-muted-foreground">
                                    No online assistance types configured yet.
                                </td>
                            </tr>
                            <tr
                                v-for="item in onlineAssistanceTypes"
                                :key="item.id"
                                class="border-b transition-colors hover:bg-muted/50"
                            >
                                <td class="p-4 align-middle font-medium text-foreground">
                                    {{ item.name }}
                                </td>
                                <td class="p-4 align-middle text-right font-medium">
                                    {{ formatPhp(item.price) }}
                                </td>
                                <td class="p-4 align-middle text-center">
                                    <Badge :variant="item.is_active ? 'default' : 'secondary'">
                                        {{ item.is_active ? 'Active' : 'Inactive' }}
                                    </Badge>
                                </td>
                                <td class="p-4 align-middle text-right">
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
                                            @click="deleteAssistance(item.id)"
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
                    <h2 class="text-lg font-semibold tracking-tight">Expense Categories</h2>
                    <p class="text-sm text-muted-foreground">
                        Categorize expenditures and disbursements for clear financial reporting.
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
                            <tr class="border-b bg-muted/50 text-left text-muted-foreground">
                                <th class="p-4 font-medium">Category Name</th>
                                <th class="p-4 font-medium">Description</th>
                                <th class="p-4 font-medium text-center">Status</th>
                                <th class="p-4 font-medium text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="expenseCategories.length === 0">
                                <td colspan="4" class="p-8 text-center text-muted-foreground">
                                    No expense categories configured yet.
                                </td>
                            </tr>
                            <tr
                                v-for="cat in expenseCategories"
                                :key="cat.id"
                                class="border-b transition-colors hover:bg-muted/50"
                            >
                                <td class="p-4 align-middle font-medium text-foreground">
                                    {{ cat.name }}
                                </td>
                                <td class="p-4 align-middle text-muted-foreground">
                                    {{ cat.description || '—' }}
                                </td>
                                <td class="p-4 align-middle text-center">
                                    <Badge :variant="cat.is_active ? 'default' : 'secondary'">
                                        {{ cat.is_active ? 'Active' : 'Inactive' }}
                                    </Badge>
                                </td>
                                <td class="p-4 align-middle text-right">
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
                                            @click="deleteCategory(cat.id)"
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
                            {{ editingBaggageId ? 'Edit Baggage Option' : 'Add Baggage Option' }}
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
                            <Label for="baggage-airline">Airline (Optional)</Label>
                            <Input
                                id="baggage-airline"
                                v-model="baggageForm.airline"
                                placeholder="e.g. Cebu Pacific (Leave empty for all)"
                            />
                            <p class="text-xs text-muted-foreground">
                                Leave blank to make this option available for all airlines.
                            </p>
                            <div v-if="baggageForm.errors.airline" class="text-xs text-destructive">
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
                            <div v-if="baggageForm.errors.label" class="text-xs text-destructive">
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
                                <div v-if="baggageForm.errors.weight_kg" class="text-xs text-destructive">
                                    {{ baggageForm.errors.weight_kg }}
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <Label for="baggage-price">Price (₱)</Label>
                                <Input
                                    id="baggage-price"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    v-model="baggageForm.price"
                                    required
                                />
                                <div v-if="baggageForm.errors.price" class="text-xs text-destructive">
                                    {{ baggageForm.errors.price }}
                                </div>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="baggage-sort">Sort Order</Label>
                            <Input
                                id="baggage-sort"
                                type="number"
                                v-model="baggageForm.sort_order"
                            />
                            <div v-if="baggageForm.errors.sort_order" class="text-xs text-destructive">
                                {{ baggageForm.errors.sort_order }}
                            </div>
                        </div>

                        <div class="flex items-center space-x-2 pt-1">
                            <Checkbox
                                id="baggage-active"
                                :checked="baggageForm.is_active"
                                @update:checked="baggageForm.is_active = $event"
                            />
                            <Label for="baggage-active" class="font-normal cursor-pointer">
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
                        <Button type="submit" :disabled="baggageForm.processing">
                            {{ editingBaggageId ? 'Save Changes' : 'Create Option' }}
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
                            <div v-if="assistanceForm.errors.name" class="text-xs text-destructive">
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
                            <div v-if="assistanceForm.errors.price" class="text-xs text-destructive">
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
                            <div v-if="assistanceForm.errors.sort_order" class="text-xs text-destructive">
                                {{ assistanceForm.errors.sort_order }}
                            </div>
                        </div>

                        <div class="flex items-center space-x-2 pt-1">
                            <Checkbox
                                id="assistance-active"
                                :checked="assistanceForm.is_active"
                                @update:checked="assistanceForm.is_active = $event"
                            />
                            <Label for="assistance-active" class="font-normal cursor-pointer">
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
                        <Button type="submit" :disabled="assistanceForm.processing">
                            {{ editingAssistanceId ? 'Save Changes' : 'Create Type' }}
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
                            {{ editingCategoryId ? 'Edit Expense Category' : 'Add Expense Category' }}
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
                            <div v-if="categoryForm.errors.name" class="text-xs text-destructive">
                                {{ categoryForm.errors.name }}
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="category-desc">Description (Optional)</Label>
                            <Input
                                id="category-desc"
                                v-model="categoryForm.description"
                                placeholder="e.g. Electricity, water, and internet bills"
                            />
                            <div v-if="categoryForm.errors.description" class="text-xs text-destructive">
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
                            <div v-if="categoryForm.errors.sort_order" class="text-xs text-destructive">
                                {{ categoryForm.errors.sort_order }}
                            </div>
                        </div>

                        <div class="flex items-center space-x-2 pt-1">
                            <Checkbox
                                id="category-active"
                                :checked="categoryForm.is_active"
                                @update:checked="categoryForm.is_active = $event"
                            />
                            <Label for="category-active" class="font-normal cursor-pointer">
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
                        <Button type="submit" :disabled="categoryForm.processing">
                            {{ editingCategoryId ? 'Save Changes' : 'Create Category' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </main>
</template>
