<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { AlertCircle, Banknote, Loader2, Smartphone } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDiscrepancy } from '@/lib/reportFormat';
import { formatPhp, formatTransactionDate } from '@/lib/transactionFormat';
import type {
    PastSettlementPreview,
    SettlementDraftMetrics,
} from '@/types/report';

defineProps<{
    todayDate: string;
}>();

const isPastModalOpen = ref(false);
const isLoadingPastPreview = ref(false);
const pastDateAlreadyExists = ref(false);
const pastPreviewError = ref<string | null>(null);
const pastPreviewMetrics = ref<SettlementDraftMetrics | null>(null);

const pastForm = useForm({
    settlement_date: '',
    opening_cash: 0 as number | string,
    opening_gcash: 0 as number | string,
    actual_cash: '' as string | number,
    actual_gcash: '' as string | number,
    notes: '',
});

const fetchPastPreview = async (date: string) => {
    if (!date) {
        pastPreviewMetrics.value = null;
        pastDateAlreadyExists.value = false;
        pastPreviewError.value = null;

        return;
    }

    isLoadingPastPreview.value = true;
    pastPreviewError.value = null;

    try {
        const response = await fetch(
            `/reports/settlements/preview?date=${date}`,
        );

        if (!response.ok) {
            throw new Error('Failed to load preview for date');
        }

        const data: PastSettlementPreview = await response.json();

        if (data.exists) {
            pastDateAlreadyExists.value = true;
            pastPreviewMetrics.value = null;
        } else {
            pastDateAlreadyExists.value = false;
            pastPreviewMetrics.value = data.metrics ?? null;
            pastForm.opening_cash = data.suggested_opening_cash ?? 0;
            pastForm.opening_gcash = data.suggested_opening_gcash ?? 0;
        }
    } catch {
        pastPreviewError.value =
            'Unable to fetch metrics preview for the selected date.';
        pastPreviewMetrics.value = null;
    } finally {
        isLoadingPastPreview.value = false;
    }
};

const open = () => {
    const d = new Date();
    d.setDate(d.getDate() - 1);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const yesterdayStr = `${y}-${m}-${day}`;

    pastForm.settlement_date = yesterdayStr;
    pastForm.opening_cash = 0;
    pastForm.opening_gcash = 0;
    pastForm.actual_cash = '';
    pastForm.actual_gcash = '';
    pastForm.notes = '';
    pastForm.clearErrors();
    pastDateAlreadyExists.value = false;
    pastPreviewError.value = null;
    pastPreviewMetrics.value = null;

    isPastModalOpen.value = true;
    fetchPastPreview(yesterdayStr);
};

watch(
    () => pastForm.settlement_date,
    (newDate) => {
        if (isPastModalOpen.value && newDate) {
            fetchPastPreview(newDate);
        }
    },
);

const pastExpectedCash = computed(() => {
    const opening = Number(pastForm.opening_cash) || 0;
    const cashIn = pastPreviewMetrics.value?.cash_in ?? 0;
    const cashOut = pastPreviewMetrics.value?.cash_out ?? 0;

    return opening + cashIn - cashOut;
});

const pastExpectedGcash = computed(() => {
    const opening = Number(pastForm.opening_gcash) || 0;
    const gcashIn = pastPreviewMetrics.value?.gcash_in ?? 0;
    const gcashOut = pastPreviewMetrics.value?.gcash_out ?? 0;

    return opening + gcashIn - gcashOut;
});

const livePastDiscrepancy = computed(() => {
    if (
        pastForm.actual_cash === '' ||
        pastForm.actual_cash === null ||
        isNaN(Number(pastForm.actual_cash))
    ) {
        return null;
    }

    return Number(pastForm.actual_cash) - pastExpectedCash.value;
});

const livePastGcashDiscrepancy = computed(() => {
    if (
        pastForm.actual_gcash === '' ||
        pastForm.actual_gcash === null ||
        isNaN(Number(pastForm.actual_gcash))
    ) {
        return null;
    }

    return Number(pastForm.actual_gcash) - pastExpectedGcash.value;
});

const submitPastSettlement = () => {
    if (pastDateAlreadyExists.value || !pastForm.settlement_date) {
        return;
    }

    pastForm.post('/reports/settlements/past', {
        preserveScroll: true,
        onSuccess: () => {
            isPastModalOpen.value = false;
        },
    });
};

