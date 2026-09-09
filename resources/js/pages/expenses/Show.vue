<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Copy, HandCoins, Pencil, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import ExpenseController from '@/actions/App/Http/Controllers/ExpenseController';
import ReimburseExpenseModal from '@/components/ReimburseExpenseModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    formatExpenseDate,
    formatExpensePaymentMethod,
    formatRecurringFrequency,
} from '@/lib/expenseFormat';
import { formatPhp } from '@/lib/transactionFormat';
import {
    create as expenseCreate,
    edit as expenseEdit,
    index as expensesIndex,
} from '@/routes/expenses';
import type { Expense } from '@/types/expense';

const props = defineProps<{
    expense: Expense;
}>();

const reimburseModalOpen = ref(false);

const page = usePage();
const currentUser = computed(() => page.props.auth?.user);

const canModify = computed(() => {
    if (!currentUser.value) {
        return false;
    }

    if (
        currentUser.value.role === 'admin' ||
        currentUser.value.role === 'tech_admin'
    ) {
        return true;
    }

    const isOwner = props.expense.user_id === currentUser.value.id;
    const createdDate = props.expense.created_at
        ? new Date(props.expense.created_at).toDateString()
        : '';
    const todayDate = new Date().toDateString();

    return isOwner && createdDate === todayDate;
});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Expenses', href: expensesIndex() },
            {
                title: 'Expense details',
                href: '#',
            },
        ],
    },
});
</script>

