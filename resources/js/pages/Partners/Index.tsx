import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { PaginatedResponse, PartnerData, PartnerSearchFilters, SearchType } from '@/types/partner';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, RotateCcw, Search, UserCheck } from 'lucide-react';
import React, { useState } from 'react';

interface IndexProps {
    partners: PaginatedResponse<PartnerData>;
    filters: PartnerSearchFilters;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Daftar Mitra',
        href: '/partners',
    },
];

export default function Index({ partners, filters }: IndexProps) {
    const [searchQuery, setSearchQuery] = useState(filters.query ?? '');
    const [searchType, setSearchType] = useState<SearchType>(filters.type ?? 'no_id');
    const [perPage, setPerPage] = useState<string>(String(filters.per_page ?? 15));

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();

        router.get(
            route('partners.index'),
            {
                query: searchQuery.trim() || undefined,
                type: searchQuery.trim() ? searchType : undefined,
                per_page: perPage,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const handleReset = () => {
        setSearchQuery('');
        setSearchType('no_id');
        router.get(route('partners.index'), {}, { preserveState: true });
    };

    const handlePerPageChange = (value: string) => {
        setPerPage(value);
        router.get(
            route('partners.index'),
            {
                query: searchQuery.trim() || undefined,
                type: searchQuery.trim() ? searchType : undefined,
                per_page: value,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pencarian Mitra" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-foreground text-2xl font-bold tracking-tight">Pencarian Mitra</h1>
                    <p className="text-muted-foreground text-sm">
                        Cari mitra program kemitraan berdasarkan NO ID resmi, nama/alias, nomor perjanjian, atau nomor Virtual Account (VA).
                    </p>
                </div>

                {/* Filter Card */}
                <Card>
                    <CardHeader className="pb-4">
                        <CardTitle className="text-base">Filter Pencarian</CardTitle>
                        <CardDescription>Pilih tipe pencarian untuk melakukan lookup data mitra.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSearch} className="flex flex-col gap-4 md:flex-row md:items-end">
                            <div className="w-full md:w-56">
                                <label className="text-foreground mb-2 block text-xs font-medium">Tipe Pencarian</label>
                                <Select value={searchType} onValueChange={(val: SearchType) => setSearchType(val)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih tipe" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="no_id">NO ID (Persis)</SelectItem>
                                        <SelectItem value="name">Nama / Alias</SelectItem>
                                        <SelectItem value="agreement">Nomor Perjanjian</SelectItem>
                                        <SelectItem value="va">Nomor VA</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="flex-1">
                                <label className="text-foreground mb-2 block text-xs font-medium">Kata Kunci</label>
                                <div className="relative">
                                    <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                    <Input
                                        type="text"
                                        placeholder={
                                            searchType === 'no_id'
                                                ? 'Contoh: 0001234567 (angka 0 di awal dipertahankan)'
                                                : searchType === 'agreement'
                                                  ? 'Contoh: 0001/SP-TJSL/2026'
                                                  : searchType === 'va'
                                                    ? 'Contoh: 0000000012345678'
                                                    : 'Masukkan nama mitra atau alias...'
                                        }
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
                                        className="pl-9"
                                    />
                                </div>
                            </div>

                            <div className="flex gap-2">
                                <Button type="submit">
                                    <Search className="mr-1 h-4 w-4" /> Cari
                                </Button>
                                {(filters.query || filters.type) && (
                                    <Button type="button" variant="outline" onClick={handleReset}>
                                        <RotateCcw className="mr-1 h-4 w-4" /> Reset
                                    </Button>
                                )}
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* Results Card */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between pb-4">
                        <div>
                            <CardTitle className="text-base">Hasil Pencarian</CardTitle>
                            <CardDescription>
                                Menampilkan {partners.from ?? 0}–{partners.to ?? 0} dari total {partners.total} kandidat mitra.
                            </CardDescription>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="text-muted-foreground text-xs">Per halaman:</span>
                            <Select value={perPage} onValueChange={handlePerPageChange}>
                                <SelectTrigger className="h-8 w-20">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="10">10</SelectItem>
                                    <SelectItem value="15">15</SelectItem>
                                    <SelectItem value="25">25</SelectItem>
                                    <SelectItem value="50">50</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        {partners.data.length === 0 ? (
                            <div className="flex flex-col items-center justify-center p-12 text-center">
                                <UserCheck className="text-muted-foreground/50 mb-4 h-12 w-12" />
                                <h3 className="text-foreground text-base font-medium">Tidak ada mitra ditemukan</h3>
                                <p className="text-muted-foreground mt-1 max-w-sm text-sm">
                                    {filters.query
                                        ? `Tidak ditemukan mitra dengan kata kunci "${filters.query}" untuk tipe ${filters.type}. Pastikan nomor atau ejaan sesuai.`
                                        : 'Belum ada data mitra terdaftar dalam sistem.'}
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/50 text-muted-foreground border-b text-xs font-medium uppercase">
                                        <tr>
                                            <th className="px-6 py-3">NO ID</th>
                                            <th className="px-6 py-3">Nama Mitra & Alias</th>
                                            <th className="px-6 py-3">NIK (Masked)</th>
                                            <th className="px-6 py-3">Wilayah</th>
                                            <th className="px-6 py-3">Status Verifikasi</th>
                                            <th className="px-6 py-3">Perjanjian</th>
                                            <th className="px-6 py-3 text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-border divide-y">
                                        {partners.data.map((partner) => (
                                            <tr key={partner.id} className="hover:bg-muted/30 transition-colors">
                                                <td className="text-foreground px-6 py-4 font-mono font-medium whitespace-nowrap">
                                                    {partner.partner_no_id ?? <span className="text-muted-foreground italic">Belum ada</span>}
                                                </td>
                                                <td className="px-6 py-4">
                                                    <div className="text-foreground font-medium">{partner.name}</div>
                                                    {partner.aliases && partner.aliases.length > 0 && (
                                                        <div className="text-muted-foreground mt-0.5 text-xs">
                                                            Alias: {partner.aliases.map((a) => a.name_raw).join(', ')}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="text-muted-foreground px-6 py-4 font-mono whitespace-nowrap">{partner.nik ?? '-'}</td>
                                                <td className="text-muted-foreground px-6 py-4">{partner.region ?? '-'}</td>
                                                <td className="px-6 py-4">
                                                    <Badge variant={getVerificationBadgeVariant(partner.verification_state)}>
                                                        {partner.verification_badge_label}
                                                    </Badge>
                                                </td>
                                                <td className="text-muted-foreground px-6 py-4 whitespace-nowrap">
                                                    {partner.agreements_count} Perjanjian
                                                </td>
                                                <td className="px-6 py-4 text-right whitespace-nowrap">
                                                    <Button variant="ghost" size="sm" asChild>
                                                        <Link href={route('partners.show', partner.id)} className="inline-flex items-center gap-1">
                                                            Detail <ArrowRight className="h-3 w-3" />
                                                        </Link>
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {/* Pagination Links */}
                        {partners.links && partners.links.length > 3 && (
                            <div className="flex flex-col items-center justify-between gap-4 border-t p-4 sm:flex-row">
                                <span className="text-muted-foreground text-xs">
                                    Halaman {partners.current_page} dari {partners.last_page}
                                </span>
                                <div className="flex flex-wrap items-center gap-1">
                                    {partners.links.map((link, idx) => {
                                        if (link.url === null) {
                                            return (
                                                <Button
                                                    key={idx}
                                                    variant="outline"
                                                    size="sm"
                                                    disabled
                                                    className="h-8 px-3 text-xs opacity-50"
                                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                                />
                                            );
                                        }

                                        return (
                                            <Button
                                                key={idx}
                                                variant={link.active ? 'default' : 'outline'}
                                                size="sm"
                                                asChild
                                                className="h-8 px-3 text-xs"
                                            >
                                                <Link href={link.url} preserveState preserveScroll dangerouslySetInnerHTML={{ __html: link.label }} />
                                            </Button>
                                        );
                                    })}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