defineExpose({ open });
</script>

<template>
    <Dialog v-model:open="isPastModalOpen">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Record Past Settlement</DialogTitle>
                <DialogDescription>
                    Backfill a missed cash register settlement for a previous
                    operating day.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submitPastSettlement" class="space-y-4">
                <div class="space-y-2">
                    <Label for="past_settlement_date">
                        Operating Date
                        <span class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="past_settlement_date"
                        v-model="pastForm.settlement_date"
                        type="date"
                        :max="todayDate"
                        required
                    />
                    <p
                        v-if="pastForm.errors.settlement_date"
                        class="text-xs text-destructive"
                    >
                        {{ pastForm.errors.settlement_date }}
                    </p>
                </div>

                <div
                    v-if="pastDateAlreadyExists"
                    class="flex items-center gap-2 rounded-lg border border-destructive/20 bg-destructive/10 p-3 text-xs text-destructive"
                >
                    <AlertCircle class="size-4 shrink-0" />
                    <span>
                        A settlement record already exists for
                        {{ formatTransactionDate(pastForm.settlement_date) }}.
                        You can view or edit it in Settlement History.
                    </span>
                </div>

                <div
                    v-else-if="pastPreviewError"
                    class="flex items-center gap-2 rounded-lg border border-destructive/20 bg-destructive/10 p-3 text-xs text-destructive"
                >
                    <AlertCircle class="size-4 shrink-0" />
                    <span>{{ pastPreviewError }}</span>
                </div>

                <div
                    v-else-if="isLoadingPastPreview"
                    class="flex items-center justify-center gap-2 py-6 text-sm text-muted-foreground"
                >
                    <Loader2 class="size-4 animate-spin" />
                    <span>Fetching date records & metrics...</span>
                </div>

                <div v-else-if="pastForm.settlement_date" class="space-y-4">
                    <div class="space-y-2 rounded-lg border bg-muted/40 p-3">
                        <div
                            class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            Recorded Activity for
                            {{
                                formatTransactionDate(pastForm.settlement_date)
                            }}
                        </div>
                        <div
                            class="grid grid-cols-2 gap-2 text-xs sm:grid-cols-4"
                        >
                            <div>
                                <span class="block text-muted-foreground"
                                    >Cash In:</span
                                >
                                <span
                                    class="font-medium text-emerald-600 dark:text-emerald-400"
                                >
                                    {{
                                        formatPhp(
                                            pastPreviewMetrics?.cash_in ?? 0,
                                        )
                                    }}
                                </span>
                            </div>
                            <div>
                                <span class="block text-muted-foreground"
                                    >Cash Out:</span
                                >
                                <span
                                    class="font-medium text-rose-600 dark:text-rose-400"
                                >
                                    {{
                                        formatPhp(
                                            pastPreviewMetrics?.cash_out ?? 0,
                                        )
                                    }}
                                </span>
                            </div>
                            <div>
                                <span class="block text-muted-foreground"
                                    >GCash In:</span
                                >
                                <span
                                    class="font-medium text-blue-600 dark:text-blue-400"
                                >
                                    {{
                                        formatPhp(
                                            pastPreviewMetrics?.gcash_in ?? 0,
                                        )
                                    }}
                                </span>
                            </div>
                            <div>
                                <span class="block text-muted-foreground"
                                    >GCash Out:</span
                                >
                                <span
                                    class="font-medium text-amber-600 dark:text-amber-400"
                                >
                                    {{
                                        formatPhp(
                                            pastPreviewMetrics?.gcash_out ?? 0,
                                        )
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div
                        class="space-y-3 rounded-lg border border-emerald-500/20 bg-emerald-500/5 p-4"
                    >
                        <div
                            class="flex items-center gap-1.5 text-xs font-semibold tracking-wider text-emerald-600 uppercase dark:text-emerald-400"
                        >
                            <Banknote class="size-4" />
                            <span>1. Physical Cash Drawer Reconciliation</span>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label
                                    for="past_opening_cash"
                                    class="text-xs font-medium"
                                >
                                    Opening Cash Float (₱)
                                    <span class="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="past_opening_cash"
                                    v-model="pastForm.opening_cash"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="0.00"
                                    required
                                />
                                <p
                                    v-if="pastForm.errors.opening_cash"
                                    class="text-xs text-destructive"
                                >
                                    {{ pastForm.errors.opening_cash }}
                                </p>
                            </div>

                            <div class="space-y-1.5">
                                <Label
                                    for="past_actual_cash"
                                    class="text-xs font-medium"
                                >
                                    Actual Counted Cash (₱)
                                    <span class="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="past_actual_cash"
                                    v-model="pastForm.actual_cash"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="0.00"
                                    required
                                />
                                <p
                                    v-if="pastForm.errors.actual_cash"
                                    class="text-xs text-destructive"
                                >
                                    {{ pastForm.errors.actual_cash }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="space-y-1 border-t border-emerald-500/10 pt-1 text-xs"
                        >
                            <div class="flex justify-between">
                                <span class="text-muted-foreground"
                                    >Expected Drawer Cash:</span
                                >
                                <span class="font-mono font-medium">{{
                                    formatPhp(pastExpectedCash)
                                }}</span>
                            </div>
                            <div
                                v-if="livePastDiscrepancy !== null"
                                class="flex items-center justify-between pt-1"
                            >
                                <span class="text-muted-foreground"
                                    >Discrepancy:</span
                                >
                                <Badge
                                    :variant="
                                        formatDiscrepancy(livePastDiscrepancy)
                                            .variant
                                    "
                                    class="text-xs"
                                >
                                    {{
                                        formatDiscrepancy(livePastDiscrepancy)
                                            .label
                                    }}
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <div
                        class="space-y-3 rounded-lg border border-blue-500/20 bg-blue-500/5 p-4"
                    >
                        <div
                            class="flex items-center gap-1.5 text-xs font-semibold tracking-wider text-blue-600 uppercase dark:text-blue-400"
                        >
                            <Smartphone class="size-4" />
                            <span
                                >2. GCash Wallet Reconciliation (Optional)</span
                            >
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label
                                    for="past_opening_gcash"
                                    class="text-xs font-medium"
                                >
                                    Opening GCash (₱)
                                </Label>
                                <Input
                                    id="past_opening_gcash"
                                    v-model="pastForm.opening_gcash"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="0.00"
                                />
                                <p
                                    v-if="pastForm.errors.opening_gcash"
                                    class="text-xs text-destructive"
                                >
                                    {{ pastForm.errors.opening_gcash }}
                                </p>
                            </div>

                            <div class="space-y-1.5">
                                <Label
                                    for="past_actual_gcash"
                                    class="text-xs font-medium"
                                >
                                    Actual Counted GCash (₱)
                                </Label>
                                <Input
                                    id="past_actual_gcash"
                                    v-model="pastForm.actual_gcash"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="0.00"
                                />
                                <p
                                    v-if="pastForm.errors.actual_gcash"
                                    class="text-xs text-destructive"
                                >
                                    {{ pastForm.errors.actual_gcash }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="space-y-1 border-t border-blue-500/10 pt-1 text-xs"
                        >
                            <div class="flex justify-between">
                                <span class="text-muted-foreground"
                                    >Expected GCash:</span
                                >
                                <span class="font-mono font-medium">{{
                                    formatPhp(pastExpectedGcash)
                                }}</span>
                            </div>
                            <div
                                v-if="livePastGcashDiscrepancy !== null"
                                class="flex items-center justify-between pt-1"
                            >
                                <span class="text-muted-foreground"
                                    >Discrepancy:</span
                                >
                                <Badge
                                    :variant="
                                        formatDiscrepancy(
                                            livePastGcashDiscrepancy,
                                        ).variant
                                    "
                                    class="text-xs"
                                >
                                    {{
                                        formatDiscrepancy(
                                            livePastGcashDiscrepancy,
                                        ).label
                                    }}
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="past_notes"
                            >Notes / Discrepancy Remarks</Label
                        >
                        <textarea
                            id="past_notes"
                            v-model="pastForm.notes"
                            rows="2"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                            placeholder="Explain any shortages, overages, or historical notes..."
                        />
                        <p
                            v-if="pastForm.errors.notes"
                            class="text-xs text-destructive"
                        >
                            {{ pastForm.errors.notes }}
                        </p>
                    </div>
                </div>

                <DialogFooter class="mt-6">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isPastModalOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        :disabled="
                            pastForm.processing ||
                            isLoadingPastPreview ||
                            pastDateAlreadyExists ||
                            !pastForm.settlement_date
                        "
                    >
                        Record Settlement
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
