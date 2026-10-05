import { formatCurrency } from '@/components/AgreementTimeline';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { FundLotData, FundLotType } from '@/types/fund-lot';
import { Head, Link, useForm } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, ArrowRightLeft, Building2, CheckCircle2, Clock, FileText, HelpCircle, Receipt, Shield, User } from 'lucide-react';
import React, { useState } from 'react';

interface ShowProps {
    lot: FundLotData;
}

export default function Show({ lot }: ShowProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Dashboard',
            href: '/dashboard',
        },
        {
            title: 'Kelebihan & ABT',
            href: '/abt',
        },
        {
            title: `Lot #${lot.id.substring(0, 8)}`,
            href: `/fund-lots/${lot.id}`,
        },
    ];

    const [isAllocateOpen, setIsAllocateOpen] = useState(false);
    const today = new Date().toISOString().split('T')[0];

    const firstAgr = lot.partner?.active_agreements?.[0];
    const defaultAgrId = firstAgr?.id ?? '';
    const defaultAmount = Math.min(lot.remaining_capacity, firstAgr ? firstAgr.remaining_balance : lot.remaining_capacity);

    const allocateForm = useForm({
        agreement_id: defaultAgrId,
        amount: defaultAmount > 0 ? defaultAmount.toString() : lot.remaining_capacity.toString(),
        reason: firstAgr ? `Alokasi dana ABT untuk perjanjian ${firstAgr.agreement_number}` : 'Alokasi dana ABT ke perjanjian',
        effective_date: today,
        idempotency_key: crypto.randomUUID(),
    });

    const handleAllocateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        allocateForm.post(route('fund-lots.allocate', lot.id), {
            preserveScroll: true,
            onSuccess: () => {
                setIsAllocateOpen(false);
                allocateForm.reset();
            },
        });
    };

    const getLotTypeBadge = (type: FundLotType) => {
        switch (type) {
            case 'abt':
                return (
                    <Badge variant="outline" className="border-amber-500/50 bg-amber-500/10 text-amber-700 dark:text-amber-400">
                        <HelpCircle className="mr-1 size-3.5" />
                        Angsuran Belum Teridentifikasi (ABT)
                    </Badge>
                );
            case 'identified_unallocated':
                return (
                    <Badge variant="secondary" className="border-blue-500/30 bg-blue-500/10 text-blue-700 dark:text-blue-300">
                        <Clock className="mr-1 size-3.5" />
                        Teridentifikasi Belum Teralokasi
                    </Badge>
                );
            case 'excess':
                return (
                    <Badge variant="default" className="border-purple-500/30 bg-purple-600 text-white">
                        <CheckCircle2 className="mr-1 size-3.5" />
                        Kelebihan Pembayaran (Excess)
                    </Badge>
                );
            case 'allocated':
                return (
                    <Badge variant="outline" className="border-emerald-500/50 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400">
                        <CheckCircle2 className="mr-1 size-3.5" />
                        Teralokasi Penuh
                    </Badge>
                );
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Detail Dana Parkir #${lot.id.substring(0, 8)}`} />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Button variant="outline" size="icon" asChild className="h-9 w-9">
                            <Link href={route('abt.index')}>
                                <ArrowLeft className="size-4" />
                            </Link>
                        </Button>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-xl font-bold tracking-tight">Lot Dana #{lot.id.substring(0, 8)}</h1>
                                {getLotTypeBadge(lot.lot_type)}
                            </div>
                            <p className="text-muted-foreground font-mono text-xs">UUID: {lot.id}</p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        {lot.lot_type === 'identified_unallocated' && lot.remaining_capacity > 0 && (
                            <Button onClick={() => setIsAllocateOpen(true)} className="bg-blue-600 text-white hover:bg-blue-700">
                                <ArrowRightLeft className="mr-2 size-4" />
                                Alokasikan ke Perjanjian
                            </Button>
                        )}
                        {lot.lot_type === 'abt' && (
                            <Button
                                variant="outline"
                                asChild
                                className="border-amber-500/50 text-amber-700 hover:bg-amber-500/10 dark:text-amber-400"
                            >
                                <Link href={route('abt.index')}>
                                    <User className="mr-2 size-4" />
                                    Identifikasi Pemilik di Antrean
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                {/* Banner Status */}
                {lot.lot_type === 'abt' && (
                    <div className="flex items-start gap-3 rounded-lg border border-amber-500/40 bg-amber-500/10 p-4 text-xs text-amber-800 dark:text-amber-300">
                        <AlertCircle className="mt-0.5 size-4 shrink-0" />
                        <div>
                            <p className="font-semibold">Dana Masuk Belum Teridentifikasi Pemiliknya (DEC-006)</p>
                            <p className="text-muted-foreground mt-0.5">
                                Setoran ini dicatat secara immutable dari mutasi bank dan tidak mengurangi piutang mitra mana pun sampai berhasil
                                diverifikasi identitas pemiliknya.
                            </p>
                        </div>
                    </div>
                )}

                {/* Layout Grid */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Left Column (2 Cols) */}
                    <div className="space-y-6 lg:col-span-2">
                        {/* Detail Penerimaan */}
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Receipt className="size-4 text-blue-600" />
                                    Rincian Penerimaan Dana
                                </CardTitle>
                                <CardDescription className="text-xs">Data setoran dan transaksi bank asal dana parkir</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                                    <div className="bg-muted/20 rounded-lg border p-3">
                                        <span className="text-muted-foreground block text-xs">Nominal Asal Dana</span>
                                        <span className="text-lg font-bold">{formatCurrency(lot.amount)}</span>
                                    </div>

                                    <div className="rounded-lg border border-blue-200 bg-blue-50/50 p-3 dark:border-blue-900 dark:bg-blue-950/20">
                                        <span className="block text-xs text-blue-700 dark:text-blue-300">Sisa Kapasitas Alokasi</span>
                                        <span className="text-lg font-bold text-blue-600 dark:text-blue-400">
                                            {formatCurrency(lot.remaining_capacity)}
                                        </span>
                                    </div>

                                    <div>
                                        <span className="text-muted-foreground block text-xs">Referensi Mutasi Bank</span>
                                        <span className="font-mono font-medium">{lot.bank_transaction?.reference ?? '-'}</span>
                                    </div>

                                    <div>
                                        <span className="text-muted-foreground block text-xs">Pengirim Asal / Nama Pembayar</span>
                                        <span className="font-medium">{lot.bank_transaction?.payer_name ?? '-'}</span>
                                    </div>

                                    <div>
                                        <span className="text-muted-foreground block text-xs">Waktu Transaksi</span>
                                        <span className="font-medium">{lot.bank_transaction?.transaction_datetime ?? '-'}</span>
                                    </div>

                                    <div>
                                        <span className="text-muted-foreground block text-xs">Sumber Pencatatan</span>
                                        <span className="font-mono font-medium">{lot.bank_transaction?.source ?? '-'}</span>
                                    </div>

                                    {lot.evidence && (
                                        <div className="border-t pt-3 sm:col-span-2">
                                            <span className="text-muted-foreground block text-xs">Bukti Penerimaan / Dokumen</span>
                                            <span className="text-xs font-medium break-all">{lot.evidence}</span>
                                        </div>
                                    )}

                                    {lot.reason && (
                                        <div className="sm:col-span-2">
                                            <span className="text-muted-foreground block text-xs">Alasan / Catatan Penerimaan</span>
                                            <span className="text-xs font-medium">{lot.reason}</span>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Riwayat Alokasi / Fund Transfer */}
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <ArrowRightLeft className="size-4 text-emerald-600" />
                                    Riwayat Alokasi & Mutasi Dana (Fund Transfers)
                                </CardTitle>
                                <CardDescription className="text-xs">
                                    Pencatatan uang keluar dari lot ini ke perjanjian aktif per formula-spec §7
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {lot.transfers && lot.transfers.length > 0 ? (
                                    <div className="overflow-x-auto rounded-lg border">
                                        <table className="w-full text-left text-sm">
                                            <thead className="bg-muted/50 text-xs font-semibold uppercase">
                                                <tr>
                                                    <th className="px-3 py-2">Tanggal</th>
                                                    <th className="px-3 py-2">Perjanjian Tujuan</th>
                                                    <th className="px-3 py-2">Nominal</th>
                                                    <th className="px-3 py-2">Petugas</th>
                                                    <th className="px-3 py-2">Catatan</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y text-xs">
                                                {lot.transfers.map((transfer) => (
                                                    <tr key={transfer.id} className="hover:bg-muted/20">
                                                        <td className="px-3 py-2.5 whitespace-nowrap">
                                                            {transfer.effective_date ?? transfer.created_at?.split('T')[0]}
                                                        </td>
                                                        <td className="px-3 py-2.5 font-medium whitespace-nowrap">
                                                            {transfer.target_agreement ? (
                                                                <span className="font-mono text-blue-600 dark:text-blue-400">
                                                                    {transfer.target_agreement.agreement_number}
                                                                </span>
                                                            ) : (
                                                                '-'
                                                            )}
                                                        </td>
                                                        <td className="px-3 py-2.5 font-semibold whitespace-nowrap text-emerald-600">
                                                            {formatCurrency(transfer.amount)}
                                                        </td>
                                                        <td className="px-3 py-2.5 whitespace-nowrap">{transfer.actor?.name ?? '-'}</td>
                                                        <td className="text-muted-foreground px-3 py-2.5">{transfer.reason ?? '-'}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                ) : (
                                    <div className="bg-muted/10 flex flex-col items-center justify-center rounded-lg border p-8 text-center">
                                        <FileText className="text-muted-foreground mb-2 size-8" />
                                        <p className="text-xs font-medium">Belum ada alokasi dari dana parkir ini</p>
                                        <p className="text-muted-foreground mt-0.5 text-[11px]">
                                            Dana masih utuh dan siap dialokasikan ke perjanjian aktif mitra pemilik.
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Right Column (1 Col) */}
                    <div className="space-y-6">
                        {/* Mitra Pemilik & Utang Aktif */}
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Building2 className="text-primary size-4" />
                                    Mitra Pemilik & Kewajiban
                                </CardTitle>
                                <CardDescription className="text-xs">Informasi mitra teridentifikasi dan total kewajiban aktif</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {lot.partner ? (
                                    <>
                                        <div className="bg-muted/20 space-y-1.5 rounded-lg border p-3 text-xs">
                                            <div className="flex justify-between">
                                                <span className="text-muted-foreground">Nama Mitra:</span>
                                                <span className="font-semibold">{lot.partner.name}</span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span className="text-muted-foreground">NO ID Mitra:</span>
                                                <span className="font-mono font-medium">{lot.partner.partner_no_id ?? '-'}</span>
                                            </div>
                                            <div className="flex justify-between border-t pt-1.5">
                                                <span className="text-muted-foreground">Total Sisa Utang:</span>
                                                <span className="text-destructive font-bold">
                                                    {formatCurrency(lot.partner.total_remaining_debt ?? 0)}
                                                </span>
                                            </div>
                                        </div>

                                        {/* Perjanjian Aktif */}
                                        <div>
                                            <h4 className="mb-2 text-xs font-semibold">Daftar Perjanjian Aktif:</h4>
                                            {lot.partner.active_agreements && lot.partner.active_agreements.length > 0 ? (
                                                <div className="space-y-2">
                                                    {lot.partner.active_agreements.map((agr) => (
                                                        <div
                                                            key={agr.id}
                                                            className="bg-muted/10 hover:bg-muted/30 flex items-center justify-between rounded-md border p-2.5 text-xs"
                                                        >
                                                            <div>
                                                                <p className="font-mono font-medium">{agr.agreement_number}</p>
                                                                <p className="text-muted-foreground text-[11px]">
                                                                    Sisa Pokok: {formatCurrency(agr.principal_remaining)}
                                                                </p>
                                                            </div>
                                                            <div className="text-right">
                                                                <p className="font-semibold text-blue-600 dark:text-blue-400">
                                                                    {formatCurrency(agr.remaining_balance)}
                                                                </p>
                                                                <span className="text-muted-foreground text-[10px]">Total Sisa</span>
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            ) : (
                                                <div className="rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-700 dark:text-amber-400">
                                                    Mitra ini tidak memiliki perjanjian berstatus Aktif.
                                                </div>
                                            )}
                                        </div>
                                    </>
                                ) : (
                                    <div className="space-y-2 rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-800 dark:text-amber-300">
                                        <p className="font-medium">Mitra Belum Teridentifikasi</p>
                                        <p className="text-muted-foreground text-[11px]">
                                            Dana ini belum memiliki tautan ke mitra mana pun. Lakukan identifikasi di halaman antrean ABT.
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Audit & Integritas */}
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Shield className="text-muted-foreground size-4" />
                                    Audit & Integritas Data
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-xs">
                                <div>
                                    <span className="text-muted-foreground block text-[11px]">Petugas Identifikasi:</span>
                                    <span className="font-medium">{lot.identified_by?.name ?? '-'}</span>
                                </div>
                                <div>
                                    <span className="text-muted-foreground block text-[11px]">Waktu Identifikasi:</span>
                                    <span className="font-medium">{lot.identified_at ?? '-'}</span>
                                </div>
                                {lot.identification_evidence && (
                                    <div>
                                        <span className="text-muted-foreground block text-[11px]">Bukti Identifikasi:</span>
                                        <span className="font-medium break-all">{lot.identification_evidence}</span>
                                    </div>
                                )}
                                <div className="border-t pt-2">
                                    <span className="text-muted-foreground block text-[11px]">Idempotency Key:</span>
                                    <span className="text-muted-foreground font-mono text-[10px] break-all">{lot.idempotency_key}</span>
                                </div>
                                <div>
                                    <span className="text-muted-foreground block text-[11px]">Versi Rekod:</span>
                                    <span className="font-mono font-medium">v{lot.version}</span>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            {/* Modal: Alokasikan ke Perjanjian */}
            <Dialog open={isAllocateOpen} onOpenChange={setIsAllocateOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Alokasikan Dana Parkir ke Perjanjian</DialogTitle>
                        <DialogDescription>
                            Alokasikan dana yang telah teridentifikasi ke perjanjian aktif mitra binaan per DEC-006 & DP-7.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleAllocateSubmit} className="space-y-4">
                        <div className="bg-muted/40 space-y-2 rounded-lg border p-3 text-xs">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Mitra Pemilik:</span>
                                <span className="text-foreground font-semibold">
                                    {lot.partner?.name} ({lot.partner?.partner_no_id ?? '-'})
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Sisa Kapasitas Dana:</span>
                                <span className="font-semibold text-blue-600 dark:text-blue-400">{formatCurrency(lot.remaining_capacity)}</span>
                            </div>
                            <div className="flex justify-between border-t pt-1.5">
                                <span className="text-muted-foreground">Total Sisa Utang Mitra:</span>
                                <span className="text-foreground font-semibold">{formatCurrency(lot.partner?.total_remaining_debt ?? 0)}</span>
                            </div>
                        </div>

                        <div>
                            <Label htmlFor="show_agreement_id">Pilih Perjanjian Aktif *</Label>
                            {lot.partner?.active_agreements && lot.partner.active_agreements.length > 0 ? (
                                <Select
                                    value={allocateForm.data.agreement_id}
                                    onValueChange={(val) => {
                                        const agr = lot.partner?.active_agreements?.find((a) => a.id === val);
                                        allocateForm.setData((prev) => ({
                                            ...prev,
                                            agreement_id: val,
                                            reason: agr ? `Alokasi dana ABT untuk perjanjian ${agr.agreement_number}` : prev.reason,
                                        }));
                                    }}
                                >
                                    <SelectTrigger id="show_agreement_id">
                                        <SelectValue placeholder="Pilih perjanjian tujuan..." />
                                    </SelectTrigger>
                                    <SelectContent className="max-h-60">
                                        {lot.partner.active_agreements.map((agr) => (
                                            <SelectItem key={agr.id} value={agr.id}>
                                                {agr.agreement_number} — Sisa: {formatCurrency(agr.remaining_balance)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            ) : (
                                <div className="rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-700 dark:text-amber-400">
                                    Mitra ini tidak memiliki perjanjian berstatus Aktif.
                                </div>
                            )}
                            {allocateForm.errors.agreement_id && <p className="text-destructive mt-1 text-xs">{allocateForm.errors.agreement_id}</p>}
                        </div>

                        <div>
                            <div className="flex items-center justify-between">
                                <Label htmlFor="show_amount">Jumlah Alokasi (Rp) *</Label>
                                {lot.partner?.active_agreements?.find((a) => a.id === allocateForm.data.agreement_id) && (
                                    <div className="flex gap-1.5">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="h-6 px-1.5 text-[11px] text-blue-600 dark:text-blue-400"
                                            onClick={() => {
                                                const agr = lot.partner?.active_agreements?.find((a) => a.id === allocateForm.data.agreement_id);
                                                if (agr) {
                                                    const targetAmount = Math.min(lot.remaining_capacity, agr.remaining_balance);
                                                    allocateForm.setData('amount', targetAmount.toString());
                                                }
                                            }}
                                        >
                                            Sesuai Sisa Perjanjian
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="h-6 px-1.5 text-[11px] text-blue-600 dark:text-blue-400"
                                            onClick={() => allocateForm.setData('amount', lot.remaining_capacity.toString())}
                                        >
                                            Semua Sisa Dana
                                        </Button>
                                    </div>
                                )}
                            </div>
                            <Input
                                id="show_amount"
                                type="number"
                                required
                                min="1"
                                max={lot.remaining_capacity}
                                placeholder="Contoh: 500000"
                                value={allocateForm.data.amount}
                                onChange={(e) => allocateForm.setData('amount', e.target.value)}
                            />
                            {allocateForm.errors.amount && <p className="text-destructive mt-1 text-xs">{allocateForm.errors.amount}</p>}
                        </div>

                        <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <Label htmlFor="show_effective_date">Tanggal Efektif</Label>
                                <Input
                                    id="show_effective_date"
                                    type="date"
                                    value={allocateForm.data.effective_date}
                                    onChange={(e) => allocateForm.setData('effective_date', e.target.value)}
                                />
                                {allocateForm.errors.effective_date && (
                                    <p className="text-destructive mt-1 text-xs">{allocateForm.errors.effective_date}</p>
                                )}
                            </div>
                            <div>
                                <Label htmlFor="show_reason">Alasan / Catatan</Label>
                                <Input
                                    id="show_reason"
                                    value={allocateForm.data.reason}
                                    onChange={(e) => allocateForm.setData('reason', e.target.value)}
                                />
                                {allocateForm.errors.reason && <p className="text-destructive mt-1 text-xs">{allocateForm.errors.reason}</p>}
                            </div>
                        </div>

                        <DialogFooter className="mt-6">
                            <Button type="button" variant="outline" onClick={() => setIsAllocateOpen(false)}>
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={allocateForm.processing || !allocateForm.data.agreement_id}
                                className="bg-blue-600 text-white hover:bg-blue-700"
                            >
                                {allocateForm.processing ? 'Memproses Alokasi...' : 'Alokasikan Dana'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
