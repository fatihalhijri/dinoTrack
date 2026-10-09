import { Head, Link, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    ArrowLeft,
    Clock,
    FileQuestion,
    Gauge,
    LayoutDashboard,
    House,
    RotateCw,
    ServerCrash,
    ShieldX,
    Wrench,
} from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { dashboard, home } from '@/routes';

type ErrorContent = {
    title: string;
    description: string;
    icon: LucideIcon;
    canReload: boolean;
};

const errors: Record<number, ErrorContent> = {
    403: {
        title: 'Akses ditolak',
        description:
            'Akun Anda tidak memiliki izin untuk membuka halaman atau menjalankan aksi ini. Hubungi admin jika Anda memerlukannya.',
        icon: ShieldX,
        canReload: false,
    },
    404: {
        title: 'Halaman tidak ditemukan',
        description:
            'Halaman yang Anda cari tidak ada atau datanya sudah dihapus. Periksa kembali alamatnya.',
        icon: FileQuestion,
        canReload: false,
    },
    419: {
        title: 'Sesi halaman kedaluwarsa',
        description:
            'Halaman ini terlalu lama dibiarkan sehingga sesinya berakhir. Muat ulang halaman, lalu ulangi langkah Anda.',
        icon: Clock,
        canReload: true,
    },
    429: {
        title: 'Terlalu banyak permintaan',
        description:
            'Permintaan dari akun Anda terlalu sering. Tunggu sebentar sebelum mencoba lagi.',
        icon: Gauge,
        canReload: false,
    },
    500: {
        title: 'Terjadi kesalahan',
        description:
            'Ada masalah di server kami. Coba lagi beberapa saat lagi; jika terus terjadi, hubungi admin.',
        icon: ServerCrash,
        canReload: false,
    },
    503: {
        title: 'Layanan sedang tidak tersedia',
        description:
            'Sistem sedang dalam pemeliharaan atau sibuk. Coba lagi dalam beberapa menit.',
        icon: Wrench,
        canReload: true,
    },
};

/**
 * Halaman error untuk route admin (lihat `AppServiceProvider::configureErrorPages`). Tanpa layout
 * aplikasi karena 404 untuk URL tak dikenal terjadi sebelum session dibaca, sehingga user bisa
 * tampak sebagai tamu.
 */
export default function ErrorPage({ status }: { status: number }) {
    const { auth } = usePage().props;
    const error = errors[status] ?? errors[500];
    const Icon = error.icon;

    return (
        <>
            <Head title={error.title} />
            <main className="flex min-h-svh flex-col items-center justify-center bg-background p-6 md:p-10">
                <div className="flex w-full max-w-md flex-col items-center gap-6 text-center">
                    <AppLogoIcon className="size-9 fill-current text-foreground" />

                    <div className="flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground">
                        <Icon className="size-6" aria-hidden="true" />
                    </div>

                    <div className="space-y-2">
                        <p className="text-sm font-medium text-muted-foreground">
                            Kode {status}
                        </p>
                        <h1 className="text-xl font-semibold">{error.title}</h1>
                        <p className="text-sm text-muted-foreground">
                            {error.description}
                        </p>
                    </div>

                    <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                        {error.canReload ? (
                            <Button
                                className="h-10"
                                onClick={() => window.location.reload()}
                            >
                                <RotateCw aria-hidden="true" />
                                Muat ulang
                            </Button>
                        ) : null}
                        <Button
                            variant="outline"
                            className="h-10"
                            onClick={() => window.history.back()}
                        >
                            <ArrowLeft aria-hidden="true" />
                            Kembali
                        </Button>
                        {auth.user === null ? (
                            <Button
                                asChild
                                variant={
                                    error.canReload ? 'outline' : 'default'
                                }
                                className="h-10"
                            >
                                <Link href={home()}>
                                    <House aria-hidden="true" />
                                    Ke halaman utama
                                </Link>
                            </Button>
                        ) : (
                            <Button
                                asChild
                                variant={
                                    error.canReload ? 'outline' : 'default'
                                }
                                className="h-10"
                            >
                                <Link href={dashboard()}>
                                    <LayoutDashboard aria-hidden="true" />
                                    Ke dashboard
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>
            </main>
        </>
    );
}
