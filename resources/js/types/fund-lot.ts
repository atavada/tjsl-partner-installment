export type FundLotType = 'abt' | 'identified_unallocated' | 'excess';

export interface FundLotPartner {
    id: string;
    name: string;
    partner_no_id: string | null;
}

export interface FundLotUser {
    id: number;
    name: string;
}

export interface FundLotAgreement {
    id: string;
    agreement_number: string;
}

export interface FundLotData {
    id: string;
    bank_transaction_id: string;
    partner_id: string | null;
    partner?: FundLotPartner | null;
    lot_type: FundLotType;
    lot_type_label: string;
    amount: number;
    evidence: string | null;
    identified_by_id: number | null;
    identified_by?: FundLotUser | null;
    identified_at: string | null;
    identification_evidence: string | null;
    source_agreement_id: string | null;
    source_agreement?: FundLotAgreement | null;
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
