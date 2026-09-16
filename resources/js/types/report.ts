export type DailySettlementStatus = 'open' | 'closed';

export type DailySettlement = {
    id: number;
    settlement_date: string;
    user_id: number;
    closed_by_user_id: number | null;
    status: DailySettlementStatus;
    opening_cash: number | string;
    cash_in: number | string;
    cash_out: number | string;
    expected_cash: number | string;
    actual_cash: number | string | null;
    discrepancy: number | string | null;
    opening_gcash?: number | string;
    gcash_in?: number | string;
    gcash_out?: number | string;
    expected_gcash?: number | string;
    actual_gcash?: number | string | null;
    gcash_discrepancy?: number | string | null;
    total_gcash: number | string;
    total_bank_transfer: number | string;
    total_sales: number | string;
    total_ddn_earnings: number | string;
    notes: string | null;
    opened_at: string | null;
    closed_at: string | null;
    created_at?: string;
    updated_at?: string;
    user?: {
        id: number;
        name: string;
        email: string;
    } | null;
    closed_by_user?: {
        id: number;
        name: string;
        email: string;
    } | null;
};

export type SettlementDraftMetrics = {
    cash_in: number;
    cash_out: number;
    gcash_in?: number;
    gcash_out?: number;
    total_gcash: number;
    total_bank_transfer: number;
    total_sales: number;
    total_ddn_earnings: number;
};

export type PastSettlementPreview = {
    exists: boolean;
    metrics?: SettlementDraftMetrics;
    suggested_opening_cash?: number;
    suggested_opening_gcash?: number;
};

export type FinancialSummary = {
    total_sales: number;
    total_ddn: number;
    total_expenses: number;
    operating_expenses?: number;
    portal_reloads?: number;
    net_profit: number;
    cash_sales: number;
    gcash_sales: number;
    cash_expenses: number;
    gcash_expenses: number;
    bank_expenses: number;
    transaction_count: number;
    expense_count: number;
};

export type ServiceBreakdownItem = {
    type: string;
    count: number;
    total_sales: number;
    total_ddn: number;
};

export type ExpenseBreakdownItem = {
    category: string;
    count: number;
    total: number;
};

export type ReportPeriod =
    | 'today'
    | 'yesterday'
    | 'this_week'
    | 'this_month'
    | 'last_month'
    | 'this_year'
    | 'custom';

export type DailySettlementsPageData = {
    todaySettlement: DailySettlement | null;
    todayDraftMetrics: SettlementDraftMetrics | null;
    suggestedOpeningFloat: number;
    suggestedOpeningGcash?: number;
    history: {
        data: DailySettlement[];
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        from: number | null;
        to: number | null;
    };
    todayDate: string;
};

export type FinancialReportsPageData = {
    period: ReportPeriod;
    startDate: string;
    endDate: string;
    summary: FinancialSummary;
    servicesBreakdown: ServiceBreakdownItem[];
    expensesBreakdown: ExpenseBreakdownItem[];
};
