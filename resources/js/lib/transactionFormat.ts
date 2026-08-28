export const formatPhp = (amount: string | number): string =>
    new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(Number(amount));

export const formatTransactionDate = (date: string): string =>
    new Intl.DateTimeFormat('en-PH', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${date}T00:00:00`));
