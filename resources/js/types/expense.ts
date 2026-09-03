export type ExpensePaymentMethod = 'cash' | 'gcash' | 'bank_transfer';

export type RecurringFrequency = 'none' | 'weekly' | 'monthly' | 'annually';

export type ExpenseCategory = {
    id: number;
    name: string;
    description?: string | null;
    is_active?: boolean;
    sort_order?: number;
};

export type Expense = {
    id: number;
    title: string;
    expense_category_id: number;
    category?: ExpenseCategory | null;
    amount: string | number;
    expense_date: string;
    payment_method: ExpensePaymentMethod;
    payee: string | null;
    reference_number: string | null;
    recurring_frequency: RecurringFrequency;
    notes: string | null;
    created_at?: string;
    updated_at?: string;
    deleted_at?: string | null;
};

export type ExpenseSummary = {
    total_expenses: number;
    total_cash: number;
    total_gcash: number;
    total_bank_transfer: number;
    top_category: {
        name: string;
        amount: number;
    } | null;
};

export type ExpenseFilters = {
    search?: string;
    category_id?: string | number;
    payment_method?: string;
    date?: string;
    date_from?: string;
    date_to?: string;
    sort?: string;
    direction?: 'asc' | 'desc';
};
