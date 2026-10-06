import { MetricDefinitionData } from './metric-definition';

export interface CollectibilityBandData {
    status: string;
    label: string;
    months_range: string;
    agreements_count: number;
    agreements_percentage: number;
    partners_count: number;
    partners_percentage: number;
    total_outstanding: number;
}

export interface CollectibilityBreakdownData {
    bands: CollectibilityBandData[];
    total_agreements: number;
    total_partners: number;
    total_outstanding: number;
    is_valid_sum: boolean;
}

export interface DashboardKpiData {
    active_agreements_count: number;
    active_partners_count: number;
    total_portfolio_partners_count: number;
    total_principal_outstanding: number;
    total_charge_outstanding: number;
    total_remaining_portfolio_balance: number;
    total_payments_collected_period: number;
    period_label: string;
    unallocated_abt_total: number;
    unallocated_abt_count: number;
    abt_unidentified_amount: number;
    abt_identified_unallocated_amount: number;
}

export interface DashboardFilters {
    as_of: string;
    batch_year: string | null;
    region: string | null;
}

export interface DashboardFilterOptions {
    batch_years: string[];
    regions: string[];
}

export interface DashboardMetricsData {
    as_of: string;
    filters: DashboardFilters;
    filter_options: DashboardFilterOptions;
    kpi: DashboardKpiData;
    collectibility: CollectibilityBreakdownData;
}

export interface DashboardPageProps {
    metrics: DashboardMetricsData;
    metric_definitions: MetricDefinitionData[];
}
