<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import GcashIcon from '@/components/icons/GcashIcon.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
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
import type { OnlineAssistanceType } from '@/types/transaction';

defineProps<{
    onlineAssistanceTypes: OnlineAssistanceType[];
}>();

const emit = defineEmits<{
    confirmDelete: [
        id: number,
        title: string,
        endpoint: string,
        description: string,
    ];
}>();

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
        ? item.disbursement_amount !== null
            ? Number(item.disbursement_amount)
            : null
        : null;
    assistanceForm.sort_order = item.sort_order ?? 0;
    assistanceForm.is_active = item.is_active ?? true;
    isAssistanceModalOpen.value = true;
};

const modalAssistanceMargin = computed(() => {
    const price = Number(assistanceForm.price) || 0;
    const hasDisb = Boolean(assistanceForm.has_disbursement);
    const disb = hasDisb ? Number(assistanceForm.disbursement_amount) || 0 : 0;

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
</script>

<template>
    <section class="space-y-4">
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
                            <th class="p-4 text-center font-medium">Status</th>
                            <th class="p-4 text-right font-medium">Actions</th>
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
                            <td class="p-4 text-right align-middle font-medium">
                                {{ formatPhp(item.price) }}
                            </td>
                            <td class="p-4 text-center align-middle">
                                <div
                                    v-if="Boolean(item.has_disbursement)"
                                    class="inline-flex items-center gap-1.5 text-xs font-medium text-blue-600 dark:text-blue-400"
                                >
                                    <GcashIcon class="size-3.5" />
                                    <span>{{
                                        formatPhp(item.disbursement_amount || 0)
                                    }}</span>
                                </div>
                                <Badge
                                    v-else
                                    variant="outline"
                                    class="border-dashed text-muted-foreground"
                                >
                                    No Disbursement
                                </Badge>
                            </td>
                            <td class="p-4 text-right align-middle">
                                <div
                                    class="font-semibold text-emerald-600 dark:text-emerald-400"
                                >
                                    {{
                                        formatPhp(
                                            getAssistanceNetShares(item).ddn,
                                        )
                                    }}
                                </div>
                                <div class="text-[11px] text-muted-foreground">
                                    Emp 40%:
                                    {{
                                        formatPhp(
                                            getAssistanceNetShares(item)
                                                .employee,
                                        )
                                    }}
                                </div>
                            </td>
                            <td class="p-4 text-center align-middle">
                                <Badge
                                    :variant="
                                        item.is_active ? 'default' : 'secondary'
                                    "
                                >
                                    {{ item.is_active ? 'Active' : 'Inactive' }}
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
                                            emit(
                                                'confirmDelete',
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

                        <div
                            class="space-y-3 rounded-lg border bg-muted/30 p-3"
                        >
                            <div class="flex items-start space-x-2">
                                <input
                                    type="checkbox"
                                    id="assistance-disbursement"
                                    v-model="assistanceForm.has_disbursement"
                                    class="peer mt-0.5 size-4 shrink-0 rounded-sm border-input bg-background text-primary shadow-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                />
                                <div class="space-y-0.5">
                                    <Label
                                        for="assistance-disbursement"
                                        class="cursor-pointer font-medium"
                                    >
                                        Requires Government Fee Disbursement
                                    </Label>
                                    <p class="text-xs text-muted-foreground">
                                        Enable if this service involves paying a
                                        government/third-party fee (e.g., NBI or
                                        Police Clearance via GCash). Disable if
                                        this service has no government
                                        disbursement.
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="Boolean(assistanceForm.has_disbursement)"
                                class="space-y-1.5 border-t pt-3"
                            >
                                <Label
                                    for="assistance-disbursement-amount"
                                    class="flex items-center gap-1.5 text-xs font-semibold text-blue-600 dark:text-blue-400"
                                >
                                    <GcashIcon class="size-3.5" />
                                    <span
                                        >Disbursement Amount (Paid via
                                        GCash)</span
                                    >
                                </Label>
                                <Input
                                    id="assistance-disbursement-amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    :model-value="
                                        assistanceForm.disbursement_amount ??
                                        undefined
                                    "
                                    @update:model-value="
                                        (value) =>
                                            (assistanceForm.disbursement_amount =
                                                value === ''
                                                    ? null
                                                    : Number(value))
                                    "
                                    placeholder="e.g. 160.00"
                                />
                                <p class="text-[11px] text-muted-foreground">
                                    Default fee deducted from GCash when
                                    entering transactions. Staff can still
                                    adjust this on the transaction form.
                                </p>
                                <div
                                    v-if="
                                        assistanceForm.errors
                                            .disbursement_amount
                                    "
                                    class="text-xs text-destructive"
                                >
                                    {{
                                        assistanceForm.errors
                                            .disbursement_amount
                                    }}
                                </div>
                            </div>
                        </div>

                        <div
                            class="space-y-2 rounded-lg border bg-card p-3 text-xs"
                        >
                            <div
                                class="flex justify-between text-muted-foreground"
                            >
                                <span>Base Price:</span>
                                <span class="font-medium text-foreground">{{
                                    formatPhp(assistanceForm.price || 0)
                                }}</span>
                            </div>
                            <div
                                v-if="Boolean(assistanceForm.has_disbursement)"
                                class="flex justify-between text-muted-foreground"
                            >
                                <span>Disbursement (GCash):</span>
                                <span
                                    class="font-medium text-blue-600 dark:text-blue-400"
                                >
                                    -
                                    {{
                                        formatPhp(
                                            assistanceForm.disbursement_amount ||
                                                0,
                                        )
                                    }}
                                </span>
                            </div>
                            <div
                                class="flex justify-between border-t pt-1.5 font-semibold"
                            >
                                <span>Net Service Margin:</span>
                                <span class="text-foreground">{{
                                    formatPhp(modalAssistanceMargin)
                                }}</span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 border-t pt-1">
                                <div class="rounded bg-muted/50 p-2">
                                    <p
                                        class="text-[10px] text-muted-foreground"
                                    >
                                        DDN Office Share (60%)
                                    </p>
                                    <p
                                        class="text-sm font-bold text-emerald-600 dark:text-emerald-400"
                                    >
                                        {{
                                            formatPhp(
                                                modalAssistanceMargin * 0.6,
                                            )
                                        }}
                                    </p>
                                </div>
                                <div class="rounded bg-muted/50 p-2">
                                    <p
                                        class="text-[10px] text-muted-foreground"
                                    >
                                        Employee Cut (40%)
                                    </p>
                                    <p
                                        class="text-sm font-bold text-foreground"
                                    >
                                        {{
                                            formatPhp(
                                                modalAssistanceMargin * 0.4,
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center space-x-2 pt-1">
                            <input
                                type="checkbox"
                                id="assistance-active"
                                v-model="assistanceForm.is_active"
                                class="peer size-4 shrink-0 rounded-sm border-input bg-background text-primary shadow-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
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
    </section>
</template>
