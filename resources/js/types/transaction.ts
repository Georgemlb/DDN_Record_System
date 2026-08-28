export type Transaction = {
    id: number;
    transaction_date: string;
    customer_name: string;
    pax: number;
    provider: string | null;
    service_details: string | null;
    amount: string;
    notes: string | null;
};
