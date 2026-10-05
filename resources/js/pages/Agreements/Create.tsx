import { formatCurrency } from '@/components/AgreementTimeline';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { PartnerData } from '@/types/partner';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Info, PlusCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

interface PartnerOption {
    id: string;
    name: string;
    partner_no_id: string | null;
}

interface CreateProps {
    partners: PartnerOption[];
    selectedPartner: PartnerData | null;
    selectedPartnerId: string | null;
}

export default function Create({ partners, selectedPartner, selectedPartnerId }: CreateProps) {
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
        ...(selectedPartner
            ? [
                  {
                      title: selectedPartner.name,
                      href: `/partners/${selectedPartner.id}`,
                  },
                  {
                      title: 'Riwayat Perjanjian',
                      href: `/partners/${selectedPartner.id}/agreements`,
                  },
              ]
            : []),
        {
            title: 'Buat Perjanjian Baru',
            href: '/agreements/create',
        },
    ];

    const { data, setData, post, processing, errors } = useForm({
        partner_id: selectedPartnerId ?? (partners.length === 1 ? partners[0].id : ''),
        agreement_number: '',
        batch_year: new Date().getFullYear().toString(),
        business_group: '',
        tenor_months: 12,
        effective_date: today,
        application_date: today,
        contract_date: today,
        loan_start_date: today,
        first_due_date: '',
        maturity_date: '',
        principal_amount: 10000000,
        interest_amount: 0,
        admin_charge_amount: 0,
        other_charge_amount: 0,
        interest_rate_percent: 6.0,
        document: null as File | null,
    });

    const computedTotal =
        (Number(data.principal_amount) || 0) +
        (Number(data.interest_amount) || 0) +
        (Number(data.admin_charge_amount) || 0) +
        (Number(data.other_charge_amount) || 0);

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/agreements', {
            forceFormData: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Buat Perjanjian Baru" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-foreground text-2xl font-bold tracking-tight">Buat Perjanjian Baru</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Pendaftaran draft perjanjian pinjaman program kemitraan (PUMK) per regulasi DEC-001 & DEC-002.
                        </p>
                    </div>

                    <Button variant="outline" asChild>
                        <Link
                            href={selectedPartner ? `/partners/${selectedPartner.id}/agreements` : '/partners'}
                            className="inline-flex items-center gap-1.5"
                        >
                            <ArrowLeft className="h-4 w-4" /> Batal & Kembali
                        </Link>
                    </Button>
                </div>

                {/* DEC-002 Invariant Warning */}
                <div className="border-border/80 bg-muted/30 text-foreground flex items-center gap-3 rounded-lg border p-4">
                    <Info className="text-primary h-5 w-5 shrink-0" />
                    <div className="text-sm">
                        <span className="font-semibold">Ketentuan Siklus Hidup Draft (DEC-002):</span> Perjanjian baru akan disimpan dengan status
                        awal <code className="text-primary font-mono text-xs font-semibold">Draft</code>. Status draft{' '}
                        <strong>tidak menimbulkan kewajiban saldo piutang berjalan</strong> dan belum memiliki jadwal angsuran aktif hingga disetujui.
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Partner & General Info */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">1. Identitas Mitra & Informasi Umum</CardTitle>
                            <CardDescription>
                                Pilih mitra binaan terdaftar dan nomor perjanjian (berfungsi sebagai pengelompokan batch, DEC-001).
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            {/* Partner selection */}
                            <div className="space-y-1.5">
                                <Label htmlFor="partner_id">
                                    Mitra Binaan <span className="text-destructive">*</span>
                                </Label>
                                {selectedPartner ? (
                                    <div className="bg-muted/50 rounded-md border p-2.5 text-sm">
                                        <p className="text-foreground font-medium">{selectedPartner.name}</p>
                                        <p className="text-muted-foreground font-mono text-xs">
                                            NO ID: {selectedPartner.partner_no_id ?? 'Belum ada'}
                                        </p>
                                    </div>
                                ) : (
                                    <select
                                        id="partner_id"
                                        value={data.partner_id}
                                        onChange={(e) => setData('partner_id', e.target.value)}
                                        className="border-input bg-background text-foreground focus-visible:ring-ring flex h-10 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-hidden"
                                    >
                                        <option value="">-- Pilih Mitra --</option>
                                        {partners.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name} {p.partner_no_id ? `(${p.partner_no_id})` : ''}
                                            </option>
                                        ))}
                                    </select>
                                )}
                                <InputError message={errors.partner_id} />
                            </div>

                            {/* Agreement Number */}
                            <div className="space-y-1.5">
                                <Label htmlFor="agreement_number">
                                    Nomor Perjanjian <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="agreement_number"
                                    type="text"
                                    placeholder="Contoh: PUMK/2026/001"
                                    value={data.agreement_number}
                                    onChange={(e) => setData('agreement_number', e.target.value)}
                                    className="font-mono uppercase"
                                    required
                                />
                                <InputError message={errors.agreement_number} />
                            </div>

                            {/* Batch Year */}
                            <div className="space-y-1.5">
                                <Label htmlFor="batch_year">Tahun Batch / Penyaluran</Label>
                                <Input
                                    id="batch_year"
                                    type="text"
                                    placeholder="2026"
                                    value={data.batch_year}
                                    onChange={(e) => setData('batch_year', e.target.value)}
                                />
                                <InputError message={errors.batch_year} />
                            </div>

                            {/* Business Group */}
                            <div className="space-y-1.5">
                                <Label htmlFor="business_group">Kelompok Usaha / Sektor</Label>
                                <Input
                                    id="business_group"
                                    type="text"
                                    placeholder="Contoh: Perdagangan, Pertanian, Jasa"
                                    value={data.business_group}
                                    onChange={(e) => setData('business_group', e.target.value)}
                                />
                                <InputError message={errors.business_group} />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Financial Terms */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">2. Struktur Pinjaman & Keuangan</CardTitle>
                            <CardDescription>Rincian pokok pembiayaan, persentase jasa, biaya administrasi, dan total tagihan.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                {/* Principal */}
                                <div className="space-y-1.5">
                                    <Label htmlFor="principal_amount">
                                        Pokok Pinjaman (Rp) <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="principal_amount"
                                        type="number"
                                        min="0"
                                        step="1"
                                        value={data.principal_amount}
                                        onChange={(e) => setData('principal_amount', parseInt(e.target.value) || 0)}
                                        required
                                    />
                                    <p className="text-muted-foreground text-xs">{formatCurrency(data.principal_amount)}</p>
                                    <InputError message={errors.principal_amount} />
                                </div>

                                {/* Tenor */}
                                <div className="space-y-1.5">
                                    <Label htmlFor="tenor_months">
                                        Tenor Angsuran (Bulan) <span className="text-destructive">*</span>
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

                                {/* Interest Rate */}
                                <div className="space-y-1.5">
                                    <Label htmlFor="interest_rate_percent">Suku Bunga / Jasa (%)</Label>
                                    <Input
                                        id="interest_rate_percent"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        value={data.interest_rate_percent}
                                        onChange={(e) => setData('interest_rate_percent', parseFloat(e.target.value) || 0)}
                                    />
                                    <InputError message={errors.interest_rate_percent} />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                {/* Total Interest */}
                                <div className="space-y-1.5">
                                    <Label htmlFor="interest_amount">Total Nominal Jasa/Bunga (Rp)</Label>
                                    <Input
                                        id="interest_amount"
                                        type="number"
                                        min="0"
                                        value={data.interest_amount}
                                        onChange={(e) => setData('interest_amount', parseInt(e.target.value) || 0)}
                                    />
                                    <p className="text-muted-foreground text-xs">{formatCurrency(data.interest_amount)}</p>
                                    <InputError message={errors.interest_amount} />
                                </div>

                                {/* Admin Charge */}
                                <div className="space-y-1.5">
                                    <Label htmlFor="admin_charge_amount">Biaya Administrasi (Rp)</Label>
                                    <Input
                                        id="admin_charge_amount"
                                        type="number"
                                        min="0"
                                        value={data.admin_charge_amount}
                                        onChange={(e) => setData('admin_charge_amount', parseInt(e.target.value) || 0)}
                                    />
                                    <p className="text-muted-foreground text-xs">{formatCurrency(data.admin_charge_amount)}</p>
                                    <InputError message={errors.admin_charge_amount} />
                                </div>

                                {/* Other Charge */}
                                <div className="space-y-1.5">
                                    <Label htmlFor="other_charge_amount">Biaya Lainnya (Rp)</Label>
                                    <Input
                                        id="other_charge_amount"
                                        type="number"
                                        min="0"
                                        value={data.other_charge_amount}
                                        onChange={(e) => setData('other_charge_amount', parseInt(e.target.value) || 0)}
                                    />
                                    <p className="text-muted-foreground text-xs">{formatCurrency(data.other_charge_amount)}</p>
                                    <InputError message={errors.other_charge_amount} />
                                </div>
                            </div>

                            {/* Total Calculation Display */}
                            <div className="bg-primary/5 border-primary/20 flex flex-col justify-between gap-2 rounded-lg border p-4 sm:flex-row sm:items-center">
                                <div>
                                    <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                                        Total Tagihan Perjanjian (Otomatis Recomputed)
                                    </p>
                                    <p className="text-foreground text-xs">Pokok + Jasa/Bunga + Administrasi + Lainnya</p>
                                </div>
                                <div className="text-primary font-mono text-xl font-bold">{formatCurrency(computedTotal)}</div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Critical Dates & Document Attachment */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">3. Jadwal Tanggal & Berkas Kontrak PDF</CardTitle>
                            <CardDescription>Tanggal efektif perjanjian dan berkas digital kontrak PDF (maksimal 10MB).</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div className="space-y-1.5">
                                    <Label htmlFor="effective_date">
                                        Tanggal Efektif <span className="text-destructive">*</span>
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

                                <div className="space-y-1.5">
                                    <Label htmlFor="first_due_date">Tanggal Jatuh Tempo Pertama</Label>
                                    <Input
                                        id="first_due_date"
                                        type="date"
                                        value={data.first_due_date}
                                        onChange={(e) => setData('first_due_date', e.target.value)}
                                    />
                                    <InputError message={errors.first_due_date} />
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="maturity_date">Tanggal Jatuh Tempo Akhir</Label>
                                    <Input
                                        id="maturity_date"
                                        type="date"
                                        value={data.maturity_date}
                                        onChange={(e) => setData('maturity_date', e.target.value)}
                                    />
                                    <InputError message={errors.maturity_date} />
                                </div>
                            </div>

                            {/* Document File Input */}
                            <div className="space-y-1.5">
                                <Label htmlFor="document">Berkas Dokumen Kontrak (Opsional, PDF Maks. 10MB)</Label>
                                <Input
                                    id="document"
                                    type="file"
                                    accept=".pdf,application/pdf"
                                    onChange={(e) => setData('document', e.target.files?.[0] ?? null)}
                                    className="cursor-pointer"
                                />
                                <p className="text-muted-foreground text-xs">
                                    Berkas disimpan pada disk privat terenkripsi dengan verifikasi hash SHA-256 (DEC-003).
                                </p>
                                <InputError message={errors.document} />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Submit Actions */}
                    <div className="flex items-center justify-end gap-3">
                        <Button variant="outline" asChild>
                            <Link href={selectedPartner ? `/partners/${selectedPartner.id}/agreements` : '/partners'}>Batal</Link>
                        </Button>
                        <Button type="submit" disabled={processing} className="gap-2">
                            <PlusCircle className="h-4 w-4" /> Simpan Draft Perjanjian
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
