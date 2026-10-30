<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TransactionController from '@/actions/App/Http/Controllers/TransactionController';
import TransactionForm from '@/components/TransactionForm.vue';
import { index } from '@/routes/transactions';
import type {
    AirlineMarkup,
    Customer,
    DdnSplitConfig,
    OnlineAssistanceType,
    PsaDocumentType,
    PsaRate,
} from '@/types/transaction';

defineProps<{
    onlineAssistanceTypes?: OnlineAssistanceType[];
    airlineMarkups?: AirlineMarkup[];
    psaDocumentTypes?: PsaDocumentType[];
    psaRates?: PsaRate[];
    ddnSplitConfig?: DdnSplitConfig;
    portalBalance?: number;
    recurringCustomers?: Customer[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Transactions', href: index() },
            { title: 'Record transaction', href: '/transactions/create' },
        ],
    },
});
</script>

<template>
    <Head title="Record transaction" />

    <main class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
        <section>
            <p class="text-sm font-medium text-primary">Transactions</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">
                Record transaction
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Add a business transaction or service record.
            </p>
        </section>

        <section class="max-w-3xl rounded-lg border bg-card p-5 sm:p-6">
            <TransactionForm
                :action="TransactionController.store().url"
                method="post"
                submit-label="Save transaction"
                :online-assistance-types="onlineAssistanceTypes"
                :airline-markups="airlineMarkups"
                :psa-document-types="psaDocumentTypes"
                :psa-rates="psaRates"
                :ddn-split-config="ddnSplitConfig"
                :portal-balance="portalBalance"
                :recurring-customers="recurringCustomers"
            />
        </section>
    </main>
</template>
