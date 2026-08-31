import type { PaymentMethod, TransactionType } from '@/types/transaction';

export const formatPhp = (
    amount: string | number | null | undefined,
): string => {
    if (amount === null || amount === undefined || amount === '') {
        return '₱0.00';
    }

    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(Number(amount));
};

export const formatTransactionDate = (date: string): string => {
    if (!date) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-PH', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${date}T00:00:00`));
};

export const formatTransactionType = (
    type: TransactionType | string,
): string => {
    const labels: Record<string, string> = {
        airline: 'Airline Ticket',
        translation: 'Document Translation',
        psa: 'PSA Assistance',
        online_assistance: 'Online Assistance',
        printing: 'Printing',
    };

    return labels[type] || type;
};

export const formatPaymentMethod = (method: PaymentMethod | string): string => {
    const labels: Record<string, string> = {
        cash: 'Cash',
        gcash: 'GCash',
    };

    return labels[method] || method;
};

export const getTodayDateString = (): string => {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};
