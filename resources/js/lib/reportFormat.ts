import { formatPhp } from './transactionFormat';

export interface DiscrepancyInfo {
    label: string;
    variant: 'default' | 'secondary' | 'destructive' | 'outline';
    colorClass: string;
    amount: number;
}

export const formatDiscrepancy = (
    discrepancy: number | string | null | undefined,
): DiscrepancyInfo => {
    if (
        discrepancy === null ||
        discrepancy === undefined ||
        discrepancy === ''
    ) {
        return {
            label: 'Not Closed',
            variant: 'outline',
            colorClass: 'text-muted-foreground',
            amount: 0,
        };
    }

    const val =
        typeof discrepancy === 'string' ? parseFloat(discrepancy) : discrepancy;

    if (Math.abs(val) < 0.01) {
        return {
            label: 'Balanced (₱0.00)',
            variant: 'secondary',
            colorClass: 'text-emerald-600 dark:text-emerald-400 font-semibold',
            amount: 0,
        };
    }

    if (val > 0) {
        return {
            label: `+${formatPhp(val)} Overage`,
            variant: 'default',
            colorClass: 'text-blue-600 dark:text-blue-400 font-semibold',
            amount: val,
        };
    }

    return {
        label: `-${formatPhp(Math.abs(val))} Shortage`,
        variant: 'destructive',
        colorClass: 'text-rose-600 dark:text-rose-400 font-semibold',
        amount: val,
    };
};

export const formatPeriodLabel = (period: string): string => {
    const labels: Record<string, string> = {
        today: 'Today',
        yesterday: 'Yesterday',
        this_week: 'This Week',
        this_month: 'This Month',
        last_month: 'Last Month',
        this_year: 'This Year',
        custom: 'Custom Range',
    };

    return labels[period] || period;
};

export const formatDateTime = (dateStr: string | null | undefined): string => {
    if (!dateStr) {
        return '—';
    }

    try {
        const d = new Date(dateStr);

        return new Intl.DateTimeFormat('en-PH', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        }).format(d);
    } catch {
        return dateStr;
    }
};
