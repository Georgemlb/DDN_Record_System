<script setup lang="ts">
import { Printer, RefreshCw } from '@lucide/vue';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { formatDateTime, formatDiscrepancy } from '@/lib/reportFormat';
import { formatPhp, formatTransactionDate } from '@/lib/transactionFormat';
import type { DailySettlement } from '@/types/report';

interface SlipData {
    settlement: DailySettlement;
    transactions: Array<{
        id: number;
        reference_number: string | null;
        customer_name: string;
        type: string;
        payment_method: string;
        payment_status?: string;
        amount: number | string;
        amount_paid?: number | string;
        balance?: number | string;
        ddn_amount: number | string | null;
    }>;
    balance_collections?: Array<{
        id: number;
        transaction_id: number;
        amount: number | string;
        payment_method: string;
        payment_date: string;
        notes?: string | null;
        transaction?: {
            id: number;
            reference_number: string | null;
            customer_name: string;
            type: string;
            transaction_date: string;
        } | null;
    }>;
    expenses: Array<{
        id: number;
        title: string;
        payment_method: string;
        amount: number | string;
        category?: { id: number; name: string } | null;
    }>;
    reimbursements?: Array<{
        id: number;
        title: string;
        amount: number | string;
        reimbursement_method: string;
        reimbursed_at?: string | null;
        payee?: string | null;
    }>;
}

const isSlipModalOpen = ref(false);
const isLoadingSlip = ref(false);
const slipData = ref<SlipData | null>(null);

const open = async (settlementId: number) => {
    isLoadingSlip.value = true;
    isSlipModalOpen.value = true;

    try {
        const response = await fetch(`/reports/settlements/${settlementId}`);

        if (!response.ok) {
            throw new Error('Failed to load settlement slip.');
        }

        slipData.value = await response.json();
    } catch {
        slipData.value = null;
    } finally {
        isLoadingSlip.value = false;
    }
};

const printSlip = () => {
    window.print();
};

defineExpose({ open });
</script>

