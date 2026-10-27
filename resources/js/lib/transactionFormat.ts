import type {
    PaymentMethod,
    Transaction,
    TransactionType,
} from '@/types/transaction';

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
        ferry: 'Ferry Ticket',
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

export const getTransactionServiceDetail = (tx: Transaction): string => {
    switch (tx.type) {
        case 'airline': {
            const tripSuffix = tx.trip_type === 'round_trip' ? ', RT' : '';
            const providerStr = tx.return_provider
                ? `${tx.provider || '—'} / ${tx.return_provider}`
                : tx.provider;

            return providerStr
                ? `${providerStr} (${tx.pax ?? 1} pax${tripSuffix})`
                : `${tx.pax ?? 1} pax${tripSuffix}`;
        }
        case 'ferry': {
            const tripSuffix = tx.trip_type === 'round_trip' ? ', RT' : '';

            return tx.provider
                ? `${tx.provider} (${tx.pax ?? 1} pax${tripSuffix})`
                : `${tx.pax ?? 1} pax${tripSuffix}`;
        }
        case 'psa': {
            const copiesText =
                tx.copies && tx.copies > 1 ? ` (${tx.copies} copies)` : '';

            return tx.psa_document_type
                ? `Doc: ${tx.psa_document_type}${copiesText}`
                : 'PSA Assistance';
        }
        case 'online_assistance':
            return tx.online_assistance_type?.name ?? 'Online Assistance';
        case 'translation':
            return 'Document Translation';
        case 'printing':
            return 'Printing Service';
        default:
            return tx.service_details || '—';
    }
};

export const getTodayDateString = (): string => {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};