<template>
    <Head :title="`Expense: ${props.expense.title}`" />

    <main class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
        <!-- Header & Action Buttons -->
        <section class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-sm font-medium text-primary">
                        Expense details
                    </p>
                    <Badge variant="secondary" v-if="props.expense.category">
                        {{ props.expense.category.name }}
                    </Badge>
                    <Badge
                        v-if="
                            props.expense.is_personal &&
                            props.expense.reimbursement_status === 'pending'
                        "
                        variant="outline"
                        class="border-amber-500 bg-amber-500/10 text-amber-700 dark:text-amber-300"
                    >
                        Personal (Pending Reimbursement)
                    </Badge>
                    <Badge
                        v-else-if="
                            props.expense.is_personal &&
                            props.expense.reimbursement_status === 'reimbursed'
                        "
                        variant="outline"
                        class="border-emerald-500 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                    >
                        Personal (Reimbursed)
                    </Badge>
                </div>
                <h1
                    class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl"
                >
                    {{ props.expense.title }}
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Disbursed on
                    {{ formatExpenseDate(props.expense.expense_date) }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <!-- Reimburse Button for Pending Personal Expenses -->
                <Button
                    v-if="
                        props.expense.is_personal &&
                        props.expense.reimbursement_status === 'pending' &&
                        canModify
                    "
                    class="gap-1.5 bg-emerald-600 text-white hover:bg-emerald-700"
                    @click="reimburseModalOpen = true"
                >
                    <HandCoins class="size-4" />
                    <span>Record Reimbursement</span>
                </Button>

                <!-- Duplicate Button -->
                <Button variant="outline" as-child>
                    <Link
                        :href="
                            expenseCreate({
                                query: { duplicate_id: props.expense.id },
                            })
                        "
                    >
                        <Copy class="size-4" />
                        <span>Duplicate</span>
                    </Link>
                </Button>

                <!-- Edit Button -->
                <Button v-if="canModify" variant="outline" as-child>
                    <Link :href="expenseEdit(props.expense)">
                        <Pencil class="size-4" />
                        <span>Edit</span>
                    </Link>
                </Button>

                <!-- Delete Modal -->
                <Dialog v-if="canModify">
                    <DialogTrigger as-child>
                        <Button variant="destructive">
                            <Trash2 class="size-4" />
                            <span>Delete</span>
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <Form
                            :action="
                                ExpenseController.destroy(props.expense).url
                            "
                            method="delete"
                            v-slot="{ processing }"
                        >
                            <DialogHeader>
                                <DialogTitle>Delete this expense?</DialogTitle>
                                <DialogDescription>
                                    Are you sure you want to delete "{{
                                        props.expense.title
                                    }}"? This record will be removed from active
                                    lists and summary metrics.
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter class="mt-6 gap-2">
                                <DialogClose as-child>
                                    <Button
                                        variant="outline"
                                        :disabled="processing"
                                    >
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button
                                    variant="destructive"
                                    type="submit"
                                    :disabled="processing"
                                >
                                    {{
                                        processing
                                            ? 'Deleting...'
                                            : 'Delete expense'
                                    }}
                                </Button>
                            </DialogFooter>
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </section>

        <!-- Main Details Grid -->
        <section class="grid gap-6 md:grid-cols-2">
            <!-- Financial Card -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base font-semibold">
                        Financial Summary
                    </CardTitle>
                    <CardDescription>
                        Disbursement value and payment channel
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div>
                        <span class="text-xs font-medium text-muted-foreground">
                            Disbursement Amount
                        </span>
                        <div class="text-3xl font-extrabold text-foreground">
                            {{ formatPhp(props.expense.amount) }}
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 border-t pt-4">
                        <div>
                            <span
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Payment Method
                            </span>
                            <div class="mt-1 font-medium capitalize">
                                {{
                                    formatExpensePaymentMethod(
                                        props.expense.payment_method,
                                    )
                                }}
                            </div>
                        </div>

                        <div>
                            <span
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Payment Date
                            </span>
                            <div class="mt-1 font-medium">
                                {{
                                    formatExpenseDate(
                                        props.expense.expense_date,
                                    )
                                }}
                            </div>
                        </div>

                        <div>
                            <span
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Category
                            </span>
                            <div class="mt-1 font-medium">
                                {{ props.expense.category?.name ?? '—' }}
                            </div>
                        </div>

                        <div>
                            <span
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Recurring
                            </span>
                            <div class="mt-1 font-medium">
                                {{
                                    formatRecurringFrequency(
                                        props.expense.recurring_frequency,
                                    )
                                }}
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Payee & Reference Card -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base font-semibold">
                        Payee & Reference Details
                    </CardTitle>
                    <CardDescription>
                        Vendor identity and receipt documentation
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div>
                        <span class="text-xs font-medium text-muted-foreground">
                            Payee / Vendor
                        </span>
                        <div class="mt-1 text-base font-medium">
                            {{ props.expense.payee || '—' }}
                        </div>
                    </div>

                    <div class="border-t pt-4">
                        <span class="text-xs font-medium text-muted-foreground">
                            Receipt / Invoice Reference No.
                        </span>
                        <div class="mt-1 font-mono text-sm">
                            {{ props.expense.reference_number || '—' }}
                        </div>
                    </div>

                    <div
                        class="grid grid-cols-2 gap-4 border-t pt-4 text-xs text-muted-foreground"
                    >
                        <div>
                            <span>Recorded: </span>
                            <span>{{
                                props.expense.created_at
                                    ? new Date(
                                          props.expense.created_at,
                                      ).toLocaleDateString()
                                    : '—'
                            }}</span>
                        </div>
                        <div>
                            <span>Last Updated: </span>
                            <span>{{
                                props.expense.updated_at
                                    ? new Date(
                                          props.expense.updated_at,
                                      ).toLocaleDateString()
                                    : '—'
                            }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Personal Errand & Reimbursement Card -->
            <Card
                v-if="props.expense.is_personal"
                class="border-amber-200 md:col-span-2 dark:border-amber-900/50"
            >
                <CardHeader>
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <div>
                            <CardTitle
                                class="flex items-center gap-2 text-base font-semibold"
                            >
                                <HandCoins
                                    class="size-4 text-amber-600 dark:text-amber-400"
                                />
                                Personal Errand & Reimbursement Status
                            </CardTitle>
                            <CardDescription>
                                Personal or family funds disbursed using agency
                                accounts
                            </CardDescription>
                        </div>
                        <Badge
                            :variant="
                                props.expense.reimbursement_status ===
                                'reimbursed'
                                    ? 'default'
                                    : 'outline'
                            "
                            :class="
                                props.expense.reimbursement_status ===
                                'reimbursed'
                                    ? 'bg-emerald-600 text-white'
                                    : 'border-amber-500 bg-amber-500/10 text-amber-700 dark:text-amber-300'
                            "
                        >
                            {{
                                props.expense.reimbursement_status ===
                                'reimbursed'
                                    ? 'Reimbursed'
                                    : 'Pending Reimbursement'
                            }}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div
                        v-if="
                            props.expense.reimbursement_status === 'reimbursed'
                        "
                        class="grid grid-cols-1 gap-4 sm:grid-cols-3"
                    >
                        <div>
                            <span
                                class="text-xs font-medium text-muted-foreground"
                                >Date Repaid</span
                            >
                            <div class="mt-1 font-semibold text-foreground">
                                {{
                                    formatExpenseDate(
                                        props.expense.reimbursed_at,
                                    )
                                }}
                            </div>
                        </div>
                        <div>
                            <span
                                class="text-xs font-medium text-muted-foreground"
                                >Repayment Channel</span
                            >
                            <div
                                class="mt-1 font-semibold text-foreground capitalize"
                            >
                                {{
                                    formatExpensePaymentMethod(
                                        props.expense.reimbursement_method ||
                                            'cash',
                                    )
                                }}
                            </div>
                        </div>
                        <div>
                            <span
                                class="text-xs font-medium text-muted-foreground"
                                >Reimbursement Notes</span
                            >
                            <div class="mt-1 text-sm text-foreground">
                                {{ props.expense.reimbursement_notes || '—' }}
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        class="flex flex-wrap items-center justify-between gap-4 rounded-lg bg-amber-50/70 p-3.5 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-200"
                    >
                        <div>
                            <span class="font-medium">Repayment Pending: </span>
                            <span>
                                This disbursement was paid from agency funds and
                                is awaiting repayment from
                                {{ props.expense.payee || 'the person' }}.
                            </span>
                        </div>
                        <Button
                            v-if="canModify"
                            size="sm"
                            class="shrink-0 gap-1.5 bg-emerald-600 text-white hover:bg-emerald-700"
                            @click="reimburseModalOpen = true"
                        >
                            <HandCoins class="size-3.5" />
                            <span>Mark as Reimbursed</span>
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Notes Card -->
            <Card class="md:col-span-2" v-if="props.expense.notes">
                <CardHeader>
                    <CardTitle class="text-base font-semibold">Notes</CardTitle>
                </CardHeader>
                <CardContent>
                    <p
                        class="text-sm whitespace-pre-wrap text-muted-foreground"
                    >
                        {{ props.expense.notes }}
                    </p>
                </CardContent>
            </Card>
        </section>

        <Button variant="outline" class="w-fit" as-child>
            <Link :href="expensesIndex()">
                <ArrowLeft class="size-4" />
                <span>Back to expenses</span>
            </Link>
        </Button>

        <!-- Reimburse Modal -->
        <ReimburseExpenseModal
            v-model:open="reimburseModalOpen"
            :expense="props.expense"
        />
    </main>
</template>
