<script setup lang="ts">
import { Calculator, Minus, Plus } from '@lucide/vue';
import { ref } from 'vue';
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
import { formatPhp } from '@/lib/transactionFormat';
import type { PsaRate } from '@/types/transaction';

const showBreakdown = ref(false);

const copies = defineModel<number>('copies', { required: true });

defineProps<{
    errors: Record<string, string>;
    psaDocumentType: string;
    psaRates: PsaRate[];
    psaDocumentTypes: string[];
    psaDocumentTypeLabels: Record<string, string>;
    selectedPsaRate?: PsaRate | null;
    numAmount: number;
    numThirdParty: number;
    computedTranslationOrPsaDdnAmount: number;
    handlePsaDocumentTypeChange: (value: unknown) => void;
    handleCopiesInput: () => void;
    incrementCopies: () => void;
    decrementCopies: () => void;
}>();
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-3 md:col-span-2">
        <div class="space-y-2 sm:col-span-2">
            <Label for="psa_doc_select">PSA Document Type</Label>
            <Select
                :model-value="psaDocumentType"
                @update:model-value="handlePsaDocumentTypeChange"
            >
                <SelectTrigger id="psa_doc_select" class="w-full">
                    <SelectValue placeholder="Select PSA Document Type" />
                </SelectTrigger>
                <SelectContent>
                    <template v-if="psaRates.length > 0">
                        <SelectItem
                            v-for="rate in psaRates"
                            :key="rate.id"
                            :value="rate.document_type"
                        >
                            {{ rate.document_type }} — {{ rate.name }} ({{
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
                            {{ psaDocumentTypeLabels[docType] || docType }}
                        </SelectItem>
                    </template>
                </SelectContent>
            </Select>
            <InputError :message="errors.psa_document_type" />
        </div>

        <div class="space-y-2">
            <Label for="psa_copies">Number of Copies</Label>
            <div class="flex items-center space-x-1">
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    class="h-9 w-9 shrink-0"
                    :disabled="copies <= 1"
                    @click="decrementCopies"
                >
                    <Minus class="size-3.5" />
                </Button>
                <Input
                    id="psa_copies"
                    v-model.number="copies"
                    type="number"
                    min="1"
                    max="100"
                    class="h-9 text-center font-semibold"
                    @input="handleCopiesInput"
                />
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    class="h-9 w-9 shrink-0"
                    :disabled="copies >= 100"
                    @click="incrementCopies"
                >
                    <Plus class="size-3.5" />
                </Button>
            </div>
            <InputError :message="errors.copies" />
        </div>
    </div>

    <div class="rounded-lg border bg-muted/40 px-4 py-2.5 md:col-span-2">
        <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <div class="flex items-center gap-1.5">
                    <Calculator class="size-4 text-primary" />
                    <span class="text-muted-foreground">Customer Price:</span>
                    <span class="font-bold text-foreground">
                        {{ formatPhp(numAmount) }}
                    </span>
                </div>
                <span class="text-xs text-muted-foreground">
                    Net DDN:
                    <strong class="text-emerald-600 dark:text-emerald-400">{{
                        formatPhp(computedTranslationOrPsaDdnAmount)
                    }}</strong>
                    &middot; {{ copies }}
                    {{ copies === 1 ? 'copy' : 'copies' }}
                </span>
            </div>
            <button
                type="button"
                class="text-xs font-medium text-primary hover:underline"
                @click="showBreakdown = !showBreakdown"
            >
                {{ showBreakdown ? 'Hide breakdown' : 'Show breakdown' }}
            </button>
        </div>

        <div v-if="showBreakdown" class="mt-3 border-t pt-3">
            <div class="grid grid-cols-3 gap-2 text-center text-xs">
                <div class="rounded border bg-background p-2">
                    <span class="text-muted-foreground">Customer Price</span>
                    <p class="font-bold text-foreground">
                        {{ formatPhp(numAmount) }}
                    </p>
                    <span
                        v-if="copies > 1 && selectedPsaRate"
                        class="mt-0.5 block text-[10px] text-muted-foreground"
                    >
                        {{ copies }} &times;
                        {{ formatPhp(selectedPsaRate.price) }}
                    </span>
                </div>
                <div class="rounded border bg-background p-2">
                    <span class="text-muted-foreground">PSA Officer Fee</span>
                    <p class="font-semibold text-blue-600 dark:text-blue-400">
                        -{{ formatPhp(numThirdParty) }}
                    </p>
                    <span
                        v-if="copies > 1 && selectedPsaRate"
                        class="mt-0.5 block text-[10px] text-muted-foreground"
                    >
                        {{ copies }} &times;
                        {{ formatPhp(selectedPsaRate.officer_fee) }}
                    </span>
                </div>
                <div class="rounded border bg-background p-2">
                    <span class="text-muted-foreground">Net DDN Income</span>
                    <p
                        class="font-semibold text-emerald-600 dark:text-emerald-400"
                    >
                        {{ formatPhp(computedTranslationOrPsaDdnAmount) }}
                    </p>
                    <span
                        v-if="copies > 1 && selectedPsaRate"
                        class="mt-0.5 block text-[10px] text-muted-foreground"
                    >
                        {{ copies }} &times;
                        {{
                            formatPhp(
                                Number(selectedPsaRate.price) -
                                    Number(selectedPsaRate.officer_fee),
                            )
                        }}
                    </span>
                </div>
            </div>
            <p class="mt-2 text-center text-[11px] text-muted-foreground">
                Customer price and PSA officer payment are fixed according to
                branch configuration multiplied by number of copies.
            </p>
        </div>
        <InputError :message="errors.amount" />
        <InputError :message="errors.third_party_payment" />
        <InputError :message="errors.copies" />
    </div>
</template>
