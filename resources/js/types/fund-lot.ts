export type FundLotType = 'abt' | 'identified_unallocated' | 'excess' | 'allocated';

export interface FundLotAgreementItem {
    id: string;
    agreement_number: string;
    remaining_balance: number;
    principal_remaining: number;
}

export interface FundLotPartner {
    id: string;
    name: string;
    partner_no_id: string | null;
    total_remaining_debt?: number;
    active_agreements?: FundLotAgreementItem[];
}

export interface FundLotUser {
    id: number;
    name: string;
}

export interface FundLotAgreement {
    id: string;
    agreement_number: string;
}

export interface FundLotBankTransaction {
    id: string;
    reference: string | null;
    amount: number;
    payer_name: string | null;
    transaction_datetime: string | null;
    source: string | null;
}

export interface FundTransferData {
    id: string;
    amount: number;
    effective_date: string | null;
    reason: string | null;
    target_agreement: {
        id: string;
        agreement_number: string;
    } | null;
    actor: {
        id: number;
        name: string;
    } | null;
    linked_allocation_id: string | null;
    created_at: string | null;
}

export interface FundLotData {
    id: string;
    bank_transaction_id: string;
    bank_transaction?: FundLotBankTransaction | null;
    partner_id: string | null;
    partner?: FundLotPartner | null;
    lot_type: FundLotType;
    lot_type_label: string;
    amount: number;
    remaining_capacity: number;
    is_allocated: boolean;
    evidence: string | null;
    identified_by_id: number | null;
    identified_by?: FundLotUser | null;
    identified_at: string | null;
    identification_evidence: string | null;
    source_agreement_id: string | null;
    source_agreement?: FundLotAgreement | null;
    transfers?: FundTransferData[];
    idempotency_key: string;
    reason: string | null;
    version: number;
    created_at: string | null;
    updated_at: string | null;
}

export interface FundLotStats {
    total_abt_amount: number;
    total_abt_count: number;
    total_identified_amount: number;
    total_identified_count: number;
    total_excess_amount: number;
    total_excess_count: number;
    grand_total_amount: number;
    grand_total_count: number;
}

export interface FundLotFilters {
    lot_type?: FundLotType | null;
    search?: string | null;
    per_page?: number | null;
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface PaginatedFundLots {
    data: FundLotData[];
    current_page: number;
    first_page_url: string;
    from: number | null;
    last_page: number;
    last_page_url: string;
    links: PaginationLink[];
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
}
