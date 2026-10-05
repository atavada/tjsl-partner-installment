import { formatCurrency, getCollectibilityBadgeVariant, getLifecycleBadgeVariant, getSigningBadgeVariant } from '@/components/AgreementTimeline';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { AgreementData } from '@/types/agreement';
import { PartnerData } from '@/types/partner';
import { Head, Link } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, Calendar, Coins, FileCheck2, FileText, GitBranch, Info, Receipt, ShieldAlert } from 'lucide-react';

interface ShowProps {
    partner: PartnerData;
    agreement: AgreementData;
}

export default function Show({ partner, agreement }: ShowProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Dashboard',
            href: '/dashboard',
        },
        {
            title: 'Daftar Mitra',
            href: '/partners',
        },
        {
            title: partner.name,
            href: `/partners/${partner.id}`,
        },
        {
            title: 'Riwayat Perjanjian',
            href: `/partners/${partner.id}/agreements`,
        },
        {
            title: agreement.agreement_number,
            href: `/partners/${partner.id}/agreements/${agreement.id}`,
        },
    ];

    const isDraft = agreement.is_draft;
    const balance = agreement.balance;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Perjanjian ${agreement.agreement_number} - ${partner.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header section */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-foreground font-mono text-2xl font-bold tracking-tight">{agreement.agreement_number}</h1>
                            <Badge variant={getLifecycleBadgeVariant(agreement.lifecycle_status)}>{agreement.lifecycle_status_label}</Badge>
                            {agreement.batch_year && (
                                <span className="bg-muted text-muted-foreground rounded px-2 py-0.5 text-xs font-medium">
                                    Tahun {agreement.batch_year}
                                </span>
                            )}
                        </div>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Mitra: <span className="text-foreground font-semibold">{partner.name}</span> (NO ID:{' '}
                            {partner.partner_no_id ?? 'Belum ada'}){agreement.business_group && ` • Kelompok Usaha: ${agreement.business_group}`}
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button variant="outline" asChild>
                            <Link href={`/partners/${partner.id}/agreements/${agreement.id}/payments`} className="inline-flex items-center gap-1.5">
                                <Receipt className="h-4 w-4" /> Pembayaran
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={`/partners/${partner.id}/agreements`} className="inline-flex items-center gap-1.5">
                                <ArrowLeft className="h-4 w-4" /> Kembali ke Garis Waktu
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Draft Banner if draft */}
                {isDraft && (
                    <div className="border-border/80 bg-muted/30 text-foreground flex items-center gap-3 rounded-lg border p-4">
                        <AlertCircle className="text-primary h-5 w-5 shrink-0" />
                        <div className="text-sm">
                            <span className="font-semibold">Status Perjanjian Draft:</span> Perjanjian ini berstatus draft dan belum aktif. Sesuai PRD
                            FR-02, perjanjian draft <strong>tidak menimbulkan kewajiban piutang</strong> dan tidak memiliki jadwal angsuran aktif.
                        </div>
                    </div>
                )}

                {/* Three Independent Status Dimensions (PRD §4 invariant 8) */}
                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Info className="text-primary h-4 w-4" /> Tiga Dimensi Status Independen (PRD §4 Invariant 8)
                        </CardTitle>
                        <CardDescription>
                            Perubahan pada satu dimensi status tidak mempengaruhi dimensi lainnya ataupun saldo keuangan.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            {/* Dimension 1: Lifecycle */}
                            <div className="bg-muted/30 rounded-lg border p-3.5">
                                <span className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                                    1. Dimensi Siklus Hidup (Lifecycle)
                                </span>
                                <div className="mt-2 flex items-center gap-2">
                                    <Badge variant={getLifecycleBadgeVariant(agreement.lifecycle_status)} className="text-xs">
                                        {agreement.lifecycle_status_label}
                                    </Badge>
                                    <code className="text-muted-foreground font-mono text-xs">({agreement.lifecycle_status})</code>
                                </div>
                                <p className="text-muted-foreground mt-2 text-xs">
                                    {agreement.is_closed_by_rescheduling
                                        ? 'Perjanjian ditutup karena digantikan oleh perjanjian rescheduling penerus (bukan pelunasan kas).'
                                        : agreement.is_paid_off
                                          ? 'Perjanjian telah lunas setelah seluruh komponen terverifikasi nol.'
                                          : agreement.is_completed
                                            ? 'Perjanjian telah selesai secara kontraktual (berakhir masa berlakunya).'
                                            : isDraft
                                              ? 'Draft pengajuan perjanjian; aktivasi memerlukan persetujuan dan verifikasi.'
                                              : 'Perjanjian berjalan aktif.'}
                                </p>
                                {agreement.status_dimensions.lifecycle.legacy && (
                                    <p className="text-muted-foreground mt-1 text-[11px] italic">
                                        Data historis: {agreement.status_dimensions.lifecycle.legacy}
                                    </p>
                                )}
                            </div>

                            {/* Dimension 2: Collectibility */}
                            <div className="bg-muted/30 rounded-lg border p-3.5">
                                <span className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                                    2. Dimensi Kolektibilitas (Risiko)
                                </span>
                                <div className="mt-2 flex items-center gap-2">
                                    <Badge variant={getCollectibilityBadgeVariant(agreement.collectibility_status)} className="text-xs">
                                        {agreement.collectibility_status_label}
                                    </Badge>
                                    <code className="text-muted-foreground font-mono text-xs">({agreement.collectibility_status})</code>
                                </div>
                                <p className="text-muted-foreground mt-2 text-xs">
                                    Klasifikasi risiko kredit per tanggal laporan. Tidak mengubah nilai pokok atau jadwal pinjaman.
                                </p>
                                {agreement.status_dimensions.collectibility.legacy && (
                                    <p className="text-muted-foreground mt-1 text-[11px] italic">
                                        Data historis: {agreement.status_dimensions.collectibility.legacy}
                                    </p>
                                )}
                            </div>

                            {/* Dimension 3: Signing */}
                            <div className="bg-muted/30 rounded-lg border p-3.5">
                                <span className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                                    3. Dimensi Penandatanganan Dokumen
                                </span>
                                <div className="mt-2 flex flex-wrap items-center gap-2">
                                    <Badge variant={getSigningBadgeVariant(agreement.signing_status)} className="text-xs">
                                        {agreement.signing_status_label}
                                    </Badge>
                                    {agreement.signature_summary && (
                                        <Badge variant="outline" className="text-xs">
                                            {agreement.signature_summary_label}
                                        </Badge>
                                    )}
                                </div>
                                <p className="text-muted-foreground mt-2 text-xs">Status alur verifikasi tanda tangan basah/elektronik para pihak.</p>
                                {agreement.status_dimensions.signing.legacy && (
                                    <p className="text-muted-foreground mt-1 text-[11px] italic">
                                        Data historis: {agreement.status_dimensions.signing.legacy}
                                    </p>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Financial Amounts & Balance Section */}
                <div className="grid gap-6 md:grid-cols-2">
                    {/* Approved Loan Amounts */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Coins className="text-primary h-4 w-4" /> Nilai Plafon & Biaya Perjanjian
                            </CardTitle>
                            <CardDescription>Komponen pokok pinjaman dan biaya yang disetujui dalam kontrak.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span className="text-muted-foreground text-xs">Plafon Pokok</span>
                                    <p className="text-foreground font-mono font-medium">{formatCurrency(agreement.principal_amount)}</p>
                                </div>
                                <div>
                                    <span className="text-muted-foreground text-xs">Jasa / Bunga</span>
                                    <p className="text-foreground font-mono font-medium">{formatCurrency(agreement.interest_amount)}</p>
                                </div>
                                <div>
                                    <span className="text-muted-foreground text-xs">Biaya Administrasi</span>
                                    <p className="text-foreground font-mono font-medium">{formatCurrency(agreement.admin_charge_amount)}</p>
                                </div>
                                <div>
                                    <span className="text-muted-foreground text-xs">Biaya Lain-lain</span>
                                    <p className="text-foreground font-mono font-medium">{formatCurrency(agreement.other_charge_amount)}</p>
                                </div>
                            </div>

                            <div className="border-border/60 bg-muted/20 flex items-center justify-between rounded-md border p-3">
                                <span className="text-foreground text-sm font-semibold">Total Kewajiban Awal</span>
                                <span className="text-primary font-mono text-base font-bold">{formatCurrency(agreement.total_amount)}</span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Balance Stub (DEC-008 & PRD FR-02) */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <ShieldAlert className="h-4 w-4 text-amber-500" /> Saldo Piutang Berjalan (DEC-008)
                            </CardTitle>
                            <CardDescription>Sisa saldo piutang berjalan per tanggal peninjauan.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {isDraft ? (
                                <div className="border-border/60 bg-muted/20 space-y-2 rounded-md border p-4 text-xs">
                                    <div className="text-foreground flex items-center gap-2 font-medium">
                                        <AlertCircle className="text-muted-foreground h-4 w-4" /> Status Draft — Belum Ada Saldo
                                    </div>
                                    <p className="text-muted-foreground">
                                        Perjanjian ini berstatus draft dan belum menimbulkan kewajiban piutang resmi (PRD FR-02). Angka saldo tidak
                                        aktif hingga perjanjian diaktivasi melalui persetujuan resmi.
                                    </p>
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    <div className="flex items-start gap-3 rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-900 dark:text-amber-200">
                                        <ShieldAlert className="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
                                        <div>
                                            <p className="font-semibold">
                                                Status Saldo:{' '}
                                                <code className="rounded bg-amber-200/50 px-1 dark:bg-amber-900/50">
                                                    {balance?.status ?? 'unverified'}
                                                </code>{' '}
                                                ({balance?.label ?? 'Belum Terverifikasi'})
                                            </p>
                                            <p className="mt-1 text-[11px]">
                                                Sesuai DEC-008, rumus perhitungan saldo ditangguhkan sampai ada persetujuan aturan resmi. Sistem
                                                secara sengaja tidak menampilkan angka nol palsu.
                                            </p>
                                        </div>
                                    </div>

                                    <div className="divide-border/60 divide-y rounded-md border text-xs">
                                        <div className="flex items-center justify-between p-2.5">
                                            <span className="text-muted-foreground">Sisa Pokok:</span>
                                            <Badge variant="outline" className="font-mono text-amber-700 dark:text-amber-300">
                                                {balance?.principal_remaining ?? 'unverified'}
                                            </Badge>
                                        </div>
                                        <div className="flex items-center justify-between p-2.5">
                                            <span className="text-muted-foreground">Sisa Jasa / Bunga:</span>
                                            <Badge variant="outline" className="font-mono text-amber-700 dark:text-amber-300">
                                                {balance?.interest_remaining ?? 'unverified'}
                                            </Badge>
                                        </div>
                                        <div className="flex items-center justify-between p-2.5">
                                            <span className="text-muted-foreground">Sisa Biaya Admin:</span>
                                            <Badge variant="outline" className="font-mono text-amber-700 dark:text-amber-300">
                                                {balance?.admin_charge_remaining ?? 'unverified'}
                                            </Badge>
                                        </div>
                                        <div className="bg-muted/20 flex items-center justify-between p-2.5 font-semibold">
                                            <span className="text-foreground">Total Sisa Piutang:</span>
                                            <Badge variant="outline" className="font-mono text-amber-700 dark:text-amber-300">
                                                {balance?.total_remaining ?? 'unverified'}
                                            </Badge>
                                        </div>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Dates & Provenance */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Calendar className="text-primary h-4 w-4" /> Tanggal-tanggal Penting & Tata Kelola
                        </CardTitle>
                        <CardDescription>Jadwal tanggal kontrak dan metadata sumber data.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-2 gap-4 text-sm md:grid-cols-4">
                            <div>
                                <span className="text-muted-foreground text-xs">Tanggal Pengajuan</span>
                                <p className="text-foreground font-medium">{agreement.application_date ?? '-'}</p>
                            </div>
                            <div>
                                <span className="text-muted-foreground text-xs">Tanggal Akad / Kontrak</span>
                                <p className="text-foreground font-medium">{agreement.contract_date ?? '-'}</p>
                            </div>
                            <div>
                                <span className="text-muted-foreground text-xs">Tanggal Berlaku (Mulai)</span>
                                <p className="text-foreground font-medium">{agreement.effective_date ?? '-'}</p>
                            </div>
                            <div>
                                <span className="text-muted-foreground text-xs">Tanggal Jatuh Tempo</span>
                                <p className="text-foreground font-medium">{agreement.maturity_date ?? '-'}</p>
                            </div>
                            <div>
                                <span className="text-muted-foreground text-xs">Sumber Provenance</span>
                                <p className="text-foreground font-mono text-xs">{agreement.provenance ?? '-'}</p>
                            </div>
                            <div>
                                <span className="text-muted-foreground text-xs">Nomor Baris Sumber</span>
                                <p className="text-foreground font-mono text-xs">{agreement.source_row_number ?? '-'}</p>
                            </div>
                            <div>
                                <span className="text-muted-foreground text-xs">Disetujui Oleh</span>
                                <p className="text-foreground text-xs">
                                    {agreement.approved_by?.name ?? <span className="text-muted-foreground italic">Belum ada</span>}
                                </p>
                            </div>
                            <div>
                                <span className="text-muted-foreground text-xs">Tanggal Persetujuan</span>
                                <p className="text-foreground text-xs">
                                    {agreement.approved_at ?? <span className="text-muted-foreground italic">Belum ada</span>}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Predecessors / Successors Transitions */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <GitBranch className="text-primary h-4 w-4" /> Keterkaitan Predecessor & Successor
                        </CardTitle>
                        <CardDescription>Hubungan perjanjian pendahulu dan penerus (adendum, rescheduling, atau penutupan).</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {(!agreement.predecessors || agreement.predecessors.length === 0) &&
                        (!agreement.successors || agreement.successors.length === 0) &&
                        (!agreement.predecessor_transitions || agreement.predecessor_transitions.length === 0) &&
                        (!agreement.successor_transitions || agreement.successor_transitions.length === 0) ? (
                            <p className="text-muted-foreground text-sm italic">
                                Perjanjian ini merupakan perjanjian mandiri tanpa tautan adendum atau rescheduling.
                            </p>
                        ) : (
                            <div className="space-y-4 text-sm">
                                {/* Predecessors */}
                                {agreement.predecessors && agreement.predecessors.length > 0 && (
                                    <div className="space-y-2">
                                        <h4 className="text-foreground text-xs font-semibold uppercase">Perjanjian Pendahulu (Predecessors)</h4>
                                        <div className="divide-border/60 divide-y rounded-md border">
                                            {agreement.predecessors.map((pred) => (
                                                <div key={pred.id} className="flex items-center justify-between p-3">
                                                    <div>
                                                        <Link
                                                            href={`/partners/${partner.id}/agreements/${pred.id}`}
                                                            className="text-primary font-mono font-medium hover:underline"
                                                        >
                                                            {pred.agreement_number}
                                                        </Link>
                                                        <p className="text-muted-foreground text-xs">
                                                            Tgl Berlaku: {pred.effective_date ?? '-'} • Plafon: {formatCurrency(pred.total_amount)}
                                                        </p>
                                                    </div>
                                                    <Badge variant={getLifecycleBadgeVariant(pred.lifecycle_status)}>
                                                        {pred.lifecycle_status_label ?? pred.lifecycle_status}
                                                    </Badge>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                {/* Successors */}
                                {agreement.successors && agreement.successors.length > 0 && (
                                    <div className="space-y-2">
                                        <h4 className="text-foreground text-xs font-semibold uppercase">Perjanjian Penerus (Successors)</h4>
                                        <div className="divide-border/60 divide-y rounded-md border">
                                            {agreement.successors.map((succ) => (
                                                <div key={succ.id} className="flex items-center justify-between p-3">
                                                    <div>
                                                        <Link
                                                            href={`/partners/${partner.id}/agreements/${succ.id}`}
                                                            className="text-primary font-mono font-medium hover:underline"
                                                        >
                                                            {succ.agreement_number}
                                                        </Link>
                                                        <p className="text-muted-foreground text-xs">
                                                            Tgl Berlaku: {succ.effective_date ?? '-'} • Plafon: {formatCurrency(succ.total_amount)}
                                                        </p>
                                                    </div>
                                                    <Badge variant={getLifecycleBadgeVariant(succ.lifecycle_status)}>
                                                        {succ.lifecycle_status_label ?? succ.lifecycle_status}
                                                    </Badge>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Documents List */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <FileText className="text-primary h-4 w-4" /> Dokumen & Lampiran Perjanjian ({agreement.documents?.length ?? 0})
                        </CardTitle>
                        <CardDescription>Dokumen kontrak PDF versi tersimpan per DEC-003 dan DEC-004.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {!agreement.documents || agreement.documents.length === 0 ? (
                            <p className="text-muted-foreground text-sm italic">Belum ada berkas dokumen digital terunggah untuk perjanjian ini.</p>
                        ) : (
                            <div className="divide-border/60 divide-y rounded-md border text-sm">
                                {agreement.documents.map((doc) => (
                                    <div key={doc.id} className="flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div className="flex items-start gap-3">
                                            <FileCheck2 className="text-primary mt-0.5 h-4 w-4 shrink-0" />
                                            <div>
                                                <p className="text-foreground font-medium">{doc.file_name}</p>
                                                <p className="text-muted-foreground text-xs">
                                                    Tipe: {doc.document_type} • Versi: v{doc.document_version} •{' '}
                                                    {(doc.file_size_bytes / 1024).toFixed(1)} KB
                                                </p>
                                                {doc.notes && <p className="text-muted-foreground mt-1 text-xs italic">Catatan: {doc.notes}</p>}
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-2">
                                            {doc.signing_status && (
                                                <Badge variant={getSigningBadgeVariant(doc.signing_status)}>{doc.signing_status_label}</Badge>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
