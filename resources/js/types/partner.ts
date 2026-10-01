export interface PartnerAliasData {
    id: string;
    name_raw: string;
    name_normalized: string;
    state: 'unreviewed' | 'confirmed' | 'rejected' | string;
}

export interface VirtualAccountData {
    id: string;
    partner_id: string;
    va_number: string;
    provider: string | null;
    valid_from: string | null;
    valid_until: string | null;
    evidence: string | null;
    version: number;
    is_masked: boolean;
}

export interface PartnerData {
    id: string;
    partner_no_id: string | null;
    name: string;
    nik: string | null;
    phone: string | null;
    address: string | null;
    business_type: string | null;
    region: string | null;
    verification_state: 'unverified' | 'pending' | 'verified' | string;
    verification_badge_label: string;
    version: number;
    is_masked: boolean;
    aliases?: PartnerAliasData[];
    virtual_accounts?: VirtualAccountData[];
    agreements_count: number;
    created_at?: string;
    updated_at?: string;
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface PaginatedResponse<T> {
    data: T[];
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

export type SearchType = 'no_id' | 'name' | 'agreement' | 'va';

export interface PartnerSearchFilters {
    query?: string | null;
    type?: SearchType;
    per_page?: number;
}
