import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { AgreementData } from '@/types/agreement';
import { Link } from '@inertiajs/react';
import { AlertCircle, ArrowRight, Calendar, CheckCircle2, Clock, FileText, GitBranch, History, ShieldAlert } from 'lucide-react';

interface AgreementTimelineProps {
    partnerId: string;
    agreements: AgreementData[];
}

export function formatCurrency(amount: number | null | undefined): string {
    if (amount === null || amount === undefined) {
        return '-';
    }
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(amount);
}

export function getLifecycleBadgeVariant(status: string | null): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'active':
            return 'default';
        case 'draft':
            return 'secondary';
        case 'paid_off':
        case 'completed':
            return 'outline';
        case 'closed_by_rescheduling':
        case 'cancelled':
            return 'destructive';
        default:
            return 'outline';
    }
}

export function getCollectibilityBadgeVariant(status: string | null): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'lancar':
        case 'current':
            return 'outline';
        case 'kurang_lancar':
        case 'substandard':
        case 'diragukan':
            return 'secondary';
        case 'bermasalah':
        case 'loss':
            return 'destructive';
        case 'lunas':
            return 'default';
        case 'unknown':
        default:
            return 'outline';
    }
}

export function getSigningBadgeVariant(status: string | null): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'signed':
            return 'default';
        case 'draft':
        case 'awaiting_partner_signature':
        case 'awaiting_company_signature':
            return 'secondary';
        case 'not_prepared':
        default:
            return 'outline';
    }
}

