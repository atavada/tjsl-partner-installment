import { formatCurrency, getLifecycleBadgeVariant } from '@/components/AgreementTimeline';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { AgreementData } from '@/types/agreement';
import { PartnerData } from '@/types/partner';
import { PaymentData } from '@/types/payment';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Plus, Receipt } from 'lucide-react';

interface IndexProps {
    partner: PartnerData;
    agreement: AgreementData;
    payments: PaymentData[];
}

export function getPaymentStateBadgeVariant(state: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (state) {
        case 'posted':
            return 'default';
        case 'submitted':
            return 'secondary';
        case 'reversed':
            return 'destructive';
        case 'draft':
        default:
            return 'outline';
    }
}

export default function Index({ partner, agreement, payments }: IndexProps) {
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
            title: 'Pembayaran & Alokasi',
            href: `/partners/${partner.id}/agreements/${agreement.id}/payments`,
        },
    ];

    const totalPaid = payments.filter((p) => p.state !== 'reversed').reduce((sum, p) => sum + p.amount, 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Riwayat Pembayaran - ${agreement.agreement_number}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header section */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-foreground text-2xl font-bold tracking-tight">Riwayat Pembayaran & Staging Alokasi</h1>
                            <Badge variant={getLifecycleBadgeVariant(agreement.lifecycle_status)}>{agreement.lifecycle_status_label}</Badge>
                        </div>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Perjanjian <span className="font-mono font-semibold">{agreement.agreement_number}</span> &bull; Mitra{' '}
                            <span className="font-semibold">{partner.name}</span>
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <Button variant="outline" asChild>
                            <Link href={`/partners/${partner.id}/agreements/${agreement.id}`} className="inline-flex items-center gap-1.5">
                                <ArrowLeft className="h-4 w-4" /> Detail Perjanjian
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link
                                href={`/partners/${partner.id}/agreements/${agreement.id}/payments/create`}
                                className="inline-flex items-center gap-1.5"
                            >
                                <Plus className="h-4 w-4" /> Catat Pembayaran
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Summary metrics */}
                <div className="grid gap-4 sm:grid-cols-3">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Total Transaksi Tercatat</CardDescription>
                            <CardTitle className="text-2xl font-bold">{payments.length} Transaksi</CardTitle>
                        </CardHeader>
                        <CardContent className="text-muted-foreground text-xs">
                            Semua bukti penerimaan bank berstatus draft/diajukan/dibukukan
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Total Dana Masuk (Aktif)</CardDescription>
                            <CardTitle className="font-mono text-2xl font-bold">{formatCurrency(totalPaid)}</CardTitle>
                        </CardHeader>
                        <CardContent className="text-muted-foreground text-xs">Tidak termasuk transaksi yang telah dibalikkan (reversed)</CardContent>
                    </Card>

                    <Card className="border-amber-500/30 bg-amber-500/5">
                        <CardHeader className="pb-2">
                            <div className="flex items-center justify-between">
                                <CardDescription className="text-amber-700 dark:text-amber-400">Saldo Piutang Berjalan</CardDescription>
                                <Badge variant="outline" className="border-amber-500/40 text-amber-700 dark:text-amber-400">
                                    {agreement.balance.label}
                                </Badge>
                            </div>
                            <CardTitle className="font-mono text-2xl text-amber-900 dark:text-amber-300">
                                {agreement.balance.total_remaining}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-xs text-amber-800/80 dark:text-amber-300/80">
                            Sesuai DEC-008: Pembukuan saldo piutang ditangguhkan
                        </CardContent>
                    </Card>
                </div>

                {/* Payments Table */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">Daftar Penerimaan & Usulan Alokasi</CardTitle>
                        <CardDescription>
                            Daftar mutasi bank dan proposal alokasi komponen piutang. Setiap baris tersimpan dengan kunci idempotensi unik.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        {payments.length === 0 ? (
                            <div className="flex flex-col items-center justify-center p-12 text-center">
                                <Receipt className="text-muted-foreground/40 mb-3 h-12 w-12" />
                                <h3 className="text-foreground text-base font-semibold">Belum Ada Pembayaran Tercatat</h3>
                                <p className="text-muted-foreground mt-1 max-w-sm text-sm">
                                    Belum ada transaksi pembayaran yang dicatat untuk perjanjian ini. Klik tombol di bawah untuk mencatat pembayaran
                                    baru.
                                </p>
                                <Button asChild className="mt-4">
                                    <Link href={`/partners/${partner.id}/agreements/${agreement.id}/payments/create`}>
                                        <Plus className="mr-1.5 h-4 w-4" /> Catat Pembayaran Baru
                                    </Link>
                                </Button>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="border-border/60 bg-muted/30 text-muted-foreground border-b text-xs font-medium tracking-wider uppercase">
                                        <tr>
                                            <th className="px-4 py-3">Tanggal / Waktu</th>
                                            <th className="px-4 py-3">Referensi Bank</th>
                                            <th className="px-4 py-3">Penyetor / VA</th>
                                            <th className="px-4 py-3 text-right">Nilai Mutasi</th>
                                            <th className="px-4 py-3 text-right">Alokasi Pokok</th>
                                            <th className="px-4 py-3 text-right">Alokasi Jasa</th>
                                            <th className="px-4 py-3 text-right">ABT / Overage</th>
                                            <th className="px-4 py-3 text-center">Status</th>
                                            <th className="px-4 py-3 text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-border/40 divide-y">
                                        {payments.map((payment) => {
                                            const primaryAlloc = payment.allocations[0];
                                            return (
                                                <tr key={payment.id} className="hover:bg-muted/30 transition-colors">
                                                    <td className="px-4 py-3 whitespace-nowrap">
                                                        <div className="text-foreground font-medium">
                                                            {payment.transaction_datetime
                                                                ? new Date(payment.transaction_datetime).toLocaleDateString('id-ID')
                                                                : '-'}
                                                        </div>
                                                        <div className="text-muted-foreground text-xs">Periode: {payment.receipt_month ?? '-'}</div>
                                                    </td>

                                                    <td className="px-4 py-3 font-mono text-xs">
                                                        {payment.reference ?? <span className="text-muted-foreground italic">Tanpa referensi</span>}
                                                    </td>

                                                    <td className="px-4 py-3">
                                                        <div className="text-foreground">{payment.payer_name ?? '-'}</div>
                                                        {payment.payer_va && (
                                                            <div className="text-muted-foreground font-mono text-xs">{payment.payer_va}</div>
                                                        )}
                                                    </td>

                                                    <td className="px-4 py-3 text-right font-mono font-medium">{formatCurrency(payment.amount)}</td>

                                                    <td className="px-4 py-3 text-right font-mono text-xs">
                                                        {primaryAlloc ? formatCurrency(primaryAlloc.principal_amount) : '-'}
                                                    </td>

                                                    <td className="px-4 py-3 text-right font-mono text-xs">
                                                        {primaryAlloc ? formatCurrency(primaryAlloc.interest_amount) : '-'}
                                                    </td>

                                                    <td className="px-4 py-3 text-right font-mono text-xs">
                                                        {payment.overpayment_amount > 0 ? (
                                                            <span className="font-semibold text-amber-700 dark:text-amber-400">
                                                                {formatCurrency(payment.overpayment_amount)}
                                                            </span>
                                                        ) : (
                                                            <span className="text-muted-foreground">-</span>
                                                        )}
                                                    </td>

                                                    <td className="px-4 py-3 text-center">
                                                        <Badge variant={getPaymentStateBadgeVariant(payment.state)}>{payment.state_label}</Badge>
                                                    </td>

                                                    <td className="px-4 py-3 text-right">
                                                        <Button variant="ghost" size="sm" asChild>
                                                            <Link href={`/partners/${partner.id}/agreements/${agreement.id}/payments/${payment.id}`}>
                                                                Detail
                                                            </Link>
                                                        </Button>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
