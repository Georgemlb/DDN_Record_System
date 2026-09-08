<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TransactionController from '@/actions/App/Http/Controllers/TransactionController';
import TransactionForm from '@/components/TransactionForm.vue';
import { index } from '@/routes/transactions';
import type {
    BaggageOption,
    Customer,
    DdnSplitConfig,
    OnlineAssistanceType,
    PsaDocumentType,
} from '@/types/transaction';

defineProps<{
    baggageOptions?: BaggageOption[];
    onlineAssistanceTypes?: OnlineAssistanceType[];
    psaDocumentTypes?: PsaDocumentType[];
    ddnSplitConfig?: DdnSplitConfig;
    portalBalance?: number;
    recurringCustomers?: Customer[];
    initialCustomers?: Customer[];
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
                :baggage-options="baggageOptions"
                :online-assistance-types="onlineAssistanceTypes"
                :psa-document-types="psaDocumentTypes"
                :ddn-split-config="ddnSplitConfig"
                :portal-balance="portalBalance"
                :recurring-customers="recurringCustomers || initialCustomers"
            />
        </section>
    </main>
</template>
