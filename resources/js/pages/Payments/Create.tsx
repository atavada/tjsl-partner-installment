import { formatCurrency, getLifecycleBadgeVariant } from '@/components/AgreementTimeline';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { AgreementData } from '@/types/agreement';
import { PartnerData } from '@/types/partner';
import { Head, Link, useForm } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, Clock, ShieldAlert } from 'lucide-react';
import { FormEventHandler } from 'react';

interface CreateProps {
    partner: PartnerData;
    agreement: AgreementData;
    default_idempotency_key: string;
}

export default function Create({ partner, agreement, default_idempotency_key }: CreateProps) {
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
            title: 'Catat Pembayaran',
            href: `/partners/${partner.id}/agreements/${agreement.id}/payments/create`,
        },
    ];

    const today = new Date().toISOString().split('T')[0];

    const { data, setData, post, processing, errors } = useForm({
        idempotency_key: default_idempotency_key,
        partner_id: partner.id,
        agreement_id: agreement.id,
        receipt_date: today,
        reference: '',
        payer_name: partner.name,
        payer_va: partner.virtual_accounts?.[0]?.va_number ?? '',
        source: 'MANUAL_CAPTURE',
        evidence: '',
        notes: '',
        amount: '',
        principal_amount: 0,
        interest_amount: 0,
        admin_charge_amount: 0,
        other_charge_amount: 0,
    });

    const principal = Number(data.principal_amount) || 0;
    const interest = Number(data.interest_amount) || 0;
    const admin = Number(data.admin_charge_amount) || 0;
    const other = Number(data.other_charge_amount) || 0;
    const componentSum = principal + interest + admin + other;

    const rawAmount = data.amount !== '' ? Number(data.amount) : null;
    const isOverallocated = rawAmount !== null && componentSum > rawAmount;
    const overageAmount = rawAmount !== null && rawAmount > componentSum ? rawAmount - componentSum : 0;

    const balance = agreement.balance;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/partners/${partner.id}/agreements/${agreement.id}/payments`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Catat Pembayaran - ${agreement.agreement_number}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header section */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-foreground text-2xl font-bold tracking-tight">Catat Pembayaran & Usulan Alokasi</h1>
                            <Badge variant="outline">Tahap A (Staging)</Badge>
                        </div>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Pencatatan bukti penerimaan kas/bank dan usulan komponen alokasi ke perjanjian mitra terpilih.
                        </p>
                    </div>

                    <Button variant="outline" asChild>
                        <Link href={`/partners/${partner.id}/agreements/${agreement.id}`} className="inline-flex items-center gap-1.5">
                            <ArrowLeft className="h-4 w-4" /> Kembali ke Perjanjian
                        </Link>
                    </Button>
                </div>

                {/* Target context banner: Partner + Agreement + Balance */}
                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Mitra Terverifikasi</CardDescription>
                            <CardTitle className="text-base font-semibold">{partner.name}</CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm">
                            <div className="text-muted-foreground flex justify-between py-1">
                                <span>NO ID:</span>
                                <span className="text-foreground font-mono font-medium">{partner.partner_no_id ?? '-'}</span>
                            </div>
                            <div className="text-muted-foreground flex justify-between py-1">
                                <span>Status Verifikasi:</span>
                                <Badge variant={partner.verification_state === 'verified' ? 'default' : 'secondary'}>
                                    {partner.verification_state === 'verified' ? 'Terverifikasi' : 'Belum Cocok'}
                                </Badge>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Perjanjian Target</CardDescription>
                            <CardTitle className="font-mono text-base font-semibold">{agreement.agreement_number}</CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm">
                            <div className="text-muted-foreground flex justify-between py-1">
                                <span>Status:</span>
                                <Badge variant={getLifecycleBadgeVariant(agreement.lifecycle_status)}>{agreement.lifecycle_status_label}</Badge>
                            </div>
                            <div className="text-muted-foreground flex justify-between py-1">
                                <span>Nilai Kontrak:</span>
                                <span className="text-foreground font-medium">{formatCurrency(agreement.total_amount)}</span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* DEC-008: Balance shown as unverified with timestamp */}
                    <Card className="border-amber-500/30 bg-amber-500/5">
                        <CardHeader className="pb-2">
                            <div className="flex items-center justify-between">
                                <CardDescription className="text-amber-700 dark:text-amber-400">Saldo Piutang Berjalan</CardDescription>
                                <Badge variant="outline" className="border-amber-500/40 text-amber-700 dark:text-amber-400">
                                    {balance.label}
                                </Badge>
                            </div>
                            <CardTitle className="font-mono text-base text-amber-900 dark:text-amber-300">{balance.total_remaining}</CardTitle>
                        </CardHeader>
                        <CardContent className="text-xs text-amber-800/80 dark:text-amber-300/80">
                            <div className="flex items-center gap-1.5 pt-1">
                                <Clock className="h-3.5 w-3.5 shrink-0" />
                                <span>Per tanggal: {balance.as_of ? new Date(balance.as_of).toLocaleDateString('id-ID') : 'Belum diverifikasi'}</span>
                            </div>
                            <p className="mt-1.5 text-[11px] leading-relaxed">
                                Sesuai DEC-008, formula saldo piutang belum disetujui. Tampilan hanya menyajikan status belum terverifikasi.
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Staging form */}
                <form onSubmit={submit} className="space-y-6">
                    {/* General receipt info */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">1. Detail Penerimaan Kas / Bank</CardTitle>
                            <CardDescription>
                                Data referensi transaksi bank. Server akan menghitung fingerprint untuk mendeteksi duplikasi secara otomatis.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label htmlFor="receipt_date">Tanggal Bukti Penerimaan *</Label>
                                <Input
                                    id="receipt_date"
                                    type="date"
                                    max={today}
                                    value={data.receipt_date}
                                    onChange={(e) => setData('receipt_date', e.target.value)}
                                    className="mt-1"
                                    required
                                />
                                <InputError message={errors.receipt_date} className="mt-1" />
                            </div>

                            <div>
                                <Label htmlFor="reference">Nomor Referensi Bank / Giro</Label>
                                <Input
                                    id="reference"
                                    placeholder="Contoh: TRX-20260315-001"
                                    value={data.reference}
                                    onChange={(e) => setData('reference', e.target.value)}
                                    className="mt-1"
                                />
                                <InputError message={errors.reference} className="mt-1" />
                            </div>

                            <div>
                                <Label htmlFor="payer_name">Nama Pengirim / Penyetor</Label>
                                <Input
                                    id="payer_name"
                                    placeholder="Nama pembayar pada slip / mutasi"
                                    value={data.payer_name}
                                    onChange={(e) => setData('payer_name', e.target.value)}
                                    className="mt-1"
                                />
                                <InputError message={errors.payer_name} className="mt-1" />
                            </div>

                            <div>
                                <Label htmlFor="payer_va">Nomor Rekening / Virtual Account</Label>
                                <Input
                                    id="payer_va"
                                    placeholder="Contoh: 880012345678"
                                    value={data.payer_va}
                                    onChange={(e) => setData('payer_va', e.target.value)}
                                    className="mt-1"
                                />
                                <InputError message={errors.payer_va} className="mt-1" />
                            </div>

                            <div>
                                <Label htmlFor="amount">Jumlah Mutasi Bank (Rp)</Label>
                                <Input
                                    id="amount"
                                    type="number"
                                    min="1"
                                    placeholder="Kosongkan jika sama dengan total alokasi"
                                    value={data.amount}
                                    onChange={(e) => setData('amount', e.target.value)}
                                    className="mt-1"
                                />
                                <span className="text-muted-foreground text-xs">
                                    Jika mutasi bank lebih besar dari komponen, kelebihan akan dicatat sebagai ABT/Overpayment.
                                </span>
                                <InputError message={errors.amount} className="mt-1" />
                            </div>

                            <div>
                                <Label htmlFor="source">Sumber Ingesti</Label>
                                <Input id="source" value={data.source} onChange={(e) => setData('source', e.target.value)} className="mt-1" />
                                <InputError message={errors.source} className="mt-1" />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Allocation components */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">2. Usulan Komponen Alokasi (Rupiah)</CardTitle>
                            <CardDescription>
                                Pisahkan jumlah pembayaran ke dalam masing-masing komponen. Total wajib lebih besar dari 0 (zero & negative
                                rejection).
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <Label htmlFor="principal_amount">Pokok (Rp) *</Label>
                                    <Input
                                        id="principal_amount"
                                        type="number"
                                        min="0"
                                        value={data.principal_amount}
                                        onChange={(e) => setData('principal_amount', Number(e.target.value))}
                                        className="mt-1 font-mono"
                                        required
                                    />
                                    <InputError message={errors.principal_amount} className="mt-1" />
                                </div>

                                <div>
                                    <Label htmlFor="interest_amount">Jasa / Bunga (Rp) *</Label>
                                    <Input
                                        id="interest_amount"
                                        type="number"
                                        min="0"
                                        value={data.interest_amount}
                                        onChange={(e) => setData('interest_amount', Number(e.target.value))}
                                        className="mt-1 font-mono"
                                        required
                                    />
                                    <InputError message={errors.interest_amount} className="mt-1" />
                                </div>

                                <div>
                                    <Label htmlFor="admin_charge_amount">Administrasi (Rp) *</Label>
                                    <Input
                                        id="admin_charge_amount"
                                        type="number"
                                        min="0"
                                        value={data.admin_charge_amount}
                                        onChange={(e) => setData('admin_charge_amount', Number(e.target.value))}
                                        className="mt-1 font-mono"
                                        required
                                    />
                                    <InputError message={errors.admin_charge_amount} className="mt-1" />
                                </div>

                                <div>
                                    <Label htmlFor="other_charge_amount">Biaya Lainnya (Rp) *</Label>
                                    <Input
                                        id="other_charge_amount"
                                        type="number"
                                        min="0"
                                        value={data.other_charge_amount}
                                        onChange={(e) => setData('other_charge_amount', Number(e.target.value))}
                                        className="mt-1 font-mono"
                                        required
                                    />
                                    <InputError message={errors.other_charge_amount} className="mt-1" />
                                </div>
                            </div>

                            {/* Live calculation banner */}
                            <div className="bg-muted/50 rounded-lg border p-4">
                                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <span className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                            Total Usulan Alokasi (Server Recomputes)
                                        </span>
                                        <div className="text-foreground font-mono text-xl font-bold">{formatCurrency(componentSum)}</div>
                                    </div>

                                    {rawAmount !== null && (
                                        <div className="text-right">
                                            <span className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                                Nilai Transaksi Bank
                                            </span>
                                            <div className="text-foreground font-mono text-xl font-bold">{formatCurrency(rawAmount)}</div>
                                        </div>
                                    )}
                                </div>

                                {isOverallocated && (
                                    <div className="text-destructive mt-3 flex items-center gap-2 text-sm font-medium">
                                        <AlertCircle className="h-4 w-4 shrink-0" />
                                        <span>Peringatan: Jumlah alokasi komponen melebihi nilai transaksi bank (over-allocation rejected).</span>
                                    </div>
                                )}

                                {overageAmount > 0 && (
                                    <div className="mt-3 flex items-center gap-2 text-sm text-amber-700 dark:text-amber-400">
                                        <ShieldAlert className="h-4 w-4 shrink-0" />
                                        <span>
                                            Kelebihan dana sebesar <strong>{formatCurrency(overageAmount)}</strong> akan dicatat otomatis sebagai
                                            saldo belum diterapkan (ABT/Overpayment) sesuai FR-03.
                                        </span>
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Evidence & Notes */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">3. Bukti Transaksi & Catatan</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <Label htmlFor="evidence">Referensi Bukti / Tanda Terima</Label>
                                <Input
                                    id="evidence"
                                    placeholder="Nama file / nomor bukti penerimaan (contoh: kwitansi-001.pdf)"
                                    value={data.evidence}
                                    onChange={(e) => setData('evidence', e.target.value)}
                                    className="mt-1"
                                />
                                <InputError message={errors.evidence} className="mt-1" />
                            </div>

                            <div>
                                <Label htmlFor="notes">Catatan Staging</Label>
                                <Input
                                    id="notes"
                                    placeholder="Catatan tambahan bila ada..."
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                    className="mt-1"
                                />
                                <InputError message={errors.notes} className="mt-1" />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Submission controls */}
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                        <Button variant="outline" asChild disabled={processing}>
                            <Link href={`/partners/${partner.id}/agreements/${agreement.id}`}>Batal</Link>
                        </Button>
                        <Button type="submit" disabled={processing || isOverallocated || componentSum <= 0}>
                            {processing ? 'Menyimpan...' : 'Ajukan Alokasi Pembayaran (Draft)'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
