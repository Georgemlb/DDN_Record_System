export type TransactionType =
    | 'airline'
    | 'ferry'
    | 'translation'
    | 'psa'
    | 'online_assistance'
    | 'printing';

export type PaymentMethod = 'cash' | 'gcash' | 'split';

export type PaymentStatus = 'paid' | 'partial' | 'unpaid';

export type FlightType = 'local' | 'international';

export type TripType = 'one_way' | 'round_trip';

export type CarrierCategory = 'budget' | 'full_service';

export type ServiceType = 'flight' | 'ferry';

export type MarkupType = 'fixed' | 'percent';

export type PsaDocumentType = 'BC' | 'MC' | 'DC' | 'Cenomar';

export type Customer = {
    id: number;
    name: string;
    contact_number?: string | null;
    email?: string | null;
    notes?: string | null;
    transactions_count?: number;
    total_spent?: number | string | null;
    outstanding_balance?: number | string | null;
    created_at?: string;
};

export type TransactionPayment = {
    id: number;
    transaction_id: number;
    user_id?: number | null;
    amount: string | number;
    payment_method: PaymentMethod;
    cash_amount?: string | number | null;
    gcash_amount?: string | number | null;
    payment_date: string;
    notes?: string | null;
    created_at?: string;
    user?: {
        id: number;
        name: string;
    } | null;
};

export type BaggageOption = {
    id: number;
    airline?: string | null;
    label: string;
    weight_kg: number;
    is_active?: boolean;
    sort_order?: number;
};

export type AirlineMarkup = {
    id: number;
    service_type: ServiceType;
    airline: string;
    flight_type?: FlightType | null;
    markup_type: MarkupType;
    markup_amount: string | number;
    is_active?: boolean;
};

export type OnlineAssistanceType = {
    id: number;
    name: string;
    price: string | number;
    is_active?: boolean;
    sort_order?: number;
};

export type PsaRate = {
    id: number;
    document_type: string;
    name: string;
    price: string | number;
    officer_fee: string | number;
    net_income?: number;
    is_active?: boolean;
    sort_order?: number;
};

export type DdnSplitConfig = {
    ddn: number;
    employee: number;
};

export type Transaction = {
    id: number;
    user_id?: number | null;
    customer_id?: number | null;
    customer?: Customer | null;
    reference_number?: string | null;
    type: TransactionType;
    payment_method: PaymentMethod;
    payment_status: PaymentStatus;
    transaction_date: string;
    customer_name: string;
    pax: number | null;
    trip_type?: TripType | null;
    provider: string | null;
    flight_type: FlightType | null;
    carrier_category?: CarrierCategory | null;
    service_details: string | null;
    baggage_option_id: number | null;
    baggage_option?: BaggageOption | null;
    psa_document_type: PsaDocumentType | null;
    online_assistance_type_id: number | null;
    online_assistance_type?: OnlineAssistanceType | null;
    amount: string | number;
    amount_paid: string | number;
    balance: string | number;
    cash_amount: string | number | null;
    gcash_amount: string | number | null;
    third_party_payment: string | number | null;
    ddn_amount: string | number | null;
    employee_share: string | number | null;
    notes: string | null;
    deleted_at?: string | null;
    created_at?: string;
    updated_at?: string;
    user?: {
        id: number;
        name: string;
        email: string;
    } | null;
    payments?: TransactionPayment[];
};

export type TransactionFilters = {
    search?: string;
    type?: string;
    payment_method?: string;
    payment_status?: string;
    date?: string;
    date_from?: string;
    date_to?: string;
    sort?: string;
    direction?: string;
    status?: 'active' | 'trash';
};

export type TransactionSummary = {
    total_payments: number;
    total_ddn: number;
    total_cash: number;
    total_gcash: number;
    total_balance?: number;
};

export type TransactionCounts = {
    active: number;
    trash: number;
    unpaid_or_partial?: number;
};
