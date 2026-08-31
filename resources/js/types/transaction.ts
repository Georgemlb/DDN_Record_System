export type TransactionType =
    'airline' | 'translation' | 'psa' | 'online_assistance' | 'printing';

export type PaymentMethod = 'cash' | 'gcash';

export type PsaDocumentType = 'BC' | 'MC' | 'DC' | 'Cenomar';

export type BaggageOption = {
    id: number;
    label: string;
    weight_kg: number;
    price: string | number;
    is_active?: boolean;
    sort_order?: number;
};

export type OnlineAssistanceType = {
    id: number;
    name: string;
    price: string | number;
    is_active?: boolean;
    sort_order?: number;
};

export type DdnSplitConfig = {
    ddn: number;
    employee: number;
};

export type Transaction = {
    id: number;
    type: TransactionType;
    payment_method: PaymentMethod;
    transaction_date: string;
    customer_name: string;
    pax: number | null;
    provider: string | null;
    service_details: string | null;
    baggage_option_id: number | null;
    baggage_option?: BaggageOption | null;
    psa_document_type: PsaDocumentType | null;
    online_assistance_type_id: number | null;
    online_assistance_type?: OnlineAssistanceType | null;
    amount: string | number;
    third_party_payment: string | number | null;
    ddn_amount: string | number | null;
    employee_share: string | number | null;
    notes: string | null;
};
