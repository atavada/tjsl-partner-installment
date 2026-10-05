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
import { FundLotData, FundLotFilters, FundLotPartner, FundLotStats, FundLotType, PaginatedFundLots } from '@/types/fund-lot';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowRightLeft,
    CheckCircle2,
    CircleDollarSign,
    Clock,
    ExternalLink,
    HelpCircle,
    Info,
    Plus,
    Search,
    UserCheck,
} from 'lucide-react';
import React, { useState } from 'react';

interface IndexProps {
    lots: PaginatedFundLots;
    stats: FundLotStats;
    verifiedPartners: FundLotPartner[];
    filters: FundLotFilters;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Kelebihan & ABT',
        href: '/abt',
    },
];

export default function Index({ lots, stats, verifiedPartners, filters }: IndexProps) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [selectedType, setSelectedType] = useState<FundLotType | 'all'>(filters.lot_type ?? 'all');

    // Dialog state for Recording new ABT
    const [isCreateOpen, setIsCreateOpen] = useState(false);

    // Dialog state for Identification
    const [identifyingLot, setIdentifyingLot] = useState<FundLotData | null>(null);

    // Dialog state for Allocation to Agreement per TASK-REM-007
    const [allocatingLot, setAllocatingLot] = useState<FundLotData | null>(null);

    // Form for capturing ABT
    const today = new Date().toISOString().split('T')[0];
    const createForm = useForm({
        idempotency_key: crypto.randomUUID(),
        amount: '',
        receipt_date: today,
        payer_name: '',
        payer_va: '',
        reference: '',
        evidence: '',
        reason: 'Setoran belum teridentifikasi (ABT)',
        notes: '',
        source: 'MANUAL_ABT_CAPTURE',
    });

    // Form for identifying ABT
    const identifyForm = useForm({
        partner_id: '',
        evidence: '',
    });

    // Form for allocating ABT to agreement
    const allocateForm = useForm({
        agreement_id: '',
        amount: '',
        reason: '',
        effective_date: today,
        idempotency_key: crypto.randomUUID(),
    });

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            route('abt.index'),
            {
                search: search.trim() || undefined,
                lot_type: selectedType !== 'all' ? selectedType : undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const handleTypeFilter = (type: FundLotType | 'all') => {
        setSelectedType(type);
        router.get(
            route('abt.index'),
            {
                search: search.trim() || undefined,
                lot_type: type !== 'all' ? type : undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const handleStoreAbt = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post(route('fund-lots.store-abt'), {
            onSuccess: () => {
                setIsCreateOpen(false);
                createForm.reset();
                createForm.setData('idempotency_key', crypto.randomUUID());
            },
        });
    };

    const handleOpenIdentify = (lot: FundLotData) => {
        setIdentifyingLot(lot);
        identifyForm.reset();
        identifyForm.clearErrors();
    };

    const handleIdentifySubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!identifyingLot) return;

        identifyForm.post(route('fund-lots.identify', identifyingLot.id), {
            onSuccess: () => {
                setIdentifyingLot(null);
                identifyForm.reset();
            },
        });
    };

    const handleOpenAllocate = (lot: FundLotData) => {
        setAllocatingLot(lot);
        allocateForm.reset();
        allocateForm.clearErrors();
        const firstAgr = lot.partner?.active_agreements?.[0];
        const defaultAgrId = firstAgr?.id ?? '';
        const defaultAmount = Math.min(lot.remaining_capacity, firstAgr ? firstAgr.remaining_balance : lot.remaining_capacity);

        allocateForm.setData({
            agreement_id: defaultAgrId,
            amount: defaultAmount > 0 ? defaultAmount.toString() : lot.remaining_capacity.toString(),
            reason: firstAgr ? `Alokasi dana ABT untuk perjanjian ${firstAgr.agreement_number}` : 'Alokasi dana ABT ke perjanjian',
            effective_date: today,
            idempotency_key: crypto.randomUUID(),
        });
    };

    const handleAllocateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!allocatingLot) return;

        allocateForm.post(route('fund-lots.allocate', allocatingLot.id), {
            preserveScroll: true,
            onSuccess: () => {
                setAllocatingLot(null);
                allocateForm.reset();
            },
        });
    };

    const getLotTypeBadge = (type: FundLotType) => {
        switch (type) {
            case 'abt':
                return (
                    <Badge variant="outline" className="border-amber-500/50 bg-amber-500/10 text-amber-700 dark:text-amber-400">
                        <HelpCircle className="mr-1 size-3" />
                        ABT (Belum Teridentifikasi)
                    </Badge>
                );
            case 'identified_unallocated':
                return (
                    <Badge variant="secondary" className="border-blue-500/30 bg-blue-500/10 text-blue-700 dark:text-blue-300">
                        <Clock className="mr-1 size-3" />
                        Teridentifikasi Belum Teralokasi
                    </Badge>
                );
            case 'excess':
                return (
                    <Badge variant="default" className="border-purple-500/30 bg-purple-600 text-white">
                        <CheckCircle2 className="mr-1 size-3" />
                        Kelebihan Bayar (Excess)
                    </Badge>
                );
            case 'allocated':
                return (
                    <Badge variant="outline" className="border-emerald-500/50 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400">
                        <CheckCircle2 className="mr-1 size-3" />
                        Teralokasi
                    </Badge>
                );
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Kelebihan Dana & ABT" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Kelebihan Dana & Angsuran Belum Teridentifikasi (ABT)</h1>
                        <p className="text-muted-foreground text-sm">
                            Pengelolaan dana parkir empat konsep (DEC-006): Penerimaan bank, ABT, teridentifikasi belum teralokasi, dan kelebihan
                            sejati.
                        </p>
                    </div>

                    <Button onClick={() => setIsCreateOpen(true)} className="gap-2">
                        <Plus className="size-4" />
                        Catat Dana ABT Baru
                    </Button>
                </div>

                {/* DEC-006 Rule Explainer Banner */}
                <Card className="border-amber-500/30 bg-amber-500/5">
                    <CardContent className="flex items-start gap-4 p-4 text-sm">
                        <Info className="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400" />
                        <div className="space-y-1">
                            <p className="font-semibold text-amber-900 dark:text-amber-200">Ketentuan Integritas Keuangan DEC-006 (2026-10-03)</p>
                            <p className="text-amber-800 dark:text-amber-300">
                                <strong>ABT bukan kelebihan bayar.</strong> Dana ABT tidak mengurangi piutang berjalan sampai mitra teridentifikasi
                                dan dialokasikan ke perjanjian. Sesuai aturan: tidak ada pengembalian dana (<em>no refund</em>) dan tidak ada
                                penghapusan dana parkir (<em>no delete</em>). Kasir mencatat dan mengidentifikasi secara langsung dengan jejak audit
                                dan pembalikan.
                            </p>
                        </div>
                    </CardContent>
                </Card>

                {/* Summary KPI Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription className="flex items-center justify-between text-xs font-medium">
                                <span>ABT (Belum Teridentifikasi)</span>
                                <HelpCircle className="size-4 text-amber-500" />
                            </CardDescription>
                            <CardTitle className="text-2xl font-bold text-amber-600 dark:text-amber-400">
                                {formatCurrency(stats.total_abt_amount)}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-muted-foreground text-xs">{stats.total_abt_count} lot menunggu identifikasi mitra</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription className="flex items-center justify-between text-xs font-medium">
                                <span>Teridentifikasi Belum Teralokasi</span>
                                <Clock className="size-4 text-blue-500" />
                            </CardDescription>
                            <CardTitle className="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                {formatCurrency(stats.total_identified_amount)}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-muted-foreground text-xs">{stats.total_identified_count} lot siap dialokasikan ke perjanjian</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription className="flex items-center justify-between text-xs font-medium">
                                <span>Kelebihan Bayar (True Excess)</span>
                                <CircleDollarSign className="size-4 text-purple-500" />
                            </CardDescription>
                            <CardTitle className="text-2xl font-bold text-purple-600 dark:text-purple-400">
                                {formatCurrency(stats.total_excess_amount)}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-muted-foreground text-xs">{stats.total_excess_count} lot melebihi total utang mitra (DP-8)</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription className="flex items-center justify-between text-xs font-medium">
                                <span>Total Akumulasi Dana Parkir</span>
                                <AlertCircle className="text-muted-foreground size-4" />
                            </CardDescription>
                            <CardTitle className="text-2xl font-bold">{formatCurrency(stats.grand_total_amount)}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-muted-foreground text-xs">{stats.grand_total_count} total lot tercatat dalam sistem</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Filter and Search Bar */}
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    {/* Filter Pills */}
                    <div className="flex flex-wrap gap-2">
                        <Button variant={selectedType === 'all' ? 'default' : 'outline'} size="sm" onClick={() => handleTypeFilter('all')}>
                            Semua ({stats.grand_total_count})
                        </Button>
                        <Button
                            variant={selectedType === 'abt' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => handleTypeFilter('abt')}
                            className={selectedType === 'abt' ? 'bg-amber-600 hover:bg-amber-700' : ''}
                        >
                            ABT Belum Teridentifikasi ({stats.total_abt_count})
                        </Button>
                        <Button
                            variant={selectedType === 'identified_unallocated' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => handleTypeFilter('identified_unallocated')}
                            className={selectedType === 'identified_unallocated' ? 'bg-blue-600 hover:bg-blue-700' : ''}
                        >
                            Teridentifikasi Belum Teralokasi ({stats.total_identified_count})
                        </Button>
                        <Button
                            variant={selectedType === 'excess' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => handleTypeFilter('excess')}
                            className={selectedType === 'excess' ? 'bg-purple-600 hover:bg-purple-700' : ''}
                        >
                            Kelebihan Bayar ({stats.total_excess_count})
                        </Button>
                    </div>

                    {/* Search Form */}
                    <form onSubmit={handleSearchSubmit} className="flex gap-2">
                        <div className="relative w-full sm:w-72">
                            <Search className="text-muted-foreground absolute top-2.5 left-2.5 size-4" />
                            <Input
                                placeholder="Cari bukti, nama, no ID, ref..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="pl-9 text-sm"
                            />
                        </div>
                        <Button type="submit" variant="secondary" size="sm">
                            Cari
                        </Button>
                    </form>
                </div>

                {/* Fund Lots Table */}
                <Card>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-border/60 bg-muted/40 text-muted-foreground text-xs uppercase">
                                <tr>
                                    <th className="px-4 py-3 font-semibold">Tipe Dana</th>
                                    <th className="px-4 py-3 font-semibold">Jumlah (Rp)</th>
                                    <th className="px-4 py-3 font-semibold">Mitra / Pemilik</th>
                                    <th className="px-4 py-3 font-semibold">Referensi / Penyetor</th>
                                    <th className="px-4 py-3 font-semibold">Bukti & Catatan</th>
                                    <th className="px-4 py-3 text-right font-semibold">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-border/40 divide-y">
                                {lots.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="py-12 text-center">
                                            <div className="flex flex-col items-center justify-center gap-2">
                                                <AlertCircle className="text-muted-foreground size-8" />
                                                <p className="font-medium">Tidak ada lot dana parkir ditemukan</p>
                                                <p className="text-muted-foreground text-xs">
                                                    Semua penerimaan dana telah dialokasikan atau belum ada data yang sesuai filter.
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    lots.data.map((lot) => (
                                        <tr key={lot.id} className="hover:bg-muted/20">
                                            <td className="px-4 py-3">
                                                {getLotTypeBadge(lot.lot_type)}
                                                <div className="text-muted-foreground mt-1 text-xs">ID: {lot.id.substring(0, 8)}...</div>
                                            </td>

                                            <td className="px-4 py-3 whitespace-nowrap">
                                                <div className="font-semibold">{formatCurrency(lot.amount)}</div>
                                                {lot.remaining_capacity !== undefined && lot.remaining_capacity !== lot.amount && (
                                                    <div className="text-muted-foreground text-xs">
                                                        Sisa: {formatCurrency(lot.remaining_capacity)}
                                                    </div>
                                                )}
                                            </td>

                                            <td className="px-4 py-3">
                                                {lot.partner ? (
                                                    <div>
                                                        <p className="font-medium">{lot.partner.name}</p>
                                                        <p className="text-muted-foreground font-mono text-xs">{lot.partner.partner_no_id ?? '-'}</p>
                                                    </div>
                                                ) : (
                                                    <span className="text-muted-foreground text-xs italic">Belum Teridentifikasi</span>
                                                )}
                                                {lot.identified_by && (
                                                    <p className="text-muted-foreground mt-0.5 text-xs">Oleh: {lot.identified_by.name}</p>
                                                )}
                                            </td>

                                            <td className="px-4 py-3 text-xs">
                                                {lot.reason && <p className="font-medium">{lot.reason}</p>}
                                                {lot.idempotency_key && (
                                                    <p className="text-muted-foreground font-mono text-[11px]">
                                                        Key: {lot.idempotency_key.substring(0, 8)}...
                                                    </p>
                                                )}
                                            </td>

                                            <td className="px-4 py-3 text-xs">
                                                {lot.evidence && (
                                                    <p className="max-w-[200px] truncate" title={lot.evidence}>
                                                        Bukti: {lot.evidence}
                                                    </p>
                                                )}
                                                {lot.identification_evidence && (
                                                    <p className="text-muted-foreground max-w-[200px] truncate" title={lot.identification_evidence}>
                                                        Identifikasi: {lot.identification_evidence}
                                                    </p>
                                                )}
                                            </td>

                                            <td className="px-4 py-3 text-right">
                                                <div className="flex items-center justify-end gap-1.5">
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        asChild
                                                        className="text-muted-foreground hover:text-foreground h-8 px-2 text-xs"
                                                    >
                                                        <Link href={route('fund-lots.show', lot.id)}>
                                                            <ExternalLink className="mr-1 size-3.5" />
                                                            Detail
                                                        </Link>
                                                    </Button>
                                                    {lot.lot_type === 'abt' ? (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => handleOpenIdentify(lot)}
                                                            className="border-amber-500/50 text-amber-700 hover:bg-amber-500/10 dark:text-amber-400"
                                                        >
                                                            <UserCheck className="mr-1 size-3.5" />
                                                            Identifikasi Mitra
                                                        </Button>
                                                    ) : lot.lot_type === 'identified_unallocated' ? (
                                                        lot.remaining_capacity > 0 ? (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() => handleOpenAllocate(lot)}
                                                                className="border-blue-500/50 text-blue-700 hover:bg-blue-500/10 dark:text-blue-400"
                                                            >
                                                                <ArrowRightLeft className="mr-1 size-3.5" />
                                                                Alokasikan
                                                            </Button>
                                                        ) : (
                                                            <Badge variant="outline" className="text-muted-foreground text-xs">
                                                                Habis Teralokasi
                                                            </Badge>
                                                        )
                                                    ) : lot.lot_type === 'allocated' ? (
                                                        <Badge variant="outline" className="text-xs text-emerald-600 dark:text-emerald-400">
                                                            Teralokasi
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="outline" className="text-xs text-purple-600 dark:text-purple-400">
                                                            Kelebihan Utang
                                                        </Badge>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {lots.links && lots.links.length > 3 && (
                        <div className="border-border/40 flex items-center justify-between border-t p-4 text-xs">
                            <span className="text-muted-foreground">
                                Menampilkan {lots.from ?? 0} - {lots.to ?? 0} dari {lots.total} data
                            </span>
                            <div className="flex gap-1">
                                {lots.links.map((link, idx) => (
                                    <Button
                                        key={idx}
                                        size="sm"
                                        variant={link.active ? 'default' : 'outline'}
                                        disabled={!link.url}
                                        onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                        className="h-8 min-w-8 text-xs"
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </Card>
            </div>

            {/* Modal: Catat Dana ABT Baru */}
            <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Catat Penerimaan Dana ABT</DialogTitle>
                        <DialogDescription>
                            Pencatatan dana mutasi bank yang belum teridentifikasi pemiliknya. Dana ini tidak akan mengurangi piutang sebelum
                            diidentifikasi.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleStoreAbt} className="space-y-4">
                        <div>
                            <Label htmlFor="amount">Jumlah Setoran (Rp) *</Label>
                            <Input
                                id="amount"
                                type="number"
                                required
                                min="1"
                                placeholder="Contoh: 500000"
                                value={createForm.data.amount}
                                onChange={(e) => createForm.setData('amount', e.target.value)}
                            />
                            {createForm.errors.amount && <p className="text-destructive mt-1 text-xs">{createForm.errors.amount}</p>}
                        </div>

                        <div>
                            <Label htmlFor="receipt_date">Tanggal Penerimaan Bank *</Label>
                            <Input
                                id="receipt_date"
                                type="date"
                                required
                                value={createForm.data.receipt_date}
                                onChange={(e) => createForm.setData('receipt_date', e.target.value)}
                            />
                            {createForm.errors.receipt_date && <p className="text-destructive mt-1 text-xs">{createForm.errors.receipt_date}</p>}
                        </div>

                        <div>
                            <Label htmlFor="payer_name">Nama Pengirim / Penyetor di Rekening Koran</Label>
                            <Input
                                id="payer_name"
                                placeholder="Contoh: Budi Santoso atau Anonim"
                                value={createForm.data.payer_name}
                                onChange={(e) => createForm.setData('payer_name', e.target.value)}
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label htmlFor="payer_va">Nomor VA Pengirim</Label>
                                <Input
                                    id="payer_va"
                                    placeholder="Contoh: 999900001234"
                                    value={createForm.data.payer_va}
                                    onChange={(e) => createForm.setData('payer_va', e.target.value)}
                                />
                            </div>

                            <div>
                                <Label htmlFor="reference">Referensi Bank</Label>
                                <Input
                                    id="reference"
                                    placeholder="Contoh: TXN-BANK-001"
                                    value={createForm.data.reference}
                                    onChange={(e) => createForm.setData('reference', e.target.value)}
                                />
                            </div>
                        </div>

                        <div>
                            <Label htmlFor="evidence">Bukti Rekening Koran / Dokumen</Label>
                            <Input
                                id="evidence"
                                placeholder="Contoh: rekening_koran_mar2026_row_45.pdf"
                                value={createForm.data.evidence}
                                onChange={(e) => createForm.setData('evidence', e.target.value)}
                            />
                        </div>

                        <div>
                            <Label htmlFor="reason">Alasan / Catatan</Label>
                            <Input id="reason" value={createForm.data.reason} onChange={(e) => createForm.setData('reason', e.target.value)} />
                        </div>

                        <DialogFooter className="mt-6">
                            <Button type="button" variant="outline" onClick={() => setIsCreateOpen(false)}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={createForm.processing}>
                                {createForm.processing ? 'Menyimpan...' : 'Simpan ke Antrean ABT'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modal: Identifikasi Mitra */}
            <Dialog open={!!identifyingLot} onOpenChange={(open) => !open && setIdentifyingLot(null)}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Identifikasi Pemilik Dana ABT</DialogTitle>
                        <DialogDescription>
                            Tautkan dana parkir sebesar {identifyingLot ? formatCurrency(identifyingLot.amount) : ''} kepada mitra terverifikasi.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleIdentifySubmit} className="space-y-4">
                        <div>
                            <Label htmlFor="partner_id">Pilih Mitra Terverifikasi *</Label>
                            <Select value={identifyForm.data.partner_id} onValueChange={(val) => identifyForm.setData('partner_id', val)}>
                                <SelectTrigger id="partner_id">
                                    <SelectValue placeholder="Pilih mitra tujuan identifikasi..." />
                                </SelectTrigger>
                                <SelectContent className="max-h-60">
                                    {verifiedPartners.map((partner) => (
                                        <SelectItem key={partner.id} value={partner.id}>
                                            {partner.name} ({partner.partner_no_id ?? 'Tanpa NO ID'})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {identifyForm.errors.partner_id && <p className="text-destructive mt-1 text-xs">{identifyForm.errors.partner_id}</p>}
                        </div>

                        <div>
                            <Label htmlFor="identify_evidence">Bukti Verifikasi Identitas *</Label>
                            <Input
                                id="identify_evidence"
                                required
                                placeholder="Contoh: Konfirmasi WhatsApp & bukti slip transfer asli dari mitra"
                                value={identifyForm.data.evidence}
                                onChange={(e) => identifyForm.setData('evidence', e.target.value)}
                            />
                            <p className="text-muted-foreground mt-1 text-xs">
                                Wajib menyertakan bukti fisik atau tertulis verifikasi identitas (DEC-006).
                            </p>
                            {identifyForm.errors.evidence && <p className="text-destructive mt-1 text-xs">{identifyForm.errors.evidence}</p>}
                        </div>

                        <DialogFooter className="mt-6">
                            <Button type="button" variant="outline" onClick={() => setIdentifyingLot(null)}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={identifyForm.processing}>
                                {identifyForm.processing ? 'Menyimpan...' : 'Konfirmasi Identifikasi'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modal: Alokasi Dana ABT ke Perjanjian per TASK-REM-007 */}
            <Dialog open={!!allocatingLot} onOpenChange={(open) => !open && setAllocatingLot(null)}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Alokasikan Dana Parkir ke Perjanjian</DialogTitle>
                        <DialogDescription>
                            Alokasikan dana yang telah teridentifikasi ke perjanjian aktif mitra binaan per DEC-006 & DP-7.
                        </DialogDescription>
                    </DialogHeader>

                    {allocatingLot && (
                        <form onSubmit={handleAllocateSubmit} className="space-y-4">
                            {/* Ringkasan Mitra & Dana */}
                            <div className="bg-muted/40 space-y-2 rounded-lg border p-3 text-xs">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Mitra Pemilik:</span>
                                    <span className="text-foreground font-semibold">
                                        {allocatingLot.partner?.name} ({allocatingLot.partner?.partner_no_id ?? '-'})
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Sisa Kapasitas Dana:</span>
                                    <span className="font-semibold text-blue-600 dark:text-blue-400">
                                        {formatCurrency(allocatingLot.remaining_capacity)}
                                    </span>
                                </div>
                                <div className="flex justify-between border-t pt-1.5">
                                    <span className="text-muted-foreground">Total Sisa Utang Mitra (Semua Perjanjian):</span>
                                    <span className="text-foreground font-semibold">
                                        {formatCurrency(allocatingLot.partner?.total_remaining_debt ?? 0)}
                                    </span>
                                </div>
                            </div>

                            {/* Pilih Perjanjian */}
                            <div>
                                <Label htmlFor="allocate_agreement_id">Pilih Perjanjian Aktif *</Label>
                                {allocatingLot.partner?.active_agreements && allocatingLot.partner.active_agreements.length > 0 ? (
                                    <Select
                                        value={allocateForm.data.agreement_id}
                                        onValueChange={(val) => {
                                            const agr = allocatingLot.partner?.active_agreements?.find((a) => a.id === val);
                                            allocateForm.setData((prev) => ({
                                                ...prev,
                                                agreement_id: val,
                                                reason: agr ? `Alokasi dana ABT untuk perjanjian ${agr.agreement_number}` : prev.reason,
                                            }));
                                        }}
                                    >
                                        <SelectTrigger id="allocate_agreement_id">
                                            <SelectValue placeholder="Pilih perjanjian tujuan alokasi..." />
                                        </SelectTrigger>
                                        <SelectContent className="max-h-60">
                                            {allocatingLot.partner.active_agreements.map((agr) => (
                                                <SelectItem key={agr.id} value={agr.id}>
                                                    {agr.agreement_number} — Sisa: {formatCurrency(agr.remaining_balance)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                ) : (
                                    <div className="rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-700 dark:text-amber-400">
                                        Mitra ini tidak memiliki perjanjian berstatus Aktif. Dana tetap diparkir sampai perjanjian aktif tersedia.
                                    </div>
                                )}
                                {allocateForm.errors.agreement_id && (
                                    <p className="text-destructive mt-1 text-xs">{allocateForm.errors.agreement_id}</p>
                                )}
                            </div>

                            {/* Jumlah Alokasi */}
                            <div>
                                <div className="flex items-center justify-between">
                                    <Label htmlFor="allocate_amount">Jumlah Alokasi (Rp) *</Label>
                                    {allocatingLot.partner?.active_agreements?.find((a) => a.id === allocateForm.data.agreement_id) && (
                                        <div className="flex gap-1.5">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                className="h-6 px-1.5 text-[11px] text-blue-600 dark:text-blue-400"
                                                onClick={() => {
                                                    const agr = allocatingLot.partner?.active_agreements?.find(
                                                        (a) => a.id === allocateForm.data.agreement_id,
                                                    );
                                                    if (agr) {
                                                        const targetAmount = Math.min(allocatingLot.remaining_capacity, agr.remaining_balance);
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
                                                onClick={() => allocateForm.setData('amount', allocatingLot.remaining_capacity.toString())}
                                            >
                                                Semua Sisa Dana
                                            </Button>
                                        </div>
                                    )}
                                </div>
                                <Input
                                    id="allocate_amount"
                                    type="number"
                                    required
                                    min="1"
                                    max={allocatingLot.remaining_capacity}
                                    placeholder="Contoh: 500000"
                                    value={allocateForm.data.amount}
                                    onChange={(e) => allocateForm.setData('amount', e.target.value)}
                                />
                                {allocateForm.errors.amount && <p className="text-destructive mt-1 text-xs">{allocateForm.errors.amount}</p>}
                            </div>

                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div>
                                    <Label htmlFor="allocate_effective_date">Tanggal Efektif</Label>
                                    <Input
                                        id="allocate_effective_date"
                                        type="date"
                                        value={allocateForm.data.effective_date}
                                        onChange={(e) => allocateForm.setData('effective_date', e.target.value)}
                                    />
                                    {allocateForm.errors.effective_date && (
                                        <p className="text-destructive mt-1 text-xs">{allocateForm.errors.effective_date}</p>
                                    )}
                                </div>
                                <div>
                                    <Label htmlFor="allocate_reason">Alasan / Catatan</Label>
                                    <Input
                                        id="allocate_reason"
                                        value={allocateForm.data.reason}
                                        onChange={(e) => allocateForm.setData('reason', e.target.value)}
                                    />
                                    {allocateForm.errors.reason && <p className="text-destructive mt-1 text-xs">{allocateForm.errors.reason}</p>}
                                </div>
                            </div>

                            <DialogFooter className="mt-6">
                                <Button type="button" variant="outline" onClick={() => setAllocatingLot(null)}>
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
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
