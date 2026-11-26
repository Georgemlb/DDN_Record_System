<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { FileText, Headphones, Tag, Ticket } from '@lucide/vue';
import { ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { ExpenseCategory } from '@/types/expense';
import type {
    AirlineMarkup,
    OnlineAssistanceType,
    PsaRate,
} from '@/types/transaction';
import AirlineMarkupsTab from './components/AirlineMarkupsTab.vue';
import ExpenseCategoriesTab from './components/ExpenseCategoriesTab.vue';
import OnlineAssistanceTypesTab from './components/OnlineAssistanceTypesTab.vue';
import PsaRatesTab from './components/PsaRatesTab.vue';

const props = defineProps<{
    airlineMarkups: AirlineMarkup[];
    onlineAssistanceTypes: OnlineAssistanceType[];
    expenseCategories: ExpenseCategory[];
    psaRates: PsaRate[];
    currentTab:
        | 'airline-markups'
        | 'online-assistance'
        | 'expense-categories'
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

type TabKey =
    | 'airline-markups'
    | 'online-assistance'
    | 'expense-categories'
    | 'psa-rates';

const activeTab = ref<TabKey>(props.currentTab || 'airline-markups');

const tabs = [
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
</script>

<template>
    <Head title="Configurations" />

    <main class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
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

        <AirlineMarkupsTab
            v-if="activeTab === 'airline-markups'"
            :airline-markups="airlineMarkups"
            @confirm-delete="confirmDelete"
        />

        <OnlineAssistanceTypesTab
            v-if="activeTab === 'online-assistance'"
            :online-assistance-types="onlineAssistanceTypes"
            @confirm-delete="confirmDelete"
        />

        <ExpenseCategoriesTab
            v-if="activeTab === 'expense-categories'"
            :expense-categories="expenseCategories"
            @confirm-delete="confirmDelete"
        />

        <PsaRatesTab
            v-if="activeTab === 'psa-rates'"
            :psa-rates="psaRates"
            @confirm-delete="confirmDelete"
        />

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
