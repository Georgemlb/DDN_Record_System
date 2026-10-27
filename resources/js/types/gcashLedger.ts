export type GcashLedgerEntryType =
    | 'customer_payment'
    | 'remittance_cash_in'
    | 'remittance_cash_out'
    | 'government_disbursement'
    | 'expense_payment'
    | 'portal_reload'
    | 'online_assistance'
    | 'direct_payment'
    | 'direct_expense'
    | 'cash_transfer'
    | 'bank_transfer'
    | 'adjustment';

export interface GcashLedgerEntry {
    id: number;
    user_id: number | null;
    type: GcashLedgerEntryType;
    amount: string | number;
    running_balance: string | number;
    is_balance_anchor?: boolean;
    transaction_id: number | null;
    expense_id: number | null;
    reference_number: string | null;
    entry_date: string;
    sort_order?: number;
    can_move_up?: boolean;
    can_move_down?: boolean;
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

export interface GcashLedgerMetrics {
    current_balance: number;
    total_inflows_month: number;
    total_outflows_month: number;
    low_balance_threshold: number;
    is_low_balance: boolean;
}

export interface GcashLedgerFilterParams {
    search?: string;
    type?: string;
    date_from?: string;
    date_to?: string;
    sort?: string;
    direction?: 'asc' | 'desc';
}
