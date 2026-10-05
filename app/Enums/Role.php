<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Nama role spatie beserta matriks permission-nya (sumber tunggal, lihat docs/01).
 */
enum Role: string
{
    case Admin = 'admin';
    case Kasir = 'kasir';
    case Teknisi = 'teknisi';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Kasir => 'Kasir',
            self::Teknisi => 'Teknisi',
        };
    }

    /**
     * Admin juga melewati semua pengecekan lewat Gate::before; daftarnya tetap lengkap
     * agar isi database konsisten dengan hak akses sebenarnya.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => Permission::cases(),
            self::Kasir => [
                Permission::CustomersView,
                Permission::CustomersActivate,
                Permission::PackagesView,
                Permission::InvoicesView,
                Permission::InvoicesResend,
                Permission::PaymentsView,
                Permission::PaymentsRecord,
            ],
            self::Teknisi => [
                Permission::CustomersView,
                Permission::CustomersCreate,
                Permission::PackagesView,
            ],
        };
    }
}
