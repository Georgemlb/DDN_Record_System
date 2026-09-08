import type { ExpensePaymentMethod, RecurringFrequency } from '@/types/expense';

export const formatExpenseDate = (date?: string | null): string => {
    if (!date) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-PH', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${date}T00:00:00`));
};

export const formatExpensePaymentMethod = (
    method: ExpensePaymentMethod | string,
): string => {
    const labels: Record<string, string> = {
        cash: 'Cash',
        gcash: 'GCash',
        bank_transfer: 'Bank Transfer',
    };

    return labels[method] || method;
};

export const formatRecurringFrequency = (
    frequency: RecurringFrequency | string,
): string => {
    const labels: Record<string, string> = {
        none: 'One-time',
        weekly: 'Weekly',
        monthly: 'Monthly',
        annually: 'Annually',
    };

    return labels[frequency] || frequency;
};

export { getTodayDateString } from '@/lib/transactionFormat';
