import { formatCurrency } from '@/components/AgreementTimeline';
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
import { PaymentData } from '@/types/payment';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, RotateCcw, ShieldAlert } from 'lucide-react';
import { useState } from 'react';
import { getPaymentStateBadgeVariant } from './Index';

interface ShowProps {
    partner: PartnerData;
    agreement: AgreementData;
    payment: PaymentData;
}

export default function Show({ partner, agreement, payment }: ShowProps) {
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
            title: 'Pembayaran',
            href: `/partners/${partner.id}/agreements/${agreement.id}/payments`,
        },
        {
            title: payment.reference ?? 'Detail Transaksi',
            href: `/partners/${partner.id}/agreements/${agreement.id}/payments/${payment.id}`,
        },
    ];

    const [reversingAllocationId, setReversingAllocationId] = useState<string | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        reason: '',
    });

    const handleReversalSubmit = (allocationId: string) => {
        post(`/partners/${partner.id}/agreements/${agreement.id}/payments/${payment.id}/allocations/${allocationId}/reverse`, {
            onSuccess: () => {
                setReversingAllocationId(null);
                reset();
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Transaksi ${payment.reference ?? payment.id} - ${agreement.agreement_number}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header section */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-foreground font-mono text-2xl font-bold tracking-tight">
                                {payment.reference ?? 'Mutasi Bank Tanpa Referensi'}
                            </h1>
                            <Badge variant={getPaymentStateBadgeVariant(payment.state)}>{payment.state_label}</Badge>
                        </div>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Mitra: <span className="font-semibold">{partner.name}</span> &bull; Perjanjian:{' '}
                            <span className="font-mono font-semibold">{agreement.agreement_number}</span>
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <Button variant="outline" asChild>
                            <Link href={`/partners/${partner.id}/agreements/${agreement.id}/payments`} className="inline-flex items-center gap-1.5">
                                <ArrowLeft className="h-4 w-4" /> Daftar Pembayaran
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Gated Posting Notice per DEC-008 */}
                <div className="border-border/80 bg-muted/30 text-foreground flex items-center gap-3 rounded-lg border p-4">
                    <ShieldAlert className="h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
                    <div className="text-sm">
                        <span className="font-semibold">Pembukuan Saldo Ditangguhkan (DEC-008):</span> Alokasi pada halaman ini berstatus{' '}
                        <strong>proposal / draft</strong>. Pembukuan resmi ke buku besar piutang (ledger posting) ditahan hingga aturan formula saldo
                        piutang disetujui pemilik proses.
                    </div>
                </div>

                {/* Bank Transaction Overview */}
                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="text-lg">Detail Transaksi Penerimaan Bank</CardTitle>
                            <CardDescription>
                                Catatan mutasi bank bersifat <strong>immutable</strong> (tidak dapat diubah atau dihapus secara fisik per PRD §4).
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <span className="text-muted-foreground text-xs">Jumlah Mutasi Bank</span>
                                    <div className="text-foreground font-mono text-xl font-bold">{formatCurrency(payment.amount)}</div>
                                </div>

                                <div>
                                    <span className="text-muted-foreground text-xs">Waktu Transaksi</span>
                                    <div className="text-foreground text-sm font-medium">
                                        {payment.transaction_datetime ? new Date(payment.transaction_datetime).toLocaleString('id-ID') : '-'}
                                    </div>
                                    <div className="text-muted-foreground text-xs">Zona Waktu: {payment.timezone}</div>
                                </div>

                                <div>
                                    <span className="text-muted-foreground text-xs">Nama Penyetor</span>
                                    <div className="text-foreground text-sm font-medium">{payment.payer_name ?? '-'}</div>
                                </div>

                                <div>
                                    <span className="text-muted-foreground text-xs">Rekening / Virtual Account</span>
                                    <div className="text-foreground font-mono text-sm font-medium">{payment.payer_va ?? '-'}</div>
                                </div>

                                <div>
                                    <span className="text-muted-foreground text-xs">Sumber Ingesti & Namespace</span>
                                    <div className="text-foreground text-sm">
                                        {payment.source ?? '-'} ({payment.reference_namespace ?? 'MANUAL'})
                                    </div>
                                </div>

                                <div>
                                    <span className="text-muted-foreground text-xs">Periode Bukti (Derivasi)</span>
                                    <div className="text-foreground font-mono text-sm font-medium">{payment.receipt_month ?? '-'}</div>
                                </div>
                            </div>

                            {payment.notes && (
                                <div className="border-border/60 bg-muted/20 rounded border p-3 text-sm">
                                    <span className="text-muted-foreground text-xs font-semibold uppercase">Catatan:</span>
                                    <p className="mt-1">{payment.notes}</p>
                                </div>
                            )}

                            {/* Technical audit trail */}
                            <div className="text-muted-foreground space-y-1 border-t pt-3 text-xs">
                                <div className="flex flex-wrap justify-between gap-1">
                                    <span>Fingerprint (SHA-256):</span>
                                    <span className="font-mono text-[11px]">{payment.fingerprint ?? '-'}</span>
                                </div>
                                <div className="flex flex-wrap justify-between gap-1">
                                    <span>Idempotency Key:</span>
                                    <span className="font-mono text-[11px]">{payment.idempotency_key}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span>Dicatat Oleh:</span>
                                    <span>{payment.recorded_by?.name ?? 'Sistem'}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Right column: Overpayment / ABT & Balance context */}
                    <div className="space-y-6">
                        <Card className="border-amber-500/30 bg-amber-500/5">
                            <CardHeader className="pb-2">
                                <CardDescription className="text-amber-700 dark:text-amber-400">Saldo Piutang Berjalan</CardDescription>
                                <CardTitle className="font-mono text-xl text-amber-900 dark:text-amber-300">
                                    {agreement.balance.total_remaining}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="text-xs text-amber-800/80 dark:text-amber-300/80">
                                <div>Status: {agreement.balance.label}</div>
                                <p className="mt-1 text-[11px]">
                                    Sesuai DEC-008: Nilai saldo piutang ditandai belum terverifikasi untuk mencegah angka fiktif.
                                </p>
                            </CardContent>
                        </Card>

                        {payment.overpayment_amount > 0 && (
                            <Card className="border-blue-500/30 bg-blue-500/5">
                                <CardHeader className="pb-2">
                                    <CardDescription className="text-blue-700 dark:text-blue-400">Kelebihan Pembayaran (ABT)</CardDescription>
                                    <CardTitle className="font-mono text-xl text-blue-900 dark:text-blue-300">
                                        {formatCurrency(payment.overpayment_amount)}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-1 text-xs text-blue-800/80 dark:text-blue-300/80">
                                    <p>Dana mutasi melebihi jumlah usulan komponen alokasi.</p>
                                    <p className="text-[11px]">
                                        Tersimpan sebagai dana belum diterapkan (unapplied). Sesuai DEC-006, eksekusi disposisi ABT ditangguhkan.
                                    </p>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>

                {/* Allocation Proposals Table */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">Usulan Alokasi Komponen Piutang</CardTitle>
                        <CardDescription>Rincian pembagian dana ke Pokok, Jasa/Bunga, Administrasi, dan Biaya Lainnya.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="border-border/60 bg-muted/30 text-muted-foreground border-b text-xs font-medium tracking-wider uppercase">
                                    <tr>
                                        <th className="px-4 py-3">Perjanjian Target</th>
                                        <th className="px-4 py-3 text-right">Pokok</th>
                                        <th className="px-4 py-3 text-right">Jasa / Bunga</th>
                                        <th className="px-4 py-3 text-right">Administrasi</th>
                                        <th className="px-4 py-3 text-right">Lainnya</th>
                                        <th className="px-4 py-3 text-right">Total Alokasi</th>
                                        <th className="px-4 py-3 text-center">Status</th>
                                        <th className="px-4 py-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-border/40 divide-y">
                                    {payment.allocations.map((alloc) => {
                                        const isReversed = alloc.state === 'reversed';
                                        const isCompensating = alloc.reversal_of_id !== null;

                                        return (
                                            <tr
                                                key={alloc.id}
                                                className={`transition-colors ${isReversed ? 'bg-destructive/5' : 'hover:bg-muted/30'}`}
                                            >
                                                <td className="px-4 py-3">
                                                    <div className="text-foreground font-mono font-medium">
                                                        {alloc.agreement_number ?? agreement.agreement_number}
                                                    </div>
                                                    <div className="text-muted-foreground text-xs">
                                                        Tgl Efektif: {alloc.effective_date ?? '-'} &bull; Periode: {alloc.period ?? '-'}
                                                    </div>
                                                    {isCompensating && (
                                                        <span className="text-destructive text-xs font-medium">
                                                            Entri Kompensasi Pembalik (Reversal of: {alloc.reversal_of_id?.substring(0, 8)}...)
                                                        </span>
                                                    )}
                                                    {alloc.reason && (
                                                        <div className="text-muted-foreground mt-0.5 text-xs italic">Alasan: {alloc.reason}</div>
                                                    )}
                                                </td>

                                                <td className="px-4 py-3 text-right font-mono text-xs">{formatCurrency(alloc.principal_amount)}</td>
                                                <td className="px-4 py-3 text-right font-mono text-xs">{formatCurrency(alloc.interest_amount)}</td>
                                                <td className="px-4 py-3 text-right font-mono text-xs">
                                                    {formatCurrency(alloc.admin_charge_amount)}
                                                </td>
                                                <td className="px-4 py-3 text-right font-mono text-xs">
                                                    {formatCurrency(alloc.other_charge_amount)}
                                                </td>
                                                <td className="px-4 py-3 text-right font-mono font-semibold">{formatCurrency(alloc.total_amount)}</td>

                                                <td className="px-4 py-3 text-center">
                                                    <Badge variant={getPaymentStateBadgeVariant(alloc.state)}>{alloc.state_label}</Badge>
                                                </td>

                                                <td className="px-4 py-3 text-right">
                                                    {!isReversed && !isCompensating && (
                                                        <Button
                                                            variant="destructive"
                                                            size="sm"
                                                            onClick={() => setReversingAllocationId(alloc.id)}
                                                            className="inline-flex items-center gap-1 text-xs"
                                                        >
                                                            <RotateCcw className="h-3.5 w-3.5" /> Balikkan (Reverse)
                                                        </Button>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>

                        {/* Inline Reversal Form Dialog */}
                        {reversingAllocationId && (
                            <div className="border-destructive/40 bg-destructive/5 space-y-3 rounded-lg border p-4">
                                <div className="text-destructive flex items-center gap-2 text-sm font-semibold">
                                    <RotateCcw className="h-4 w-4" />
                                    <span>Form Pembalikan Alokasi (Reversal Compensating Entry)</span>
                                </div>
                                <p className="text-muted-foreground text-xs leading-relaxed">
                                    Pembalikan tidak menghapus data asli secara fisik. Sistem akan membuat baris entri kompensasi bertanda terbalik
                                    dan membebaskan kapasitas alokasi transaksi kas/bank.
                                </p>

                                <div>
                                    <Label htmlFor="reversal_reason" className="text-xs">
                                        Alasan Pembalikan *
                                    </Label>
                                    <Input
                                        id="reversal_reason"
                                        placeholder="Contoh: Salah pilih perjanjian / koreksi nilai pokok..."
                                        value={data.reason}
                                        onChange={(e) => setData('reason', e.target.value)}
                                        className="mt-1"
                                        required
                                    />
                                    <InputError message={errors.reason} className="mt-1" />
                                </div>

                                <div className="flex items-center justify-end gap-2 pt-1">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => {
                                            setReversingAllocationId(null);
                                            reset();
                                        }}
                                        disabled={processing}
                                    >
                                        Batal
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                        onClick={() => handleReversalSubmit(reversingAllocationId)}
                                        disabled={processing || !data.reason.trim()}
                                    >
                                        {processing ? 'Memproses...' : 'Konfirmasi Pembalikan'}
                                    </Button>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
