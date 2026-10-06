export interface MetricDefinitionData {
    id: string;
    code: string;
    name: string;
    version: string;
    formula_expression: string;
    description: string | null;
    numerator: string | null;
    denominator: string | null;
    date_semantics: string | null;
    included_population: string | null;
    excluded_population: string | null;
    decision_ref: string | null;
    is_active: boolean;
    approved_at: string | null;
    approved_by: string | null;
    created_at?: string;
    updated_at?: string;
}
