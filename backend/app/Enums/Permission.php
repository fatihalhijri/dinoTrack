<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Nama permission spatie. Pembagian ke role ada di Role::permissions().
 */
enum Permission: string
{
    case CustomersView = 'customers.view';
    case CustomersCreate = 'customers.create';
    case CustomersUpdate = 'customers.update';
    case CustomersDelete = 'customers.delete';
    case CustomersActivate = 'customers.activate';
    case CustomersTerminate = 'customers.terminate';
    case CustomersIsolate = 'customers.isolate';
    case PackagesView = 'packages.view';
    case PackagesManage = 'packages.manage';
    case RoutersManage = 'routers.manage';
    case InvoicesView = 'invoices.view';
    case InvoicesCancel = 'invoices.cancel';
    case InvoicesResend = 'invoices.resend';
    case PaymentsView = 'payments.view';
    case PaymentsRecord = 'payments.record';
    case PaymentsReview = 'payments.review';
    case ReportsView = 'reports.view';
    case SettingsManage = 'settings.manage';
    case UsersManage = 'users.manage';

    public function label(): string
    {
        return match ($this) {
            self::CustomersView => 'Lihat pelanggan',
            self::CustomersCreate => 'Tambah pelanggan',
            self::CustomersUpdate => 'Ubah data pelanggan',
            self::CustomersDelete => 'Hapus pelanggan (salah input)',
            self::CustomersActivate => 'Tandai pelanggan terpasang',
            self::CustomersTerminate => 'Berhentikan dan aktifkan kembali pelanggan',
            self::CustomersIsolate => 'Isolir dan buka isolir manual',
            self::PackagesView => 'Lihat paket',
            self::PackagesManage => 'Kelola paket',
            self::RoutersManage => 'Kelola router',
            self::InvoicesView => 'Lihat tagihan',
            self::InvoicesCancel => 'Batalkan tagihan',
            self::InvoicesResend => 'Kirim ulang tagihan',
            self::PaymentsView => 'Lihat pembayaran',
            self::PaymentsRecord => 'Catat pembayaran manual',
            self::PaymentsReview => 'Tinjau pembayaran anomali',
            self::ReportsView => 'Lihat laporan',
            self::SettingsManage => 'Kelola pengaturan',
            self::UsersManage => 'Kelola pengguna',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
