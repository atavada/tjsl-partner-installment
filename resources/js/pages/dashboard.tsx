import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { DashboardPageProps } from '@/types/dashboard';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowUpRight,
    CheckCircle2,
    Clock,
    CreditCard,
    DollarSign,
    FileSpreadsheet,
    Filter,
    Info,
    Layers,
    RotateCcw,
    TrendingUp,
    Users,
} from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

export default function Dashboard({ metrics, metric_definitions }: DashboardPageProps) {
    const [asOf, setAsOf] = useState(metrics.filters.as_of ?? '');
    const [batchYear, setBatchYear] = useState(metrics.filters.batch_year ?? '');
    const [region, setRegion] = useState(metrics.filters.region ?? '');
    const [showMetricRegistry, setShowMetricRegistry] = useState(false);

    const formatRupiah = (val: number): string => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(val);
    };

    const formatNumber = (val: number): string => {
        return new Intl.NumberFormat('id-ID').format(val);
    };

    const handleApplyFilter = (e?: React.FormEvent) => {
        if (e) {
            e.preventDefault();
        }

        router.get(
            '/dashboard',
            {
                as_of: asOf || undefined,
                batch_year: batchYear || undefined,
                region: region || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const handleResetFilter = () => {
        setAsOf('');
        setBatchYear('');
        setRegion('');
        router.get('/dashboard', {}, { preserveState: true, preserveScroll: true });
    };

    const setQuickDate = (dateString: string) => {
        setAsOf(dateString);
        router.get(
            '/dashboard',
            {
                as_of: dateString,
                batch_year: batchYear || undefined,
                region: region || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const getTodayDate = (): string => {
        return new Date().toISOString().split('T')[0];
    };

    const getLastMonthEndDate = (): string => {
        const d = new Date();
        d.setDate(1);
        d.setHours(-1);
        return d.toISOString().split('T')[0];
    };

    const getBandBadgeClass = (status: string) => {
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

    const getBandProgressBarColor = (status: string) => {
        switch (status) {
            case 'lancar':
                return 'bg-emerald-500';
            case 'kurang_lancar':
                return 'bg-amber-500';
            case 'diragukan':
                return 'bg-orange-500';
            case 'bermasalah':
                return 'bg-rose-500';
            case 'lunas':
                return 'bg-blue-500';
            default:
                return 'bg-neutral-400';
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard Keuangan & Portofolio Piutang" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header & Title Bar */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2.5">
                            <h1 className="text-foreground text-2xl font-bold tracking-tight">Dashboard Portofolio Piutang</h1>
                            <Badge variant="outline" className="text-xs font-normal">
                                As-of: {metrics.filters.as_of}
                            </Badge>
                        </div>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Ringkasan saldo piutang berjalan, realisasi penerimaan, dan klasifikasi risiko kolektibilitas portofolio kemitraan.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setShowMetricRegistry(!showMetricRegistry)}
                            className="inline-flex items-center gap-1.5"
                        >
                            <FileSpreadsheet className="h-4 w-4" />
                            {showMetricRegistry ? 'Tutup Registry Metrik' : 'Lihat Registry Metrik'}
                        </Button>
                        <Button size="sm" asChild>
                            <Link href="/abt" className="inline-flex items-center gap-1.5">
                                <CreditCard className="h-4 w-4" /> Kelola ABT
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Filter Controls Bar */}
                <Card className="border-border">
                    <CardHeader className="pb-3">
                        <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                            <Filter className="text-primary h-4 w-4" /> Filter Portofolio & Tanggal Evaluasi (FR-11)
                        </CardTitle>
                        <CardDescription className="text-xs">
                            Kalkulasi saldo dan klasifikasi kolektibilitas dievaluasi per tanggal efektif yang dipilih (as-of date semantics).
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleApplyFilter} className="flex flex-wrap items-end gap-3 text-sm">
                            <div className="flex flex-col gap-1.5">
                                <label htmlFor="as_of_input" className="text-muted-foreground text-xs font-medium">
                                    Tanggal Evaluasi (As-of)
                                </label>
                                <div className="flex items-center gap-1.5">
                                    <Input
                                        id="as_of_input"
                                        type="date"
                                        value={asOf}
                                        onChange={(e) => setAsOf(e.target.value)}
                                        className="h-9 w-40 font-mono text-xs"
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setQuickDate(getTodayDate())}
                                        className="h-9 px-2 text-xs"
                                    >
                                        Hari Ini
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setQuickDate(getLastMonthEndDate())}
                                        className="h-9 px-2 text-xs"
                                    >
                                        Bulan Lalu
                                    </Button>
                                </div>
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <label htmlFor="batch_year_select" className="text-muted-foreground text-xs font-medium">
                                    Kohor / Tahun Perjanjian
                                </label>
                                <select
                                    id="batch_year_select"
                                    value={batchYear}
                                    onChange={(e) => setBatchYear(e.target.value)}
                                    className="border-input bg-background text-foreground focus:ring-ring h-9 rounded-md border px-3 text-xs focus:ring-2 focus:outline-none"
                                >
                                    <option value="">Semua Tahun (Semua Kohor)</option>
                                    {metrics.filter_options.batch_years.map((year) => (
                                        <option key={year} value={year}>
                                            Tahun {year}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <label htmlFor="region_select" className="text-muted-foreground text-xs font-medium">
                                    Wilayah / Daerah Mitra
                                </label>
                                <select
                                    id="region_select"
                                    value={region}
                                    onChange={(e) => setRegion(e.target.value)}
                                    className="border-input bg-background text-foreground focus:ring-ring h-9 rounded-md border px-3 text-xs focus:ring-2 focus:outline-none"
                                >
                                    <option value="">Semua Wilayah</option>
                                    {metrics.filter_options.regions.map((reg) => (
                                        <option key={reg} value={reg}>
                                            {reg}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="flex items-center gap-2">
                                <Button type="submit" size="sm" className="h-9 gap-1.5">
                                    <Filter className="h-3.5 w-3.5" /> Terapkan Filter
                                </Button>
                                {(asOf !== metrics.filters.as_of || batchYear !== '' || region !== '') && (
                                    <Button type="button" variant="outline" size="sm" onClick={handleResetFilter} className="h-9 gap-1.5">
                                        <RotateCcw className="h-3.5 w-3.5" /> Reset
                                    </Button>
                                )}
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* KPI Summary Cards Grid (6 Cards) */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {/* Card 1: Total Sisa Piutang Portofolio */}
                    <Card className="border-border shadow-xs">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-muted-foreground text-sm font-medium">Total Sisa Piutang Portofolio</CardTitle>
                            <TrendingUp className="text-primary h-4 w-4" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-foreground text-2xl font-bold tracking-tight">
                                {formatRupiah(metrics.kpi.total_remaining_portfolio_balance)}
                            </div>
                            <div className="text-muted-foreground mt-1.5 flex items-center gap-1.5 text-xs">
                                <Info className="h-3.5 w-3.5 shrink-0" />
                                <span>Formula: Pokok + Jasa/Bunga (DEC-008 Total Remaining Balance v1)</span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Card 2: Saldo Pokok Berjalan */}
                    <Card className="border-border shadow-xs">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-muted-foreground text-sm font-medium">Saldo Pokok Berjalan</CardTitle>
                            <DollarSign className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-foreground text-2xl font-bold tracking-tight">
                                {formatRupiah(metrics.kpi.total_principal_outstanding)}
                            </div>
                            <div className="text-muted-foreground mt-1.5 flex items-center gap-1.5 text-xs">
                                <Info className="h-3.5 w-3.5 shrink-0" />
                                <span>Formula: Pokok Kontrak + Penyesuaian - Alokasi Pokok (DEC-008 v1)</span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Card 3: Saldo Jasa / Bunga Berjalan */}
                    <Card className="border-border shadow-xs">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-muted-foreground text-sm font-medium">Saldo Jasa / Bunga Berjalan</CardTitle>
                            <Layers className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-foreground text-2xl font-bold tracking-tight">
                                {formatRupiah(metrics.kpi.total_charge_outstanding)}
                            </div>
                            <div className="text-muted-foreground mt-1.5 flex items-center gap-1.5 text-xs">
                                <Info className="h-3.5 w-3.5 shrink-0" />
                                <span>Formula: Bunga Kontrak + Penyesuaian - Alokasi Bunga (DEC-008 v1)</span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Card 4: Realisasi Penerimaan Pembayaran */}
                    <Card className="border-border shadow-xs">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-muted-foreground text-sm font-medium">
                                Realisasi Penerimaan ({metrics.kpi.period_label})
                            </CardTitle>
                            <CheckCircle2 className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-foreground text-2xl font-bold tracking-tight">
                                {formatRupiah(metrics.kpi.total_payments_collected_period)}
                            </div>
                            <p className="text-muted-foreground mt-1.5 text-xs">
                                Total alokasi pembayaran posted efektif pada periode berjalan (netto reversal).
                            </p>
                        </CardContent>
                    </Card>

                    {/* Card 5: Dana Belum Teralokasi (ABT & Parked) */}
                    <Card className="border-border shadow-xs">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-muted-foreground text-sm font-medium">Dana Belum Teralokasi (ABT / Parked)</CardTitle>
                            <CreditCard className="h-4 w-4 text-amber-600 dark:text-amber-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-between">
                                <div className="text-foreground text-2xl font-bold tracking-tight">
                                    {formatRupiah(metrics.kpi.unallocated_abt_total)}
                                </div>
                                <Button variant="ghost" size="sm" asChild className="h-7 px-2 text-xs">
                                    <Link href="/abt" className="inline-flex items-center gap-1">
                                        Alokasikan <ArrowUpRight className="h-3 w-3" />
                                    </Link>
                                </Button>
                            </div>
                            <p className="text-muted-foreground mt-1.5 text-xs">
                                Terdiri dari {metrics.kpi.unallocated_abt_count} lot dana mengendap (ABT belum teridentifikasi & identified
                                unallocated per DEC-006).
                            </p>
                        </CardContent>
                    </Card>

                    {/* Card 6: Populasi Portofolio (Agreements vs Partners) */}
                    <Card className="border-border shadow-xs">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-muted-foreground text-sm font-medium">Populasi Portofolio (FR-11)</CardTitle>
                            <Users className="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-baseline gap-3">
                                <div>
                                    <span className="text-foreground text-2xl font-bold">{formatNumber(metrics.kpi.active_agreements_count)}</span>
                                    <span className="text-muted-foreground ml-1 text-xs">Perjanjian Aktif</span>
                                </div>
                                <span className="text-muted-foreground font-mono">/</span>
                                <div>
                                    <span className="text-foreground text-2xl font-bold">{formatNumber(metrics.kpi.active_partners_count)}</span>
                                    <span className="text-muted-foreground ml-1 text-xs">Mitra Aktif</span>
                                </div>
                            </div>
                            <p className="text-muted-foreground mt-1.5 text-xs">
                                Dari total {formatNumber(metrics.kpi.total_portfolio_partners_count)} mitra binaan terdaftar pada portofolio.
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Collectibility Risk Distribution Section (DEC-007 & FR-11) */}
                <Card className="border-border">
                    <CardHeader>
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2 text-base font-semibold">
                                    <Clock className="text-primary h-4.5 w-4.5" /> Distribusi Risiko Kolektibilitas (DEC-007 & FR-11)
                                </CardTitle>
                                <CardDescription className="text-xs">
                                    Klasifikasi berdasarkan keterlambatan angsuran tertua yang jatuh tempo relatif terhadap tanggal evaluasi.
                                </CardDescription>
                            </div>
                            <div className="flex items-center gap-2">
                                <Badge variant="outline" className="text-xs">
                                    {metrics.collectibility.is_valid_sum ? (
                                        <span className="text-emerald-600 dark:text-emerald-400">✓ Kategori Lengkap (100%)</span>
                                    ) : (
                                        <span className="text-rose-600 dark:text-rose-400">⚠ Selisih Invarian Terdeteksi</span>
                                    )}
                                </Badge>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {/* Proportional visual distribution bar */}
                        {metrics.kpi.active_agreements_count > 0 && (
                            <div className="space-y-2">
                                <div className="text-muted-foreground flex items-center justify-between text-xs">
                                    <span>Komposisi Risiko Portofolio Aktif (berdasarkan jumlah perjanjian)</span>
                                    <span>Total: {metrics.collectibility.total_agreements} Perjanjian</span>
                                </div>
                                <div className="bg-muted flex h-3 w-full overflow-hidden rounded-full">
                                    {metrics.collectibility.bands
                                        .filter((b) => b.agreements_count > 0)
                                        .map((band) => (
                                            <div
                                                key={band.status}
                                                className={`h-full ${getBandProgressBarColor(band.status)}`}
                                                style={{ width: `${band.agreements_percentage}%` }}
                                                title={`${band.label}: ${band.agreements_count} perjanjian (${band.agreements_percentage}%)`}
                                            />
                                        ))}
                                </div>
                            </div>
                        )}

                        {/* Breakdown Table */}
                        <div className="divide-border overflow-hidden rounded-lg border text-sm">
                            <div className="bg-muted/50 text-muted-foreground grid grid-cols-12 p-3 text-xs font-semibold">
                                <div className="col-span-4 sm:col-span-3">Kategori Kolektibilitas</div>
                                <div className="hidden sm:col-span-2 sm:block">Rentang Waktu</div>
                                <div className="col-span-3 text-right sm:col-span-2">Perjanjian Aktif</div>
                                <div className="col-span-2 text-right sm:col-span-2">Mitra Binaan</div>
                                <div className="col-span-3 text-right sm:col-span-3">Sisa Piutang (Rp)</div>
                            </div>
                            <div className="divide-border divide-y">
                                {metrics.collectibility.bands.map((band) => (
                                    <div key={band.status} className="hover:bg-muted/30 grid grid-cols-12 items-center p-3 text-xs transition-colors">
                                        <div className="col-span-4 flex items-center gap-2 sm:col-span-3">
                                            <span
                                                className={`inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-medium ${getBandBadgeClass(band.status)}`}
                                            >
                                                {band.label}
                                            </span>
                                        </div>
                                        <div className="text-muted-foreground hidden sm:col-span-2 sm:block">{band.months_range}</div>
                                        <div className="col-span-3 text-right sm:col-span-2">
                                            <span className="text-foreground font-semibold">{formatNumber(band.agreements_count)}</span>
                                            <span className="text-muted-foreground ml-1">({band.agreements_percentage}%)</span>
                                        </div>
                                        <div className="col-span-2 text-right sm:col-span-2">
                                            <span className="text-foreground font-semibold">{formatNumber(band.partners_count)}</span>
                                            <span className="text-muted-foreground ml-1">({band.partners_percentage}%)</span>
                                        </div>
                                        <div className="col-span-3 text-right sm:col-span-3">
                                            <span className="text-foreground font-mono font-medium">{formatRupiah(band.total_outstanding)}</span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                            {/* Summary footer verifying category sum matches total */}
                            <div className="bg-muted/70 text-foreground grid grid-cols-12 p-3 text-xs font-bold">
                                <div className="col-span-4 sm:col-span-3">Total Seluruh Kategori (FR-11)</div>
                                <div className="text-muted-foreground hidden sm:col-span-2 sm:block">100% Tercakup</div>
                                <div className="col-span-3 text-right sm:col-span-2">
                                    {formatNumber(metrics.collectibility.total_agreements)} Perjanjian
                                </div>
                                <div className="col-span-2 text-right sm:col-span-2">{formatNumber(metrics.collectibility.total_partners)} Mitra</div>
                                <div className="col-span-3 text-right font-mono sm:col-span-3">
                                    {formatRupiah(metrics.collectibility.total_outstanding)}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Metric Registry Panel (PRD §4 & §9 Deliverable) */}
                {showMetricRegistry && (
                    <Card className="border-border">
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2 text-base font-semibold">
                                        <FileSpreadsheet className="text-primary h-4.5 w-4.5" /> Registry Definisi Metrik (MetricDefinition v1)
                                    </CardTitle>
                                    <CardDescription className="text-xs">
                                        Daftar metrik keuangan resmi yang telah disetujui (approved) sesuai spesifikasi PRD §9 dan
                                        docs/metric-definitions.md.
                                    </CardDescription>
                                </div>
                                <Badge variant="secondary" className="text-xs">
                                    {metric_definitions.length} Metrik Terdaftar
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="divide-border overflow-hidden rounded-lg border text-sm">
                                <div className="bg-muted/50 text-muted-foreground grid grid-cols-12 p-3 text-xs font-semibold">
                                    <div className="col-span-3">Kode & Nama Metrik</div>
                                    <div className="col-span-5">Ekspresi Rumus (Formula)</div>
                                    <div className="col-span-2">Referensi Keputusan</div>
                                    <div className="col-span-2 text-right">Persetujuan</div>
                                </div>
                                <div className="divide-border divide-y">
                                    {metric_definitions.map((metric) => (
                                        <div
                                            key={metric.id}
                                            className="hover:bg-muted/30 grid grid-cols-12 items-start p-3 text-xs transition-colors"
                                        >
                                            <div className="col-span-3">
                                                <div className="text-foreground font-semibold">{metric.name}</div>
                                                <div className="text-muted-foreground font-mono text-[11px]">
                                                    {metric.code} ({metric.version})
                                                </div>
                                            </div>
                                            <div className="col-span-5 font-mono text-[11px]">
                                                <div className="bg-muted/40 rounded p-1.5 text-neutral-800 dark:text-neutral-200">
                                                    {metric.formula_expression}
                                                </div>
                                                {metric.description && (
                                                    <p className="text-muted-foreground mt-1 font-sans text-[11px]">{metric.description}</p>
                                                )}
                                            </div>
                                            <div className="col-span-2">
                                                <Badge variant="outline" className="text-[10px]">
                                                    {metric.decision_ref ?? 'N/A'}
                                                </Badge>
                                                <div className="text-muted-foreground mt-1 text-[10px]">{metric.date_semantics}</div>
                                            </div>
                                            <div className="col-span-2 text-right text-[11px]">
                                                <div className="font-medium text-emerald-700 dark:text-emerald-400">Approved</div>
                                                <div className="text-muted-foreground text-[10px]">{metric.approved_by ?? 'Process owner'}</div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