export default function AgreementTimeline({ partnerId, agreements }: AgreementTimelineProps) {
    if (!agreements || agreements.length === 0) {
        return (
            <Card>
                <CardContent className="flex flex-col items-center justify-center p-8 text-center">
                    <FileText className="text-muted-foreground mb-3 h-10 w-10 stroke-1" />
                    <h3 className="text-foreground text-base font-semibold">Belum Ada Perjanjian</h3>
                    <p className="text-muted-foreground mt-1 max-w-sm text-sm">
                        Mitra ini belum memiliki riwayat nomor perjanjian pinjaman terdaftar di dalam sistem.
                    </p>
                </CardContent>
            </Card>
        );
    }

    return (
        <div className="relative space-y-6">
            {/* Vertical timeline connector line */}
            <div className="border-border/60 absolute top-4 bottom-4 left-6 hidden w-0.5 border-l md:block" />

            {agreements.map((agreement) => {
                const isDraft = agreement.is_draft;
                const balance = agreement.balance;

                return (
                    <div key={agreement.id} className="relative flex flex-col gap-4 md:flex-row md:gap-8">
                        {/* Timeline node icon */}
                        <div className="bg-background border-border relative z-10 hidden h-12 w-12 shrink-0 items-center justify-center rounded-full border shadow-xs md:flex">
                            {agreement.is_paid_off ? (
                                <CheckCircle2 className="text-primary h-5 w-5" />
                            ) : agreement.is_closed_by_rescheduling ? (
                                <History className="text-destructive h-5 w-5" />
                            ) : isDraft ? (
                                <Clock className="text-muted-foreground h-5 w-5" />
                            ) : (
                                <FileText className="text-primary h-5 w-5" />
                            )}
                        </div>

                        {/* Agreement Card */}
                        <Card className="flex-1 shadow-xs transition-shadow hover:shadow-md">
                            <CardHeader className="pb-3">
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <CardTitle className="font-mono text-lg font-bold">{agreement.agreement_number}</CardTitle>
                                            {agreement.batch_year && (
                                                <span className="text-muted-foreground bg-muted rounded px-1.5 py-0.5 text-xs font-medium">
                                                    Tahun {agreement.batch_year}
                                                </span>
                                            )}
                                        </div>
                                        {agreement.business_group && (
                                            <p className="text-muted-foreground mt-1 text-xs">
                                                Kelompok Usaha: <span className="text-foreground font-medium">{agreement.business_group}</span>
                                            </p>
                                        )}
                                    </div>

                                    <Button size="sm" variant="outline" asChild>
                                        <Link href={`/partners/${partnerId}/agreements/${agreement.id}`} className="inline-flex items-center gap-1.5">
                                            Detail Perjanjian <ArrowRight className="h-3.5 w-3.5" />
                                        </Link>
                                    </Button>
                                </div>

                                {/* Three Independent Status Dimensions (PRD §4 invariant 8) */}
                                <div className="border-border/60 bg-muted/20 mt-3 rounded-md border p-2.5">
                                    <span className="text-muted-foreground mb-1.5 block text-xs font-medium">
                                        3 Dimensi Status Independen (PRD §4 Invariant 8):
                                    </span>
                                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-3">
                                        {/* Dimension 1: Lifecycle */}
                                        <div className="flex flex-col gap-1">
                                            <span className="text-muted-foreground text-[10px] tracking-wider uppercase">Siklus (Lifecycle)</span>
                                            <div>
                                                <Badge variant={getLifecycleBadgeVariant(agreement.lifecycle_status)}>
                                                    {agreement.lifecycle_status_label}
                                                </Badge>
                                            </div>
                                        </div>

                                        {/* Dimension 2: Collectibility */}
                                        <div className="flex flex-col gap-1">
                                            <span className="text-muted-foreground text-[10px] tracking-wider uppercase">Kolektibilitas</span>
                                            <div>
                                                <Badge variant={getCollectibilityBadgeVariant(agreement.collectibility_status)}>
                                                    {agreement.collectibility_status_label}
                                                </Badge>
                                            </div>
                                        </div>

                                        {/* Dimension 3: Signing */}
                                        <div className="flex flex-col gap-1">
                                            <span className="text-muted-foreground text-[10px] tracking-wider uppercase">Penandatanganan</span>
                                            <div className="flex flex-wrap items-center gap-1">
                                                <Badge variant={getSigningBadgeVariant(agreement.signing_status)}>
                                                    {agreement.signing_status_label}
                                                </Badge>
                                                {agreement.signature_summary && (
                                                    <span className="text-muted-foreground text-xs">({agreement.signature_summary_label})</span>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </CardHeader>

                            <CardContent className="space-y-4 pt-0">
                                {/* Dates and Amounts */}
                                <div className="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                                    <div>
                                        <span className="text-muted-foreground flex items-center gap-1 text-xs">
                                            <Calendar className="h-3 w-3" /> Tgl Berlaku
                                        </span>
                                        <p className="text-foreground mt-0.5 font-medium">{agreement.effective_date ?? '-'}</p>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground flex items-center gap-1 text-xs">
                                            <Calendar className="h-3 w-3" /> Tgl Jatuh Tempo
                                        </span>
                                        <p className="text-foreground mt-0.5 font-medium">{agreement.maturity_date ?? '-'}</p>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground text-xs">Plafon Pokok</span>
                                        <p className="text-foreground mt-0.5 font-mono font-medium">{formatCurrency(agreement.principal_amount)}</p>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground text-xs">Total Pinjaman</span>
                                        <p className="text-foreground mt-0.5 font-mono font-semibold">{formatCurrency(agreement.total_amount)}</p>
                                    </div>
                                </div>

                                {/* Financial Balance Status Card (DEC-008 & PRD FR-02) */}
                                <div className="rounded-md border p-3 text-xs">
                                    {isDraft ? (
                                        <div className="text-muted-foreground flex items-center gap-2">
                                            <AlertCircle className="text-muted-foreground h-4 w-4 shrink-0" />
                                            <div>
                                                <span className="text-foreground font-semibold">Draft Perjanjian:</span> Belum menimbulkan kewajiban
                                                piutang. Angka saldo tidak aktif (PRD FR-02).
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:justify-between">
                                            <div className="flex items-center gap-2">
                                                <ShieldAlert className="h-4 w-4 shrink-0 text-amber-600 dark:text-amber-500" />
                                                <div>
                                                    <span className="text-foreground font-medium">Status Saldo Piutang:</span>{' '}
                                                    <span className="rounded bg-amber-100 px-1.5 py-0.5 font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                                        {balance?.label ?? 'Belum Terverifikasi'}
                                                    </span>
                                                </div>
                                            </div>
                                            <p className="text-muted-foreground text-[11px] italic">
                                                Perhitungan saldo ditangguhkan per DEC-008. Tidak menampilkan saldo nol palsu.
                                            </p>
                                        </div>
                                    )}
                                </div>

                                {/* Predecessor / Successor Links */}
                                {((agreement.predecessors && agreement.predecessors.length > 0) ||
                                    (agreement.successors && agreement.successors.length > 0)) && (
                                    <div className="border-border/60 bg-muted/10 space-y-2 rounded-md border p-3 text-xs">
                                        <div className="text-muted-foreground flex items-center gap-1.5 font-medium">
                                            <GitBranch className="h-3.5 w-3.5" /> Riwayat Keterkaitan Perjanjian:
                                        </div>

                                        {agreement.predecessors && agreement.predecessors.length > 0 && (
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="text-muted-foreground">Perjanjian Pendahulu:</span>
                                                {agreement.predecessors.map((pred) => (
                                                    <Link
                                                        key={pred.id}
                                                        href={`/partners/${partnerId}/agreements/${pred.id}`}
                                                        className="text-primary font-mono font-medium hover:underline"
                                                    >
                                                        {pred.agreement_number}
                                                    </Link>
                                                ))}
                                            </div>
                                        )}

                                        {agreement.successors && agreement.successors.length > 0 && (
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="text-muted-foreground">Perjanjian Penerus:</span>
                                                {agreement.successors.map((succ) => (
                                                    <Link
                                                        key={succ.id}
                                                        href={`/partners/${partnerId}/agreements/${succ.id}`}
                                                        className="text-primary font-mono font-medium hover:underline"
                                                    >
                                                        {succ.agreement_number}
                                                    </Link>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                );
            })}
        </div>
    );
}
