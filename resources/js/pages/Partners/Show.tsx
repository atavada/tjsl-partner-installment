import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { PartnerData } from '@/types/partner';
import { AgreementReceivableDetailData, ReceivableRollupData } from '@/types/receivable';
import { Head, Link } from '@inertiajs/react';
import { AlertCircle, AlertTriangle, ArrowLeft, Building2, CreditCard, FileText, Phone, Plus, ShieldCheck, TrendingUp, User } from 'lucide-react';
import { useState } from 'react';

interface ShowProps {
    partner: PartnerData;
    receivable_rollup: ReceivableRollupData;
    agreements_detail: AgreementReceivableDetailData[];
    as_of: string;
}

export default function Show({ partner, receivable_rollup, agreements_detail, as_of }: ShowProps) {
    const [activeTabByAgreement, setActiveTabByAgreement] = useState<Record<string, 'allocations' | 'adjustments' | 'schedules'>>({});

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
    ];

    const formatRupiah = (val: number): string => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(val);
    };

    const getVerificationBadgeVariant = (state: string) => {
        switch (state) {
            case 'verified':
                return 'default';
            case 'pending':
                return 'secondary';
            default:
                return 'outline';
        }
    };

    const getCollectibilityBadgeClass = (status: string) => {
        switch (status) {
            case 'lancar':
                return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800';
            case 'kurang_lancar':
                return 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-800';
            case 'diragukan':
                return 'bg-orange-500/10 text-orange-700 dark:text-orange-400 border-orange-200 dark:border-orange-800';
            case 'bermasalah':
                return 'bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-800';
            case 'lunas':
                return 'bg-blue-500/10 text-blue-700 dark:text-blue-400 border-blue-200 dark:border-blue-800';
            default:
                return 'bg-neutral-500/10 text-neutral-700 dark:text-neutral-400 border-neutral-200 dark:border-neutral-800';
        }
    };

    const setAgreementTab = (agreementId: string, tab: 'allocations' | 'adjustments' | 'schedules') => {
        setActiveTabByAgreement((prev) => ({
            ...prev,
            [agreementId]: tab,
        }));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Mitra - ${partner.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header section */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-foreground text-2xl font-bold tracking-tight">{partner.name}</h1>
                            <Badge variant={getVerificationBadgeVariant(partner.verification_state)}>{partner.verification_badge_label}</Badge>
                        </div>
                        <p className="text-muted-foreground mt-1 font-mono text-sm">
                            NO ID: {partner.partner_no_id ?? <span className="italic">Belum ada (Staging)</span>}
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button variant="outline" asChild>
                            <Link href={route('partners.index')} className="inline-flex items-center gap-1.5">
                                <ArrowLeft className="h-4 w-4" /> Kembali ke Pencarian
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href={`/partners/${partner.id}/agreements/create`} className="inline-flex items-center gap-1.5">
                                <Plus className="h-4 w-4" /> Buat Perjanjian
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Portfolio Receivable Rollup Card (FR-05 & Finding #23) */}
                <Card className="border-border">
                    <CardHeader className="pb-3">
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2 text-base font-semibold">
                                    <TrendingUp className="text-primary h-4.5 w-4.5" /> Ringkasan Piutang Portofolio Mitra (FR-05)
                                </CardTitle>
                                <CardDescription className="text-xs">
                                    Akumulasi kewajiban kontrak, realisasi pembayaran, penyesuaian, dan sisa piutang berjalan per tanggal {as_of}.
                                </CardDescription>
                            </div>
                            <div className="flex items-center gap-2 text-xs">
                                <span className="text-muted-foreground">Populasi:</span>
                                <Badge variant="secondary" className="font-mono text-xs">
                                    {receivable_rollup.active_agreements_count} Aktif / {receivable_rollup.total_agreements_count} Total Perjanjian
                                </Badge>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {/* Warnings or Integrity Alerts */}
                        {receivable_rollup.has_exception && (
                            <div className="flex items-start gap-2.5 rounded-lg border border-rose-200 bg-rose-500/10 p-3 text-xs text-rose-800 dark:border-rose-900/50 dark:text-rose-300">
                                <AlertTriangle className="h-4 w-4 shrink-0 text-rose-600 dark:text-rose-400" />
                                <div>
                                    <span className="font-semibold">Peringatan Integritas Keuangan (DEC-008, Invariant 7):</span>
                                    <p className="mt-0.5">
                                        Terdeteksi saldo bernilai negatif pada komponen piutang atau pembayaran melebihi kewajiban jadwal. Saldo tidak
                                        di-clamp ke nol secara diam-diam.
                                    </p>
                                </div>
                            </div>
                        )}

                        {receivable_rollup.warnings.length > 0 && !receivable_rollup.has_exception && (
                            <div className="flex items-start gap-2.5 rounded-lg border border-amber-200 bg-amber-500/10 p-3 text-xs text-amber-800 dark:border-amber-900/50 dark:text-amber-300">
                                <AlertCircle className="h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
                                <div>
                                    <span className="font-semibold">Catatan Integritas Piutang:</span>
                                    <ul className="mt-0.5 list-disc pl-4">
                                        {receivable_rollup.warnings.map((warn, i) => (
                                            <li key={i}>{warn}</li>
                                        ))}
                                    </ul>
                                </div>
                            </div>
                        )}

                        {/* Parked ABT Lot Notice */}
                        {receivable_rollup.parked_funds_total > 0 && (
                            <div className="flex items-center justify-between rounded-lg border border-blue-200 bg-blue-500/10 p-3 text-xs text-blue-800 dark:border-blue-900/50 dark:text-blue-300">
                                <div className="flex items-center gap-2">
                                    <CreditCard className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                    <span>
                                        Mitra memiliki dana mengendap (Identified ABT / Excess Lot) sebesar{' '}
                                        <strong className="font-mono">{formatRupiah(receivable_rollup.parked_funds_total)}</strong> yang siap
                                        dialokasikan ke perjanjian aktif.
                                    </span>
                                </div>
                                <Button size="sm" variant="outline" asChild className="h-7 text-xs">
                                    <Link href="/abt">Alokasikan ABT</Link>
                                </Button>
                            </div>
                        )}

                        {/* 4-Column Rollup Financial Summary Grid */}
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            {/* Plafon Kontrak */}
                            <div className="bg-muted/40 rounded-lg border p-3 text-xs">
                                <span className="text-muted-foreground font-medium">Plafon Kontrak Aktif</span>
                                <div className="text-foreground mt-1 text-lg font-bold">{formatRupiah(receivable_rollup.contract_total)}</div>
                                <div className="text-muted-foreground mt-2 space-y-0.5 border-t pt-2 text-[11px]">
                                    <div className="flex justify-between">
                                        <span>Pokok:</span>
                                        <span className="font-mono">{formatRupiah(receivable_rollup.contract_principal)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span>Jasa/Admin:</span>
                                        <span className="font-mono">{formatRupiah(receivable_rollup.contract_charge)}</span>
                                    </div>
                                </div>
                            </div>

                            {/* Total Pembayaran */}
                            <div className="bg-muted/40 rounded-lg border p-3 text-xs">
                                <span className="text-muted-foreground font-medium">Total Pembayaran (Posted)</span>
                                <div className="mt-1 text-lg font-bold text-emerald-700 dark:text-emerald-400">
                                    {formatRupiah(receivable_rollup.paid_total)}
                                </div>
                                <div className="text-muted-foreground mt-2 space-y-0.5 border-t pt-2 text-[11px]">
                                    <div className="flex justify-between">
                                        <span>Pokok:</span>
                                        <span className="font-mono">{formatRupiah(receivable_rollup.paid_principal)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span>Jasa/Admin:</span>
                                        <span className="font-mono">{formatRupiah(receivable_rollup.paid_charge)}</span>
                                    </div>
                                </div>
                            </div>

                            {/* Penyesuaian Piutang */}
                            <div className="bg-muted/40 rounded-lg border p-3 text-xs">
                                <span className="text-muted-foreground font-medium">Penyesuaian Piutang</span>
                                <div className="text-foreground mt-1 text-lg font-bold">{formatRupiah(receivable_rollup.adjustment_total)}</div>
                                <div className="text-muted-foreground mt-2 space-y-0.5 border-t pt-2 text-[11px]">
                                    <div className="flex justify-between">
                                        <span>Pokok:</span>
                                        <span className="font-mono">{formatRupiah(receivable_rollup.adjustment_principal)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span>Jasa/Admin:</span>
                                        <span className="font-mono">{formatRupiah(receivable_rollup.adjustment_charge)}</span>
                                    </div>
                                </div>
                            </div>

                            {/* Total Sisa Piutang Berjalan */}
                            <div className="bg-primary/5 border-primary/20 rounded-lg border p-3 text-xs">
                                <span className="text-primary font-medium">Sisa Piutang Berjalan</span>
                                <div className="text-primary mt-1 text-lg font-bold">{formatRupiah(receivable_rollup.remaining_total)}</div>
                                <div className="text-muted-foreground mt-2 space-y-0.5 border-t pt-2 text-[11px]">
                                    <div className="flex justify-between">
                                        <span>Pokok:</span>
                                        <span className="font-mono">{formatRupiah(receivable_rollup.remaining_principal)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span>Jasa/Admin:</span>
                                        <span className="font-mono">{formatRupiah(receivable_rollup.remaining_charge)}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Identity & Contact Cards (Original Section) */}
                <div className="grid gap-6 md:grid-cols-2">
                    {/* General Information Card */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <User className="text-primary h-4 w-4" /> Informasi Identitas
                            </CardTitle>
                            <CardDescription>Data identitas resmi mitra kemitraan.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span className="text-muted-foreground text-xs">NO ID Resmi</span>
                                    <p className="text-foreground font-mono font-medium">
                                        {partner.partner_no_id ?? <span className="text-muted-foreground italic">Belum ada</span>}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-muted-foreground text-xs">NIK{partner.is_masked ? ' (Masked)' : ''}</span>
                                    <p className="text-foreground font-mono font-medium">{partner.nik ?? '-'}</p>
                                </div>
                                <div>
                                    <span className="text-muted-foreground text-xs">Nama Lengkap</span>
                                    <p className="text-foreground font-medium">{partner.name}</p>
                                </div>
                                <div>
                                    <span className="text-muted-foreground text-xs">Status Verifikasi</span>
                                    <div className="mt-0.5">
                                        <Badge variant={getVerificationBadgeVariant(partner.verification_state)}>
                                            {partner.verification_badge_label}
                                        </Badge>
                                    </div>
                                </div>
                                <div>
                                    <span className="text-muted-foreground text-xs">Bidang Usaha</span>
                                    <p className="text-foreground font-medium">{partner.business_type ?? '-'}</p>
                                </div>
                                <div>
                                    <span className="text-muted-foreground text-xs">Wilayah / Daerah</span>
                                    <p className="text-foreground font-medium">{partner.region ?? '-'}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Contact & Address Card */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Phone className="text-primary h-4 w-4" /> Kontak & Alamat
                            </CardTitle>
                            <CardDescription>Informasi kontak dan domisili usaha mitra.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4 text-sm">
                            <div>
                                <span className="text-muted-foreground text-xs">Nomor Telepon{partner.is_masked ? ' (Masked)' : ''}</span>
                                <p className="text-foreground font-mono font-medium">{partner.phone ?? '-'}</p>
                            </div>
                            <div>
                                <span className="text-muted-foreground text-xs">Alamat Domisili{partner.is_masked ? ' (Masked)' : ''}</span>
                                <p className="text-foreground font-medium">{partner.address ?? '-'}</p>
                            </div>
                            {partner.is_masked && (
                                <div className="border-muted bg-muted/30 text-muted-foreground rounded-md border p-3 text-xs">
                                    <ShieldCheck className="text-primary mb-1 inline h-3.5 w-3.5" /> NIK, nomor telepon, dan alamat dimaskir untuk
                                    peran Viewer sesuai kebijakan privasi (DEC-004).
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Aliases & VA Cards */}
                <div className="grid gap-6 md:grid-cols-2">
                    {/* Aliases Card */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Building2 className="text-primary h-4 w-4" /> Alias Nama ({partner.aliases?.length ?? 0})
                            </CardTitle>
                            <CardDescription>Nama alias atau variasi nama mitra yang tercatat.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {!partner.aliases || partner.aliases.length === 0 ? (
                                <p className="text-muted-foreground text-sm italic">Tidak ada variasi alias nama tercatat.</p>
                            ) : (
                                <div className="divide-border divide-y rounded-md border text-sm">
                                    {partner.aliases.map((alias) => (
                                        <div key={alias.id} className="flex items-center justify-between p-3">
                                            <span className="text-foreground font-medium">{alias.name_raw}</span>
                                            <Badge variant="outline" className="text-xs uppercase">
                                                {alias.state}
                                            </Badge>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Virtual Accounts Card */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <CreditCard className="text-primary h-4 w-4" /> Nomor Virtual Account ({partner.virtual_accounts?.length ?? 0})
                            </CardTitle>
                            <CardDescription>Daftar nomor VA yang ditugaskan kepada mitra ini.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {!partner.virtual_accounts || partner.virtual_accounts.length === 0 ? (
                                <p className="text-muted-foreground text-sm italic">Belum ada Virtual Account ditugaskan.</p>
                            ) : (
                                <div className="divide-border divide-y rounded-md border text-sm">
                                    {partner.virtual_accounts.map((va) => (
                                        <div key={va.id} className="flex items-center justify-between p-3">
                                            <div>
                                                <p className="text-foreground font-mono font-medium">{va.va_number}</p>
                                                <p className="text-muted-foreground text-xs">
                                                    Provider: {va.provider ?? 'N/A'} {va.valid_from && `(Sejak ${va.valid_from})`}
                                                </p>
                                            </div>
                                            {va.is_masked && (
                                                <Badge variant="outline" className="text-xs">
                                                    Masked
                                                </Badge>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Itemized Agreement Receivable Breakdown (FR-05 & Finding #23) */}
                <div className="space-y-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h2 className="text-foreground text-lg font-bold tracking-tight">
                                Rincian Piutang Perjanjian & Koordinat Sumber (FR-05)
                            </h2>
                            <p className="text-muted-foreground text-xs">
                                Breakdown rincian saldo per nomor perjanjian, riwayat alokasi pembayaran dengan koordinat ledger bank, penyesuaian,
                                dan jadwal angsuran.
                            </p>
                        </div>
                        <Button size="sm" variant="outline" asChild>
                            <Link href={`/partners/${partner.id}/agreements`}>Lihat Riwayat Perjanjian Lengkap</Link>
                        </Button>
                    </div>

                    {agreements_detail.length === 0 ? (
                        <Card className="border-border">
                            <CardContent className="p-8 text-center">
                                <FileText className="text-muted-foreground mx-auto h-8 w-8" />
                                <p className="text-muted-foreground mt-2 text-sm">Belum ada perjanjian terdaftar untuk mitra ini.</p>
                                <Button size="sm" className="mt-4" asChild>
                                    <Link href={`/partners/${partner.id}/agreements/create`}>Buat Perjanjian Baru</Link>
                                </Button>
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="space-y-6">
                            {agreements_detail.map((agreement) => {
                                const currentTab = activeTabByAgreement[agreement.id] ?? 'allocations';

                                return (
                                    <Card key={agreement.id} className="border-border overflow-hidden">
                                        <CardHeader className="bg-muted/30 pb-3">
                                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                <div>
                                                    <div className="flex items-center gap-2.5">
                                                        <CardTitle className="font-mono text-base font-bold">{agreement.agreement_number}</CardTitle>
                                                        {agreement.batch_year && (
                                                            <Badge variant="outline" className="text-xs">
                                                                Kohor {agreement.batch_year}
                                                            </Badge>
                                                        )}
                                                        <Badge variant="secondary" className="text-xs">
                                                            {agreement.lifecycle_status_label}
                                                        </Badge>
                                                        <span
                                                            className={`inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-medium ${getCollectibilityBadgeClass(agreement.collectibility_status)}`}
                                                        >
                                                            {agreement.collectibility_status_label}
                                                            {agreement.late_months > 0 && ` (${agreement.late_months} bln)`}
                                                        </span>
                                                    </div>
                                                    <CardDescription className="mt-1 text-xs">
                                                        Mulai: {agreement.effective_date ?? '-'} | Jatuh Tempo: {agreement.maturity_date ?? '-'} |
                                                        Tenor: {agreement.tenor_months ? `${agreement.tenor_months} bulan` : '-'}
                                                        {agreement.source_row_number && (
                                                            <span className="font-mono"> | Row #{agreement.source_row_number}</span>
                                                        )}
                                                    </CardDescription>
                                                </div>

                                                <div className="flex items-center gap-2">
                                                    <Button size="sm" variant="outline" asChild className="h-8 text-xs">
                                                        <Link href={`/partners/${partner.id}/agreements/${agreement.id}`}>Detail Kontrak</Link>
                                                    </Button>
                                                    {agreement.lifecycle_status === 'active' && (
                                                        <Button size="sm" asChild className="h-8 text-xs">
                                                            <Link href={`/partners/${partner.id}/agreements/${agreement.id}/payments/create`}>
                                                                Input Pembayaran
                                                            </Link>
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        </CardHeader>

                                        <CardContent className="space-y-4 pt-4">
                                            {/* Agreement Balance Matrix */}
                                            <div className="divide-border overflow-hidden rounded-lg border text-xs">
                                                <div className="bg-muted/50 text-muted-foreground grid grid-cols-5 p-2.5 font-semibold">
                                                    <div>Komponen</div>
                                                    <div className="text-right">Kewajiban Kontrak</div>
                                                    <div className="text-right">Realisasi Bayar</div>
                                                    <div className="text-right">Penyesuaian</div>
                                                    <div className="text-right">Sisa Piutang Berjalan</div>
                                                </div>
                                                <div className="divide-border divide-y">
                                                    <div className="grid grid-cols-5 p-2.5">
                                                        <span className="font-medium">Pokok Pinjaman</span>
                                                        <span className="text-right font-mono font-medium">
                                                            {formatRupiah(agreement.balance.contract_principal ?? 0)}
                                                        </span>
                                                        <span className="text-right font-mono font-medium text-emerald-700 dark:text-emerald-400">
                                                            {formatRupiah(agreement.balance.paid_principal ?? 0)}
                                                        </span>
                                                        <span className="text-right font-mono font-medium">
                                                            {formatRupiah(agreement.balance.adjustment_principal ?? 0)}
                                                        </span>
                                                        <span className="text-primary text-right font-mono font-bold">
                                                            {typeof agreement.balance.principal_remaining === 'number'
                                                                ? formatRupiah(agreement.balance.principal_remaining)
                                                                : agreement.balance.principal_remaining}
                                                        </span>
                                                    </div>
                                                    <div className="grid grid-cols-5 p-2.5">
                                                        <span className="font-medium">Jasa Administrasi / Bunga</span>
                                                        <span className="text-right font-mono font-medium">
                                                            {formatRupiah(agreement.balance.contract_charge ?? 0)}
                                                        </span>
                                                        <span className="text-right font-mono font-medium text-emerald-700 dark:text-emerald-400">
                                                            {formatRupiah(agreement.balance.paid_charge ?? 0)}
                                                        </span>
                                                        <span className="text-right font-mono font-medium">
                                                            {formatRupiah(agreement.balance.adjustment_charge ?? 0)}
                                                        </span>
                                                        <span className="text-primary text-right font-mono font-bold">
                                                            {typeof agreement.balance.charge_remaining === 'number'
                                                                ? formatRupiah(agreement.balance.charge_remaining)
                                                                : agreement.balance.charge_remaining}
                                                        </span>
                                                    </div>
                                                    <div className="bg-muted/40 grid grid-cols-5 p-2.5 font-semibold">
                                                        <span>Total Piutang Perjanjian</span>
                                                        <span className="text-right font-mono font-bold">
                                                            {formatRupiah(agreement.balance.contract_total ?? 0)}
                                                        </span>
                                                        <span className="text-right font-mono font-bold text-emerald-700 dark:text-emerald-400">
                                                            {formatRupiah(agreement.balance.paid_total ?? 0)}
                                                        </span>
                                                        <span className="text-right font-mono font-bold">
                                                            {formatRupiah(agreement.balance.adjustment_total ?? 0)}
                                                        </span>
                                                        <span className="text-primary text-right font-mono font-bold">
                                                            {typeof agreement.balance.total_remaining === 'number'
                                                                ? formatRupiah(agreement.balance.total_remaining)
                                                                : agreement.balance.total_remaining}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Sub-Tabs: Allocations vs Adjustments vs Schedules */}
                                            <div className="border-b pt-2">
                                                <div className="flex gap-4 text-xs">
                                                    <button
                                                        type="button"
                                                        onClick={() => setAgreementTab(agreement.id, 'allocations')}
                                                        className={`pb-2 font-medium transition-colors ${
                                                            currentTab === 'allocations'
                                                                ? 'border-primary text-primary border-b-2'
                                                                : 'text-muted-foreground hover:text-foreground'
                                                        }`}
                                                    >
                                                        Alokasi Pembayaran & Koordinat Sumber ({agreement.payment_allocations.length})
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => setAgreementTab(agreement.id, 'adjustments')}
                                                        className={`pb-2 font-medium transition-colors ${
                                                            currentTab === 'adjustments'
                                                                ? 'border-primary text-primary border-b-2'
                                                                : 'text-muted-foreground hover:text-foreground'
                                                        }`}
                                                    >
                                                        Penyesuaian Piutang ({agreement.receivable_adjustments.length})
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => setAgreementTab(agreement.id, 'schedules')}
                                                        className={`pb-2 font-medium transition-colors ${
                                                            currentTab === 'schedules'
                                                                ? 'border-primary text-primary border-b-2'
                                                                : 'text-muted-foreground hover:text-foreground'
                                                        }`}
                                                    >
                                                        Jadwal Angsuran ({agreement.schedules.length})
                                                    </button>
                                                </div>
                                            </div>

                                            {/* Tab 1: Payment Allocations with Bank Source Coordinates */}
                                            {currentTab === 'allocations' && (
                                                <div>
                                                    {agreement.payment_allocations.length === 0 ? (
                                                        <p className="text-muted-foreground p-4 text-center text-xs italic">
                                                            Belum ada alokasi pembayaran tercatat pada perjanjian ini.
                                                        </p>
                                                    ) : (
                                                        <div className="divide-border overflow-hidden rounded-md border text-xs">
                                                            <div className="bg-muted/50 text-muted-foreground grid grid-cols-12 p-2.5 font-semibold">
                                                                <div className="col-span-2">Tanggal / Periode</div>
                                                                <div className="col-span-4">Koordinat Sumber Transaksi Bank</div>
                                                                <div className="col-span-2 text-right">Alokasi Pokok</div>
                                                                <div className="col-span-2 text-right">Alokasi Jasa</div>
                                                                <div className="col-span-2 text-right">Total Alokasi</div>
                                                            </div>
                                                            <div className="divide-border divide-y">
                                                                {agreement.payment_allocations.map((alloc) => (
                                                                    <div key={alloc.id} className="hover:bg-muted/20 grid grid-cols-12 p-2.5">
                                                                        <div className="col-span-2">
                                                                            <div className="text-foreground font-medium">{alloc.effective_date}</div>
                                                                            <div className="text-muted-foreground text-[10px]">
                                                                                Periode: {alloc.period ?? '-'}
                                                                            </div>
                                                                            <Badge
                                                                                variant={alloc.state === 'posted' ? 'default' : 'secondary'}
                                                                                className="mt-1 text-[10px] uppercase"
                                                                            >
                                                                                {alloc.state}
                                                                            </Badge>
                                                                        </div>
                                                                        <div className="col-span-4 space-y-0.5">
                                                                            <div className="text-foreground font-mono font-medium">
                                                                                Ref: {alloc.bank_transaction?.reference ?? '-'}
                                                                            </div>
                                                                            <div className="text-muted-foreground text-[11px]">
                                                                                Payer: {alloc.bank_transaction?.payer_name ?? '-'} | VA:{' '}
                                                                                <span className="font-mono">
                                                                                    {alloc.bank_transaction?.payer_va ?? '-'}
                                                                                </span>
                                                                            </div>
                                                                            <div className="text-muted-foreground font-mono text-[10px]">
                                                                                Sumber: {alloc.bank_transaction?.source ?? 'N/A'}
                                                                                {alloc.bank_transaction?.source_row_identifier &&
                                                                                    ` | ${alloc.bank_transaction.source_row_identifier}`}
                                                                            </div>
                                                                        </div>
                                                                        <div className="col-span-2 text-right font-mono">
                                                                            {formatRupiah(alloc.principal_amount)}
                                                                        </div>
                                                                        <div className="col-span-2 text-right font-mono">
                                                                            {formatRupiah(alloc.interest_amount + alloc.admin_charge_amount)}
                                                                        </div>
                                                                        <div className="text-foreground col-span-2 text-right font-mono font-bold">
                                                                            {formatRupiah(alloc.total_amount)}
                                                                        </div>
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        </div>
                                                    )}
                                                </div>
                                            )}

                                            {/* Tab 2: Receivable Adjustments */}
                                            {currentTab === 'adjustments' && (
                                                <div>
                                                    {agreement.receivable_adjustments.length === 0 ? (
                                                        <p className="text-muted-foreground p-4 text-center text-xs italic">
                                                            Tidak ada penyesuaian piutang tercatat pada perjanjian ini.
                                                        </p>
                                                    ) : (
                                                        <div className="divide-border overflow-hidden rounded-md border text-xs">
                                                            <div className="bg-muted/50 text-muted-foreground grid grid-cols-12 p-2.5 font-semibold">
                                                                <div className="col-span-2">Tanggal / Tipe</div>
                                                                <div className="col-span-4">Alasan & Bukti</div>
                                                                <div className="col-span-2 text-right">Penyesuaian Pokok</div>
                                                                <div className="col-span-2 text-right">Penyesuaian Jasa</div>
                                                                <div className="col-span-2 text-right">Total Nominal</div>
                                                            </div>
                                                            <div className="divide-border divide-y">
                                                                {agreement.receivable_adjustments.map((adj) => (
                                                                    <div key={adj.id} className="hover:bg-muted/20 grid grid-cols-12 p-2.5">
                                                                        <div className="col-span-2">
                                                                            <div className="text-foreground font-medium">{adj.effective_date}</div>
                                                                            <Badge variant="outline" className="mt-1 text-[10px] uppercase">
                                                                                {adj.adjustment_type}
                                                                            </Badge>
                                                                        </div>
                                                                        <div className="col-span-4">
                                                                            <p className="text-foreground font-medium">{adj.reason ?? '-'}</p>
                                                                            {adj.evidence && (
                                                                                <p className="text-muted-foreground mt-0.5 text-[11px]">
                                                                                    Bukti: {adj.evidence}
                                                                                </p>
                                                                            )}
                                                                            {adj.approved_by && (
                                                                                <p className="text-muted-foreground text-[10px]">
                                                                                    Disetujui: {adj.approved_by.name}
                                                                                </p>
                                                                            )}
                                                                        </div>
                                                                        <div className="col-span-2 text-right font-mono">
                                                                            {formatRupiah(adj.principal_amount)}
                                                                        </div>
                                                                        <div className="col-span-2 text-right font-mono">
                                                                            {formatRupiah(adj.interest_amount + adj.admin_charge_amount)}
                                                                        </div>
                                                                        <div className="text-foreground col-span-2 text-right font-mono font-bold">
                                                                            {formatRupiah(adj.total_amount)}
                                                                        </div>
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        </div>
                                                    )}
                                                </div>
                                            )}

                                            {/* Tab 3: Installment Schedules */}
                                            {currentTab === 'schedules' && (
                                                <div>
                                                    {agreement.schedules.length === 0 ? (
                                                        <p className="text-muted-foreground p-4 text-center text-xs italic">
                                                            Belum ada jadwal angsuran yang digenerate untuk perjanjian ini.
                                                        </p>
                                                    ) : (
                                                        <div className="divide-border max-h-80 overflow-y-auto rounded-md border text-xs">
                                                            <div className="bg-muted/50 text-muted-foreground sticky top-0 grid grid-cols-12 p-2.5 font-semibold">
                                                                <div className="col-span-1">Ke-</div>
                                                                <div className="col-span-2">Jatuh Tempo</div>
                                                                <div className="col-span-2 text-right">Kewajiban Pokok</div>
                                                                <div className="col-span-2 text-right">Kewajiban Jasa</div>
                                                                <div className="col-span-2 text-right">Total Terbayar</div>
                                                                <div className="col-span-2 text-right">Sisa Kewajiban</div>
                                                                <div className="col-span-1 text-center">Status</div>
                                                            </div>
                                                            <div className="divide-border divide-y">
                                                                {agreement.schedules.map((sched) => (
                                                                    <div
                                                                        key={sched.id}
                                                                        className="hover:bg-muted/20 grid grid-cols-12 items-center p-2.5"
                                                                    >
                                                                        <div className="text-foreground col-span-1 font-mono font-semibold">
                                                                            #{sched.installment_number}
                                                                        </div>
                                                                        <div className="col-span-2 font-mono">{sched.due_date}</div>
                                                                        <div className="col-span-2 text-right font-mono">
                                                                            {formatRupiah(sched.principal_due)}
                                                                        </div>
                                                                        <div className="col-span-2 text-right font-mono">
                                                                            {formatRupiah(sched.interest_due + sched.admin_charge_due)}
                                                                        </div>
                                                                        <div className="col-span-2 text-right font-mono text-emerald-700 dark:text-emerald-400">
                                                                            {formatRupiah(sched.total_paid)}
                                                                        </div>
                                                                        <div className="text-foreground col-span-2 text-right font-mono font-bold">
                                                                            {formatRupiah(sched.outstanding)}
                                                                        </div>
                                                                        <div className="col-span-1 text-center">
                                                                            <Badge
                                                                                variant={sched.outstanding === 0 ? 'default' : 'outline'}
                                                                                className="text-[10px]"
                                                                            >
                                                                                {sched.outstanding === 0 ? 'Lunas' : 'Belum'}
                                                                            </Badge>
                                                                        </div>
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        </div>
                                                    )}
                                                </div>
                                            )}
                                        </CardContent>
                                    </Card>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
