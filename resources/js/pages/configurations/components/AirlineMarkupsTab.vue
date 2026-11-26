<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash } from '@lucide/vue';
import { computed, ref } from 'vue';
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
import type { AirlineMarkup } from '@/types/transaction';

const props = defineProps<{
    airlineMarkups: AirlineMarkup[];
}>();

const emit = defineEmits<{
    confirmDelete: [
        id: number,
        title: string,
        endpoint: string,
        description: string,
    ];
}>();

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
</script>

<template>
    <section class="space-y-4">
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
                            <th class="p-4 font-medium">Provider / Carrier</th>
                            <th class="p-4 font-medium">Category / Route</th>
                            <th class="p-4 font-medium">Markup Type</th>
                            <th class="p-4 text-right font-medium">
                                Markup Value
                            </th>
                            <th class="p-4 text-center font-medium">Status</th>
                            <th class="p-4 text-right font-medium">Actions</th>
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
                                <Badge variant="secondary" class="font-normal">
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
                                        markup.is_active ? 'Active' : 'Inactive'
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
                                            emit(
                                                'confirmDelete',
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
    </section>
</template>
