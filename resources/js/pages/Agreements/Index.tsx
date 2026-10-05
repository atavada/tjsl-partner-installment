import AgreementTimeline from '@/components/AgreementTimeline';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { AgreementData } from '@/types/agreement';
import { PartnerData } from '@/types/partner';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Clock, FileText, Info, Plus, ShieldAlert } from 'lucide-react';

interface IndexProps {
    partner: PartnerData;
    agreements: AgreementData[];
}

export default function Index({ partner, agreements }: IndexProps) {
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
    ];

    const activeAgreementsCount = agreements.filter((a) => a.lifecycle_status === 'active').length;
    const draftAgreementsCount = agreements.filter((a) => a.is_draft).length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Riwayat Perjanjian - ${partner.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header section */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-foreground text-2xl font-bold tracking-tight">Riwayat Perjanjian Mitra</h1>
                            <Badge variant="outline" className="font-mono text-xs">
                                {partner.partner_no_id ?? 'NO ID Belum Ada'}
                            </Badge>
                        </div>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Garis waktu perjanjian, status independen, dan dokumen pinjaman untuk{' '}
                            <span className="text-foreground font-semibold">{partner.name}</span>.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button asChild>
                            <Link href={`/partners/${partner.id}/agreements/create`} className="inline-flex items-center gap-1.5">
                                <Plus className="h-4 w-4" /> Buat Perjanjian Baru
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={`/partners/${partner.id}`} className="inline-flex items-center gap-1.5">
                                <ArrowLeft className="h-4 w-4" /> Kembali ke Detail Mitra
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Status overview cards */}
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <Card>
                        <CardContent className="p-4">
                            <span className="text-muted-foreground flex items-center gap-1.5 text-xs font-medium">
                                <FileText className="h-3.5 w-3.5" /> Total Perjanjian
                            </span>
                            <p className="text-foreground mt-1 text-2xl font-bold">{agreements.length}</p>
                            <p className="text-muted-foreground text-xs">Semua riwayat perjanjian</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <span className="text-muted-foreground flex items-center gap-1.5 text-xs font-medium">
                                <FileText className="text-primary h-3.5 w-3.5" /> Perjanjian Aktif
                            </span>
                            <p className="text-foreground mt-1 text-2xl font-bold">{activeAgreementsCount}</p>
                            <p className="text-muted-foreground text-xs">Siklus berjalan</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <span className="text-muted-foreground flex items-center gap-1.5 text-xs font-medium">
                                <Clock className="h-3.5 w-3.5" /> Draft / Pengajuan
                            </span>
                            <p className="text-foreground mt-1 text-2xl font-bold">{draftAgreementsCount}</p>
                            <p className="text-muted-foreground text-xs">Tidak menimbulkan piutang</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <span className="text-muted-foreground flex items-center gap-1.5 text-xs font-medium">
                                <ShieldAlert className="h-3.5 w-3.5 text-amber-500" /> Status Saldo
                            </span>
                            <p className="mt-1 text-base font-bold text-amber-600 dark:text-amber-400">Unverified</p>
                            <p className="text-muted-foreground text-xs">Tertunda per DEC-008</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Important notices banner */}
                <div className="border-border/80 bg-muted/40 text-muted-foreground space-y-1.5 rounded-lg border p-3.5 text-xs">
                    <div className="text-foreground flex items-center gap-2 font-medium">
                        <Info className="text-primary h-4 w-4" /> Kebijakan Integritas Keuangan & Data (PRD §4 & Keputusan):
                    </div>
                    <ul className="list-disc space-y-1 pl-6">
                        <li>
                            <strong className="text-foreground">DEC-001:</strong> Nomor perjanjian adalah kunci pengelompokan batch/kelompok usaha per
                            tahun, bukan identifier unik mitra.
                        </li>
                        <li>
                            <strong className="text-foreground">PRD §4 Invariant 8:</strong> Tiga dimensi status ditampilkan independen (Siklus
                            Kontrak, Kolektibilitas Risiko, dan Alur TTD Dokumen).
                        </li>
                        <li>
                            <strong className="text-foreground">DEC-008 & PRD FR-02:</strong> Perhitungan saldo piutang ditangguhkan sehingga selalu
                            berstatus <code className="bg-muted text-foreground rounded px-1">unverified</code> (tidak pernah menampilkan saldo nol
                            palsu). Perjanjian draft tidak menimbulkan kewajiban piutang.
                        </li>
                    </ul>
                </div>

                {/* Timeline view */}
                <div className="space-y-4">
                    <h2 className="text-foreground text-lg font-semibold">Garis Waktu Perjanjian</h2>
                    <AgreementTimeline partnerId={partner.id} agreements={agreements} />
                </div>
            </div>
        </AppLayout>
    );
}
