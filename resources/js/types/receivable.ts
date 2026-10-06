import { BalanceData } from './agreement';

export interface BankTransactionSourceCoordinates {
    id: string;
    reference: string | null;
    reference_normalized: string | null;
    transaction_datetime: string | null;
    amount: number;
    payer_name: string | null;
    payer_va: string | null;
    is_va_masked: boolean;
    source: string | null;
    source_row_identifier: string | null;
    source_row_number: number | null;
    provenance: string | null;
}

export interface PaymentAllocationDetailData {
    id: string;
    effective_date: string;
    period: string | null;
    principal_amount: number;
    interest_amount: number;
    admin_charge_amount: number;
    other_charge_amount: number;
    total_amount: number;
    state: string;
    evidence: string | null;
    idempotency_key: string | null;
    approved_source: string | null;
    bank_transaction: BankTransactionSourceCoordinates | null;
    approved_by?: {
        id: number;
        name: string;
    } | null;
}

export interface ReceivableAdjustmentDetailData {
    id: string;
    adjustment_type: string;
    principal_amount: number;
    interest_amount: number;
    admin_charge_amount: number;
    other_charge_amount: number;
    total_amount: number;
    effective_date: string;
    reason: string | null;
    evidence: string | null;
    state: string;
    approved_at: string | null;
    approved_by?: {
        id: number;
        name: string;
    } | null;
}

export interface InstallmentScheduleDetailData {
    id: string;
    installment_number: number;
    due_date: string;
    principal_due: number;
    interest_due: number;
    admin_charge_due: number;
    other_charge_due: number;
    total_due: number;
    principal_paid: number;
    interest_paid: number;
    admin_charge_paid: number;
    other_charge_paid: number;
    total_paid: number;
    outstanding: number;
    status: string;
}

export interface AgreementReceivableDetailData {
    id: string;
    agreement_number: string;
    agreement_number_normalized: string | null;
    batch_year: string | null;
    business_group: string | null;
    effective_date: string | null;
    maturity_date: string | null;
    tenor_months: number | null;
    source_row_number: number | null;
    lifecycle_status: string;
    lifecycle_status_label: string;
    collectibility_status: string;
    collectibility_status_label: string;
    late_months: number;
    data_verified: boolean;
    balance: BalanceData;
    payment_allocations: PaymentAllocationDetailData[];
    receivable_adjustments: ReceivableAdjustmentDetailData[];
    schedules: InstallmentScheduleDetailData[];
}

export interface ReceivableRollupData {
    contract_principal: number;
    contract_charge: number;
    contract_total: number;
    paid_principal: number;
    paid_charge: number;
    paid_total: number;
    adjustment_principal: number;
    adjustment_charge: number;
    adjustment_total: number;
    remaining_principal: number;
    remaining_charge: number;
    remaining_total: number;
    active_agreements_count: number;
    total_agreements_count: number;
    has_exception: boolean;
    warnings: string[];
    parked_funds_total: number;
}
