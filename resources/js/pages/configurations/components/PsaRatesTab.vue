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
import type { PsaRate } from '@/types/transaction';

defineProps<{
    psaRates: PsaRate[];
}>();

const emit = defineEmits<{
    confirmDelete: [
        id: number,
        title: string,
        endpoint: string,
        description: string,
    ];
}>();

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
    <section class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold tracking-tight">
                    PSA Document Rates
                </h2>
                <p class="text-sm text-muted-foreground">
                    Configure fixed customer prices and PSA officer payouts for
                    PSA assistance (Birth, Marriage, Death, CENOMAR).
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
                            <th class="p-4 text-center font-medium">Status</th>
                            <th class="p-4 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="psaRates.length === 0">
                            <td
                                colspan="8"
                                class="p-8 text-center text-muted-foreground"
                            >
                                No PSA rates configured yet. Click "Add Rate" to
                                create one.
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
                            <td class="p-4 text-right align-middle font-medium">
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
                                        rate.is_active ? 'default' : 'secondary'
                                    "
                                >
                                    {{ rate.is_active ? 'Active' : 'Inactive' }}
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
                                            emit(
                                                'confirmDelete',
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
    </section>
</template>
