import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { PartnerData } from '@/types/partner';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Building2, CreditCard, FileText, Phone, Plus, ShieldCheck, User } from 'lucide-react';

interface ShowProps {
    partner: PartnerData;
}

export default function Show({ partner }: ShowProps) {
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

                    <Button variant="outline" asChild>
                        <Link href={route('partners.index')} className="inline-flex items-center gap-1.5">
                            <ArrowLeft className="h-4 w-4" /> Kembali ke Pencarian
                        </Link>
                    </Button>
                </div>

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

                {/* Agreement Summary Card */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <FileText className="text-primary h-4 w-4" /> Perjanjian Terkait ({partner.agreements_count})
                                </CardTitle>
                                <CardDescription>Mitra ini terdaftar pada {partner.agreements_count} nomor perjanjian.</CardDescription>
                            </div>
                            <div className="flex items-center gap-2">
                                <Button size="sm" variant="outline" asChild>
                                    <Link href={`/partners/${partner.id}/agreements/create`} className="inline-flex items-center gap-1.5">
                                        <Plus className="h-3.5 w-3.5" /> Buat Perjanjian
                                    </Link>
                                </Button>
                                <Button size="sm" asChild>
                                    <Link href={`/partners/${partner.id}/agreements`} className="inline-flex items-center gap-1.5">
                                        Lihat Riwayat Perjanjian <ArrowRight className="h-3.5 w-3.5" />
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <p className="text-muted-foreground text-sm">
                            Detail perjanjian, status independen (siklus, kolektibilitas, tanda tangan), dokumen kontrak, dan garis waktu keterkaitan
                            perjanjian dikelola pada modul Riwayat Perjanjian. Nomor perjanjian berfungsi sebagai kunci pengelompokan batch/kelompok
                            usaha per tahun (DEC-001).
                        </p>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
