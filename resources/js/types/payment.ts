export interface PaymentAllocationData {
    id: string;
    bank_transaction_id: string;
    agreement_id: string;
    agreement_number?: string;
    principal_amount: number;
    interest_amount: number;
    admin_charge_amount: number;
    other_charge_amount: number;
    total_amount: number;
    effective_date: string | null;
    period: string | null;
    state: 'draft' | 'submitted' | 'posted' | 'reversed' | string;
    state_label: string;
    evidence: string | null;
    reversal_of_id: string | null;
    reason: string | null;
    approved_by?: {
        id: number;
        name: string;
    } | null;
    approved_at: string | null;
    created_at: string | null;
}

export interface OverpaymentData {
    id: string;
    partner_id: string | null;
    unapplied_amount: number;
    proposed_disposition: string | null;
    disposition_status: string;
    created_at: string | null;
}

export interface PaymentData {
    id: string;
    reference: string | null;
    reference_normalized: string | null;
    reference_namespace: string | null;
    transaction_datetime: string | null;
    timezone: string;
    amount: number;
    payer_name: string | null;
    payer_va: string | null;
    payer_va_raw: string | null;
    source: string | null;
    source_row_identifier: string | null;
    fingerprint: string | null;
    idempotency_key: string;
    state: 'draft' | 'submitted' | 'posted' | 'reversed' | string;
    state_label: string;
    receipt_month: string | null;
    provenance: string | null;
    notes: string | null;
    recorded_by: {
        id: number;
        name: string;
    } | null;
    version: number;
    allocations: PaymentAllocationData[];
    overpayments: OverpaymentData[];
    overpayment_amount: number;
    created_at: string | null;
    updated_at: string | null;
}