<template>
    <Dialog v-model:open="isSlipModalOpen">
        <DialogContent
            class="max-h-[90vh] overflow-y-auto sm:max-w-xl print:h-auto print:w-full print:max-w-none print:overflow-visible print:border-none print:shadow-none"
        >
            <div v-if="isLoadingSlip" class="py-12 text-center">
                <RefreshCw
                    class="mx-auto size-6 animate-spin text-muted-foreground"
                />
                <p class="mt-2 text-xs text-muted-foreground">
                    Loading settlement slip...
                </p>
            </div>

            <div v-else-if="slipData" class="space-y-6">
                <div
                    id="printable-slip"
                    class="space-y-4 rounded-lg border bg-card p-6 print:border-none print:p-0"
                >
                    <div class="border-b pb-4 text-center">
                        <h2 class="text-lg font-bold tracking-tight">
                            DDN RECORD SYSTEM
                        </h2>
                        <p
                            class="text-xs tracking-wider text-muted-foreground uppercase"
                        >
                            Daily Cash Settlement Slip
                        </p>
                        <p class="mt-1 text-sm font-semibold">
                            {{
                                formatTransactionDate(
                                    slipData.settlement.settlement_date,
                                )
                            }}
                        </p>
                    </div>

                    <div
                        class="grid grid-cols-2 gap-2 border-b pb-3 text-xs text-muted-foreground"
                    >
                        <div>
                            <span>Opened By: </span>
                            <span class="font-medium text-foreground">{{
                                slipData.settlement.user?.name ?? 'Staff'
                            }}</span>
                            <div class="text-[10px]">
                                {{
                                    formatDateTime(
                                        slipData.settlement.opened_at,
                                    )
                                }}
                            </div>
                        </div>
                        <div class="text-right">
                            <span>Closed By: </span>
                            <span class="font-medium text-foreground">{{
                                slipData.settlement.closed_by_user?.name ??
                                'Admin'
                            }}</span>
                            <div class="text-[10px]">
                                {{
                                    formatDateTime(
                                        slipData.settlement.closed_at,
                                    )
                                }}
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2 text-xs">
                        <p
                            class="text-[11px] font-semibold tracking-wider text-foreground uppercase"
                        >
                            Cash Drawer Reconciliation
                        </p>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground"
                                >Opening Cash Float:</span
                            >
                            <span class="font-mono font-medium">{{
                                formatPhp(slipData.settlement.opening_cash)
                            }}</span>
                        </div>
                        <div
                            class="flex justify-between text-emerald-600 dark:text-emerald-400"
                        >
                            <span>+ Total Cash In (Sales):</span>
                            <span class="font-mono font-medium">{{
                                formatPhp(slipData.settlement.cash_in)
                            }}</span>
                        </div>
                        <div
                            class="flex justify-between text-rose-600 dark:text-rose-400"
                        >
                            <span>- Total Cash Out (Expenses):</span>
                            <span class="font-mono font-medium">{{
                                formatPhp(slipData.settlement.cash_out)
                            }}</span>
                        </div>
                        <div
                            class="flex justify-between border-t pt-2 text-sm font-semibold"
                        >
                            <span>Expected Drawer Cash:</span>
                            <span class="font-mono text-primary">{{
                                formatPhp(slipData.settlement.expected_cash)
                            }}</span>
                        </div>
                        <div
                            class="flex justify-between border-t pt-1 text-sm font-semibold"
                        >
                            <span>Actual Cash Counted:</span>
                            <span class="font-mono">{{
                                formatPhp(slipData.settlement.actual_cash)
                            }}</span>
                        </div>
                        <div
                            class="flex items-center justify-between border-t pt-2 font-semibold"
                        >
                            <span>Discrepancy (Over/Short):</span>
                            <Badge
                                :variant="
                                    formatDiscrepancy(
                                        slipData.settlement.discrepancy,
                                    ).variant
                                "
                                class="text-xs"
                            >
                                {{
                                    formatDiscrepancy(
                                        slipData.settlement.discrepancy,
                                    ).label
                                }}
                            </Badge>
                        </div>
                    </div>

                    <div
                        v-if="slipData.settlement.opening_gcash !== undefined"
                        class="space-y-2 border-t pt-2 text-xs"
                    >
                        <p
                            class="text-[11px] font-semibold tracking-wider text-blue-600 uppercase dark:text-blue-400"
                        >
                            GCash Wallet Reconciliation
                        </p>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground"
                                >Opening GCash:</span
                            >
                            <span class="font-mono font-medium">{{
                                formatPhp(
                                    slipData.settlement.opening_gcash ?? 0,
                                )
                            }}</span>
                        </div>
                        <div
                            class="flex justify-between text-emerald-600 dark:text-emerald-400"
                        >
                            <span>+ Total GCash In:</span>
                            <span class="font-mono font-medium">{{
                                formatPhp(slipData.settlement.gcash_in ?? 0)
                            }}</span>
                        </div>
                        <div
                            class="flex justify-between text-rose-600 dark:text-rose-400"
                        >
                            <span>- Total GCash Out:</span>
                            <span class="font-mono font-medium">{{
                                formatPhp(slipData.settlement.gcash_out ?? 0)
                            }}</span>
                        </div>
                        <div
                            class="flex justify-between border-t pt-2 text-sm font-semibold"
                        >
                            <span>Expected GCash Balance:</span>
                            <span
                                class="font-mono text-blue-600 dark:text-blue-400"
                                >{{
                                    formatPhp(
                                        slipData.settlement.expected_gcash ?? 0,
                                    )
                                }}</span
                            >
                        </div>
                        <div
                            v-if="slipData.settlement.actual_gcash !== null"
                            class="flex justify-between border-t pt-1 text-sm font-semibold"
                        >
                            <span>Actual GCash Counted:</span>
                            <span class="font-mono">{{
                                formatPhp(slipData.settlement.actual_gcash)
                            }}</span>
                        </div>
                        <div
                            v-if="
                                slipData.settlement.gcash_discrepancy !== null
                            "
                            class="flex items-center justify-between border-t pt-2 font-semibold"
                        >
                            <span>GCash Discrepancy:</span>
                            <Badge
                                :variant="
                                    formatDiscrepancy(
                                        slipData.settlement.gcash_discrepancy,
                                    ).variant
                                "
                                class="text-xs"
                            >
                                {{
                                    formatDiscrepancy(
                                        slipData.settlement.gcash_discrepancy,
                                    ).label
                                }}
                            </Badge>
                        </div>
                    </div>

                    <div
                        v-if="
                            slipData.balance_collections &&
                            slipData.balance_collections.length
                        "
                        class="space-y-1.5 border-t pt-2 text-xs"
                    >
                        <p
                            class="text-[11px] font-semibold tracking-wider text-emerald-700 uppercase dark:text-emerald-400"
                        >
                            Balance Collections (Prior Transactions)
                        </p>
                        <div
                            v-for="bc in slipData.balance_collections"
                            :key="bc.id"
                            class="flex justify-between text-[11px]"
                        >
                            <span class="text-muted-foreground">
                                {{
                                    bc.transaction?.customer_name ?? 'Customer'
                                }}
                                ({{ bc.payment_method }})
                            </span>
                            <span class="font-mono font-medium text-foreground">
                                {{ formatPhp(bc.amount) }}
                            </span>
                        </div>
                    </div>

                    <div
                        v-if="
                            slipData.reimbursements &&
                            slipData.reimbursements.length
                        "
                        class="space-y-1.5 border-t pt-2 text-xs"
                    >
                        <p
                            class="text-[11px] font-semibold tracking-wider text-amber-700 uppercase dark:text-amber-400"
                        >
                            Personal Reimbursements Collected
                        </p>
                        <div
                            v-for="r in slipData.reimbursements"
                            :key="r.id"
                            class="flex justify-between text-[11px]"
                        >
                            <span class="text-muted-foreground">
                                {{ r.title }} ({{ r.reimbursement_method }})
                            </span>
                            <span class="font-mono font-medium text-foreground">
                                {{ formatPhp(r.amount) }}
                            </span>
                        </div>
                    </div>

                    <div class="space-y-2 border-t pt-3 text-xs">
                        <p
                            class="text-[11px] font-semibold tracking-wider text-foreground uppercase"
                        >
                            Non-Cash Collections & Business Summary
                        </p>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground"
                                >GCash Payments:</span
                            >
                            <span
                                class="font-mono text-blue-600 dark:text-blue-400"
                                >{{
                                    formatPhp(slipData.settlement.total_gcash)
                                }}</span
                            >
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground"
                                >Bank Transfer Expenses:</span
                            >
                            <span class="font-mono">{{
                                formatPhp(
                                    slipData.settlement.total_bank_transfer,
                                )
                            }}</span>
                        </div>
                        <div class="flex justify-between font-medium">
                            <span>Total Gross Sales:</span>
                            <span class="font-mono">{{
                                formatPhp(slipData.settlement.total_sales)
                            }}</span>
                        </div>
                        <div class="flex justify-between font-medium">
                            <span>Total DDN Earnings:</span>
                            <span class="font-mono text-primary">{{
                                formatPhp(
                                    slipData.settlement.total_ddn_earnings,
                                )
                            }}</span>
                        </div>
                    </div>

                    <div
                        v-if="slipData.settlement.notes"
                        class="border-t pt-3 text-xs"
                    >
                        <p class="font-semibold text-foreground">
                            Closing Remarks:
                        </p>
                        <p class="mt-1 text-muted-foreground italic">
                            "{{ slipData.settlement.notes }}"
                        </p>
                    </div>
                </div>

                <DialogFooter class="print:hidden">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isSlipModalOpen = false"
                    >
                        Close
                    </Button>
                    <Button type="button" @click="printSlip">
                        <Printer class="mr-2 size-4" />
                        Print Slip
                    </Button>
                </DialogFooter>
            </div>
        </DialogContent>
    </Dialog>
</template>
