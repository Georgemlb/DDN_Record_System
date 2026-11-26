<script setup lang="ts">
import { Printer } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    formatPaymentMethod,
    formatPhp,
    formatTransactionDate,
    formatTransactionType,
    getTransactionServiceDetail,
} from '@/lib/transactionFormat';
import type { Transaction } from '@/types/transaction';

const props = defineProps<{
    transaction: Transaction;
}>();

const open = defineModel<boolean>('open', { default: false });

const serviceDetail = computed(() =>
    getTransactionServiceDetail(props.transaction),
);

const remainingBalance = computed(() =>
    Math.max(0, Number(props.transaction.balance ?? 0)),
);

const amountPaid = computed(() =>
    Math.max(
        0,
        Number(props.transaction.amount_paid ?? props.transaction.amount ?? 0),
    ),
);

const printReceipt = () => {
    window.print();
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md print:border-none print:shadow-none">
            <DialogHeader class="no-print">
                <DialogTitle>Customer Receipt / Claim Stub</DialogTitle>
            </DialogHeader>

            <!-- Printable Receipt Body -->
            <div
                class="rounded-lg border bg-white p-5 text-slate-900 shadow-xs print:border-0 print:p-0 print:shadow-none"
            >
                <div
                    class="border-b border-dashed border-slate-300 pb-3 text-center"
                >
                    <p class="text-sm font-bold tracking-tight uppercase">
                        DDN Travel &amp; Ticketing Services
                    </p>
                    <p class="text-[11px] text-slate-500">
                        Official Acknowledgement Receipt &amp; Claim Stub
                    </p>
                </div>

                <div class="mt-3 space-y-1.5 text-xs">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Reference #:</span>
                        <span class="font-mono font-semibold">
                            {{
                                transaction.reference_number ??
                                `TX-${transaction.id}`
                            }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Date:</span>
                        <span>{{
                            formatTransactionDate(transaction.transaction_date)
                        }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Customer:</span>
                        <span class="font-semibold">{{
                            transaction.customer_name
                        }}</span>
                    </div>
                    <div
                        v-if="transaction.customer?.contact_number"
                        class="flex justify-between"
                    >
                        <span class="text-slate-500">Contact:</span>
                        <span>{{ transaction.customer.contact_number }}</span>
                    </div>
                    <div v-if="transaction.user" class="flex justify-between">
                        <span class="text-slate-500">Processed By:</span>
                        <span>{{ transaction.user.name }}</span>
                    </div>
                </div>

                <div
                    class="mt-3 space-y-1.5 border-t border-dashed border-slate-300 pt-3 text-xs"
                >
                    <div class="flex justify-between">
                        <span class="text-slate-500">Service:</span>
                        <span class="font-medium">{{
                            formatTransactionType(transaction.type)
                        }}</span>
                    </div>
                    <div
                        v-if="serviceDetail"
                        class="flex justify-between gap-4"
                    >
                        <span class="shrink-0 text-slate-500">Details:</span>
                        <span class="text-right font-medium">{{
                            serviceDetail
                        }}</span>
                    </div>
                    <div
                        v-if="transaction.copies && transaction.copies > 1"
                        class="flex justify-between"
                    >
                        <span class="text-slate-500">Copies:</span>
                        <span>{{ transaction.copies }}</span>
                    </div>
                    <div
                        v-if="transaction.pax && transaction.pax > 1"
                        class="flex justify-between"
                    >
                        <span class="text-slate-500">Passengers:</span>
                        <span>{{ transaction.pax }}</span>
                    </div>
                </div>

                <div
                    class="mt-3 space-y-1.5 border-t border-dashed border-slate-300 pt-3 text-xs tabular-nums"
                >
                    <div class="flex justify-between">
                        <span class="text-slate-500">Total Amount:</span>
                        <span class="font-semibold">{{
                            formatPhp(transaction.amount)
                        }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">
                            Paid ({{
                                formatPaymentMethod(transaction.payment_method)
                            }}):
                        </span>
                        <span class="font-semibold text-emerald-700">{{
                            formatPhp(amountPaid)
                        }}</span>
                    </div>
                    <div
                        class="flex justify-between border-t border-slate-200 pt-1.5 text-sm font-bold"
                    >
                        <span>Balance Due:</span>
                        <span
                            :class="
                                remainingBalance > 0
                                    ? 'text-rose-600'
                                    : 'text-emerald-700'
                            "
                        >
                            {{
                                remainingBalance > 0
                                    ? formatPhp(remainingBalance)
                                    : 'PAID IN FULL'
                            }}
                        </span>
                    </div>
                </div>

                <p
                    class="mt-4 border-t border-dashed border-slate-300 pt-3 text-center text-[10px] text-slate-500"
                >
                    Please present this stub when claiming documents or tickets.
                    Thank you for choosing DDN!
                </p>
            </div>

            <DialogFooter class="no-print gap-2 sm:justify-between">
                <Button type="button" variant="outline" @click="open = false">
                    Close
                </Button>
                <Button type="button" class="gap-1.5" @click="printReceipt">
                    <Printer class="size-4" />
                    <span>Print Receipt</span>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
