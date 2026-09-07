export type FlightPortalDepositType =
    'top_up' | 'ticket_deduction' | 'adjustment';

export type FlightPortalPaymentSource = 'cash' | 'gcash' | 'bank_transfer';

export interface FlightPortalDeposit {
    id: number;
    user_id: number | null;
    type: FlightPortalDepositType;
    amount: string | number;
    running_balance: string | number;
    transaction_id: number | null;
    expense_id: number | null;
    reference_number: string | null;
    payment_source: FlightPortalPaymentSource | null;
    entry_date: string;
    notes: string | null;
    created_at: string;
    updated_at: string;
    user?: {
        id: number;
        name: string;
    } | null;
    transaction?: {
        id: number;
        reference_number: string;
        customer_name: string;
    } | null;
    expense?: {
        id: number;
        title: string;
        amount: string | number;
    } | null;
}

export interface FlightPortalMetrics {
    current_balance: number;
    total_top_ups_month: number;
    total_deductions_month: number;
    low_balance_threshold: number;
    is_low_balance: boolean;
}

export interface FlightPortalFilterParams {
    search?: string;
    type?: string;
    date_from?: string;
    date_to?: string;
    sort?: string;
    direction?: 'asc' | 'desc';
}
