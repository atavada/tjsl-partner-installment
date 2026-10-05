import { formatCurrency, getLifecycleBadgeVariant } from '@/components/AgreementTimeline';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { AgreementData, BalanceData } from '@/types/agreement';
import { PartnerData } from '@/types/partner';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, RefreshCw, ShieldAlert } from 'lucide-react';
import { FormEventHandler } from 'react';

interface RestructureProps {
    partner: PartnerData;
    agreement: AgreementData;
    balance: BalanceData;
}

export default function Restructure({ partner, agreement, balance }: RestructureProps) {
    const today = new Date().toISOString().split('T')[0];

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
        {
            title: 'Restrukturisasi',
            href: `/partners/${partner.id}/agreements/${agreement.id}/restructure`,
        },
    ];

    const remainingPrincipal = typeof balance.principal_remaining === 'number' ? balance.principal_remaining : agreement.principal_amount;

    const { data, setData, post, processing, errors } = useForm({
        successor_agreement_number: `${agreement.agreement_number}-R1`,
        effective_date: today,
        reason: '',
        tenor_months: agreement.tenor_months ?? 24,
        approved_principal_amount: remainingPrincipal,
        approved_interest_amount: 0,
        approved_admin_charge_amount: 0,
        other_charge_amount: 0,
        interest_rate_percent: Number(agreement.interest_rate_percent) || 5.0,
        first_due_date: '',
        maturity_date: '',
        addendum_document: null as File | null,
    });

    const computedSuccessorTotal =
        (Number(data.approved_principal_amount) || 0) +
        (Number(data.approved_interest_amount) || 0) +
        (Number(data.approved_admin_charge_amount) || 0) +
        (Number(data.other_charge_amount) || 0);

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/partners/${partner.id}/agreements/${agreement.id}/restructure`, {
            forceFormData: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Restrukturisasi ${agreement.agreement_number} - ${partner.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-foreground text-2xl font-bold tracking-tight">Restrukturisasi Perjanjian</h1>
                            <Badge variant={getLifecycleBadgeVariant(agreement.lifecycle_status)}>{agreement.lifecycle_status_label}</Badge>
                        </div>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Pemberian addendum rescheduling / restrukturisasi pembiayaan untuk mitra{' '}
                            <span className="text-foreground font-semibold">{partner.name}</span> (PRD Flow 3, DEC-002, DEC-008).
                        </p>
                    </div>

                    <Button variant="outline" asChild>
                        <Link href={`/partners/${partner.id}/agreements/${agreement.id}`} className="inline-flex items-center gap-1.5">
                            <ArrowLeft className="h-4 w-4" /> Batal & Kembali
                        </Link>
                    </Button>
                </div>

                {/* Regulatory Callout Banner */}
                <div className="border-border/80 bg-muted/30 text-foreground flex items-center gap-3 rounded-lg border p-4">
                    <ShieldAlert className="text-primary h-5 w-5 shrink-0" />
                    <div className="text-sm">
                        <span className="font-semibold">Integritas Keuangan & Siklus Hidup (DEC-002, DEC-008):</span>
                        <ul className="text-muted-foreground mt-1 list-disc space-y-0.5 pl-5 text-xs">
                            <li>
                                Perjanjian asal <code className="text-foreground font-mono">{agreement.agreement_number}</code> akan ditutup dengan
                                status permanen <strong className="text-foreground">Closed by Rescheduling</strong> (bukan pelunasan kas).
                            </li>
                            <li>
                                Saldo, pembayaran, dan jadwal historis perjanjian asal <strong>tetap utuh dan tidak dimutasi</strong>.
                            </li>
                            <li>Perjanjian penerus akan dibentuk sebagai kontrak aktif baru yang melanjutkan sisa pokok pinjaman yang disetujui.</li>
                        </ul>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Side-by-Side Comparison Grid */}
                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        {/* LEFT COLUMN: Predecessor Agreement Baseline */}
                        <Card className="border-muted-foreground/30 bg-muted/10">
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <div>
                                        <CardTitle className="text-base">Perjanjian Asal (Predecessor Baseline)</CardTitle>
                                        <CardDescription>Kondisi saldo dan ketentuan pinjaman berjalan yang akan direstrukturisasi.</CardDescription>
                                    </div>
                                    <Badge variant="outline" className="font-mono text-xs">
                                        ID: {agreement.agreement_number}
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-2 gap-3 text-sm">
                                    <div className="bg-background rounded-md border p-3">
                                        <span className="text-muted-foreground text-xs font-medium uppercase">Pokok Awal</span>
                                        <p className="text-foreground font-mono text-base font-semibold">
                                            {formatCurrency(agreement.principal_amount)}
                                        </p>
                                    </div>
                                    <div className="bg-background rounded-md border p-3">
                                        <span className="text-muted-foreground text-xs font-medium uppercase">Tenor Asal</span>
                                        <p className="text-foreground text-base font-semibold">{agreement.tenor_months ?? '-'} Bulan</p>
                                    </div>
                                </div>

                                {/* Remaining Balances */}
                                <div className="space-y-2">
                                    <h4 className="text-foreground text-xs font-semibold tracking-wide uppercase">
                                        Rincian Sisa Tagihan Berjalan (As-Of Saat Ini)
                                    </h4>
                                    <div className="bg-background divide-border divide-y rounded-md border text-sm">
                                        <div className="flex items-center justify-between p-2.5">
                                            <span className="text-muted-foreground">Sisa Pokok Pinjaman</span>
                                            <span className="text-foreground font-mono font-medium">
                                                {typeof balance.principal_remaining === 'number'
                                                    ? formatCurrency(balance.principal_remaining)
                                                    : balance.principal_remaining}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between p-2.5">
                                            <span className="text-muted-foreground">Sisa Jasa / Bunga</span>
                                            <span className="text-foreground font-mono font-medium">
                                                {typeof balance.interest_remaining === 'number'
                                                    ? formatCurrency(balance.interest_remaining)
                                                    : balance.interest_remaining}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between p-2.5">
                                            <span className="text-muted-foreground">Sisa Administrasi</span>
                                            <span className="text-foreground font-mono font-medium">
                                                {typeof balance.admin_charge_remaining === 'number'
                                                    ? formatCurrency(balance.admin_charge_remaining)
                                                    : balance.admin_charge_remaining}
                                            </span>
                                        </div>
                                        <div className="bg-muted/30 flex items-center justify-between p-3 font-semibold">
                                            <span className="text-foreground">Total Sisa Tagihan Berjalan</span>
                                            <span className="text-primary font-mono text-base">
                                                {typeof balance.total_remaining === 'number'
                                                    ? formatCurrency(balance.total_remaining)
                                                    : balance.total_remaining}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div className="bg-muted/40 rounded-md border p-3 text-xs">
                                    <p className="text-muted-foreground">
                                        <span className="text-foreground font-semibold">Tanggal Efektif:</span> {agreement.effective_date ?? '-'} •{' '}
                                        <span className="text-foreground font-semibold">Jatuh Tempo:</span> {agreement.maturity_date ?? '-'}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        {/* RIGHT COLUMN: Successor Agreement Proposal Form */}
                        <Card className="border-primary/40 bg-card">
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <div>
                                        <CardTitle className="text-primary text-base">Perjanjian Penerus (Successor Proposal)</CardTitle>
                                        <CardDescription>Ketentuan pinjaman baru hasil persetujuan restrukturisasi.</CardDescription>
                                    </div>
                                    <Badge className="text-xs">Proposal Baru</Badge>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {/* Successor Agreement Number */}
                                <div className="space-y-1.5">
                                    <Label htmlFor="successor_agreement_number">
                                        Nomor Perjanjian Baru <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="successor_agreement_number"
                                        type="text"
                                        value={data.successor_agreement_number}
                                        onChange={(e) => setData('successor_agreement_number', e.target.value)}
                                        className="font-mono uppercase"
                                        required
                                    />
                                    <InputError message={errors.successor_agreement_number} />
                                </div>

                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    {/* Effective Date */}
                                    <div className="space-y-1.5">
                                        <Label htmlFor="effective_date">
                                            Tanggal Efektif Baru <span className="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            id="effective_date"
                                            type="date"
                                            value={data.effective_date}
                                            onChange={(e) => setData('effective_date', e.target.value)}
                                            required
                                        />
                                        <InputError message={errors.effective_date} />
                                    </div>

                                    {/* New Tenor */}
                                    <div className="space-y-1.5">
                                        <Label htmlFor="tenor_months">
                                            Tenor Baru (Bulan) <span className="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            id="tenor_months"
                                            type="number"
                                            min="1"
                                            max="360"
                                            value={data.tenor_months}
                                            onChange={(e) => setData('tenor_months', parseInt(e.target.value) || 1)}
                                            required
                                        />
                                        <InputError message={errors.tenor_months} />
                                    </div>
                                </div>

                                {/* Approved Principal */}
                                <div className="space-y-1.5">
                                    <div className="flex items-center justify-between">
                                        <Label htmlFor="approved_principal_amount">
                                            Pokok Baru Disetujui (Rp) <span className="text-destructive">*</span>
                                        </Label>
                                        <button
                                            type="button"
                                            onClick={() => setData('approved_principal_amount', remainingPrincipal)}
                                            className="text-primary text-xs hover:underline"
                                        >
                                            Gunakan Sisa Pokok ({formatCurrency(remainingPrincipal)})
                                        </button>
                                    </div>
                                    <Input
                                        id="approved_principal_amount"
                                        type="number"
                                        min="0"
                                        value={data.approved_principal_amount}
                                        onChange={(e) => setData('approved_principal_amount', parseInt(e.target.value) || 0)}
                                        required
                                    />
                                    <p className="text-muted-foreground text-xs">{formatCurrency(data.approved_principal_amount)}</p>
                                    <InputError message={errors.approved_principal_amount} />
                                </div>

                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    {/* Approved Interest */}
                                    <div className="space-y-1.5">
                                        <Label htmlFor="approved_interest_amount">Jasa / Bunga Baru (Rp)</Label>
                                        <Input
                                            id="approved_interest_amount"
                                            type="number"
                                            min="0"
                                            value={data.approved_interest_amount}
                                            onChange={(e) => setData('approved_interest_amount', parseInt(e.target.value) || 0)}
                                        />
                                        <p className="text-muted-foreground text-xs">{formatCurrency(data.approved_interest_amount)}</p>
                                        <InputError message={errors.approved_interest_amount} />
                                    </div>

                                    {/* Approved Admin */}
                                    <div className="space-y-1.5">
                                        <Label htmlFor="approved_admin_charge_amount">Biaya Administrasi Baru (Rp)</Label>
                                        <Input
                                            id="approved_admin_charge_amount"
                                            type="number"
                                            min="0"
                                            value={data.approved_admin_charge_amount}
                                            onChange={(e) => setData('approved_admin_charge_amount', parseInt(e.target.value) || 0)}
                                        />
                                        <p className="text-muted-foreground text-xs">{formatCurrency(data.approved_admin_charge_amount)}</p>
                                        <InputError message={errors.approved_admin_charge_amount} />
                                    </div>
                                </div>

                                {/* Total Successor Calculation */}
                                <div className="bg-primary/5 border-primary/20 flex items-center justify-between rounded-md border p-3">
                                    <span className="text-foreground text-xs font-semibold uppercase">Total Tagihan Kontrak Baru</span>
                                    <span className="text-primary font-mono text-base font-bold">{formatCurrency(computedSuccessorTotal)}</span>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Mandatory Approval Notes & Addendum Document */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Catatan Persetujuan & Dokumen Addendum SK</CardTitle>
                            <CardDescription>
                                Masukkan justifikasi formal restrukturisasi dan unggah berkas SK/Addendum (PDF maksimal 10MB).
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {/* Justification Notes */}
                            <div className="space-y-1.5">
                                <Label htmlFor="reason">
                                    Alasan & Catatan Persetujuan Restrukturisasi <span className="text-destructive">*</span>
                                </Label>
                                <textarea
                                    id="reason"
                                    rows={3}
                                    placeholder="Jelaskan alasan dan pertimbangan persetujuan restrukturisasi (minimal 5 karakter)..."
                                    value={data.reason}
                                    onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setData('reason', e.target.value)}
                                    className="border-input bg-background text-foreground focus-visible:ring-ring flex min-h-[80px] w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-hidden disabled:cursor-not-allowed disabled:opacity-50"
                                    required
                                />
                                <p className="text-muted-foreground text-xs">
                                    Catatan ini akan disimpan secara permanen pada jejak audit dan dokumen transisi restrukturisasi.
                                </p>
                                <InputError message={errors.reason} />
                            </div>

                            {/* Addendum File */}
                            <div className="space-y-1.5">
                                <Label htmlFor="addendum_document">Berkas Dokumen Addendum / SK Restrukturisasi (PDF Maks. 10MB)</Label>
                                <Input
                                    id="addendum_document"
                                    type="file"
                                    accept=".pdf,application/pdf"
                                    onChange={(e) => setData('addendum_document', e.target.files?.[0] ?? null)}
                                    className="cursor-pointer"
                                />
                                <p className="text-muted-foreground text-xs">
                                    Dokumen addendum akan ditautkan pada transaksi transisi dan dapat diakses dengan audit log terverifikasi.
                                </p>
                                <InputError message={errors.addendum_document} />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Submit Actions */}
                    <div className="flex items-center justify-end gap-3">
                        <Button variant="outline" asChild>
                            <Link href={`/partners/${partner.id}/agreements/${agreement.id}`}>Batal</Link>
                        </Button>
                        <Button type="submit" disabled={processing} className="gap-2">
                            <RefreshCw className="h-4 w-4" /> Proses Restrukturisasi Perjanjian
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
