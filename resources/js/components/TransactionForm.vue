<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/transactions';
import type { Transaction } from '@/types/transaction';

defineProps<{
    action: string;
    method: 'post' | 'put';
    submitLabel: string;
    transaction?: Transaction;
}>();
</script>

<template>
    <Form
        :action="action"
        :method="method"
        class="space-y-6"
        v-slot="{ errors, processing }"
    >
        <div class="grid gap-6 md:grid-cols-2">
            <div class="grid gap-2">
                <Label for="transaction_date">Transaction date</Label>
                <Input
                    id="transaction_date"
                    name="transaction_date"
                    type="date"
                    :default-value="transaction?.transaction_date"
                    required
                />
                <InputError :message="errors.transaction_date" />
            </div>

            <div class="grid gap-2">
                <Label for="customer_name">Customer or person</Label>
                <Input
                    id="customer_name"
                    name="customer_name"
                    :default-value="transaction?.customer_name"
                    required
                    autocomplete="name"
                    placeholder="e.g. Person 1"
                />
                <InputError :message="errors.customer_name" />
            </div>

            <div class="grid gap-2">
                <Label for="pax">Passengers (pax)</Label>
                <Input
                    id="pax"
                    name="pax"
                    type="number"
                    min="1"
                    step="1"
                    :default-value="transaction?.pax"
                    required
                    placeholder="e.g. 1"
                />
                <InputError :message="errors.pax" />
            </div>

            <div class="grid gap-2">
                <Label for="provider">Airline or provider</Label>
                <Input
                    id="provider"
                    name="provider"
                    :default-value="transaction?.provider ?? ''"
                    placeholder="e.g. Cebu Pacific"
                />
                <InputError :message="errors.provider" />
            </div>

            <div class="grid gap-2 md:col-span-2">
                <Label for="service_details">Additional service or details</Label>
                <Input
                    id="service_details"
                    name="service_details"
                    :default-value="transaction?.service_details ?? ''"
                    placeholder="e.g. Additional 20kg"
                />
                <InputError :message="errors.service_details" />
            </div>

            <div class="grid gap-2">
                <Label for="amount">Total amount (PHP)</Label>
                <Input
                    id="amount"
                    name="amount"
                    type="number"
                    min="0.01"
                    step="0.01"
                    inputmode="decimal"
                    :default-value="transaction?.amount"
                    required
                    placeholder="0.00"
                />
                <p class="text-xs text-muted-foreground">
                    Enter the amount in Philippine pesos.
                </p>
                <InputError :message="errors.amount" />
            </div>

            <div class="grid gap-2 md:col-span-2">
                <Label for="notes">Notes</Label>
                <textarea
                    id="notes"
                    name="notes"
                    rows="4"
                    class="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 flex w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50"
                    placeholder="Optional notes about this transaction"
                    :value="transaction?.notes ?? ''"
                ></textarea>
                <InputError :message="errors.notes" />
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 border-t pt-6">
            <Button type="submit" :disabled="processing">
                {{ submitLabel }}
            </Button>
            <Button variant="outline" as-child>
                <Link :href="index()">Cancel</Link>
            </Button>
        </div>
    </Form>
</template>
