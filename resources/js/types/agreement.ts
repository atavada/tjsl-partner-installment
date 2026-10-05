export interface BalanceData {
    agreement_id: string;
    status: 'unverified' | 'draft' | 'computed' | 'lunas' | 'exception' | string;
    label: string;
    as_of: string | null;
    policy_version: string | null;
    rule_version?: string;
    is_draft: boolean;
    creates_debt: boolean;
    data_verified?: boolean;
    is_lunas?: boolean;
    contract_principal?: number;
    contract_charge?: number;
    contract_total?: number;
    paid_principal?: number;
    paid_charge?: number;
    paid_total?: number;
    adjustment_principal?: number;
    adjustment_charge?: number;
    adjustment_total?: number;
    principal_remaining: number | 'unverified' | string;
    interest_remaining: number | 'unverified' | string;
    admin_charge_remaining: number | 'unverified' | string;
    other_charge_remaining: number | 'unverified' | string;
    charge_remaining?: number | 'unverified' | string;
    total_remaining: number | 'unverified' | string;
    included_event_ids: string[];
    warnings: string[];
}

export interface AgreementDocumentData {
    id: string;
    agreement_id: string;
    transition_id: string | null;
    file_name: string;
    mime_type: string;
    file_size_bytes: number;
    document_type: string;
    document_version: number;
    signing_status: string | null;
    signing_status_label: string | null;
    signature_summary: string | null;
    signature_summary_label: string | null;
    notes: string | null;
    created_at: string | null;
}

export interface AgreementLinkedSummary {
    id: string;
    agreement_number: string;
    effective_date: string | null;
    lifecycle_status: string | null;
    lifecycle_status_label: string | null;
    total_amount: number;
    transition_type?: string | null;
}

export interface AgreementTransitionData {
    id: string;
    predecessor_id: string;
    successor_id: string | null;
    transition_type: string | null;
    transition_type_label: string | null;
    effective_date: string | null;
    reason: string | null;
    approved_principal_amount: number | null;
    approved_interest_amount: number | null;
    approved_admin_charge_amount: number | null;
    approved_by: {
        id: number;
        name: string;
    } | null;
    approved_at: string | null;
    version: number;
    predecessor?: {
        id: string;
        agreement_number: string;
        partner_id: string;
    } | null;
    successor?: {
        id: string;
        agreement_number: string;
        partner_id: string;
    } | null;
    created_at?: string;
}

export interface AgreementStatusDimensions {
    lifecycle: {
        status: string | null;
        label: string | null;
        legacy: string | null;
    };
    collectibility: {
        status: string | null;
        label: string | null;
        legacy: string | null;
        late_months?: number;
        as_of?: string;
    };
    signing: {
        status: string | null;
        label: string | null;
        legacy: string | null;
        summary: string | null;
        summary_label: string | null;
    };
}

export interface AgreementData {
    id: string;
    partner_id: string;
    agreement_number: string;
    agreement_number_normalized: string | null;
    batch_year: string | null;
    business_group: string | null;
    source_row_number: number | null;
    tenor_months?: number | null;
    application_date: string | null;
    contract_date: string | null;
    effective_date: string | null;
    loan_start_date?: string | null;
    first_due_date?: string | null;
    maturity_date: string | null;
    principal_amount: number;
    interest_amount: number;
    admin_charge_amount: number;
    other_charge_amount: number;
    total_amount: number;
    interest_rate_percent?: string | number | null;
    status_dimensions: AgreementStatusDimensions;
    lifecycle_status: string;
    lifecycle_status_label: string;
    collectibility_status: string;
    collectibility_status_label: string;
    signing_status: string;
    signing_status_label: string;
    signature_summary: string;
    signature_summary_label: string;
    is_draft: boolean;
    is_paid_off: boolean;
    is_completed: boolean;
    is_closed_by_rescheduling: boolean;
    provenance: string;
    approved_source: string | null;
    approved_by: {
        id: number;
        name: string;
    } | null;
    approved_at: string | null;
    version: number;
    balance: BalanceData;
    partner?: {
        id: string;
        name: string;
        partner_no_id: string | null;
    } | null;
    documents?: AgreementDocumentData[];
    predecessors?: AgreementLinkedSummary[];
    successors?: AgreementLinkedSummary[];
    predecessor_transitions?: AgreementTransitionData[];
    successor_transitions?: AgreementTransitionData[];
    created_at: string | null;
    updated_at: string | null;
}
