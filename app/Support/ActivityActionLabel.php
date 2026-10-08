<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Label Bahasa Indonesia untuk `activity_logs.action` yang tampil di riwayat pelanggan.
 * Nama aksi adalah kontrak (docs/03), sehingga labelnya dipetakan di sini, bukan diubah
 * di tempat log ditulis.
 */
final class ActivityActionLabel
{
    /** @var array<string, string> */
    private const array LABELS = [
        'customer.created' => 'Pelanggan didaftarkan',
        'customer.updated' => 'Data pelanggan diubah',
        'customer.deleted' => 'Pelanggan dihapus',
        'customer.activated' => 'Ditandai terpasang',
        'customer.terminated' => 'Berhenti berlangganan',
        'customer.reactivated' => 'Didaftarkan kembali',
        'customer.package_changed' => 'Paket diganti',
        'customer.package_change_scheduled' => 'Ganti paket dijadwalkan',
        'customer.package_change_cancelled' => 'Rencana ganti paket dibatalkan',
        'customer.isolation_requested' => 'Isolir manual diminta',
        'customer.isolated' => 'Diisolir',
        'customer.isolation_skipped' => 'Isolir dilewati',
        'customer.isolation_failed' => 'Isolir gagal di router',
        'customer.activation_requested' => 'Buka isolir diminta',
        'customer.isolation_lifted' => 'Isolir dibuka',
        'customer.activation_skipped' => 'Buka isolir dilewati',
        'customer.activation_failed' => 'Buka isolir gagal di router',
        'customer.profile_applied' => 'Profil paket dipasang di router',
        'customer.profile_skipped' => 'Pemasangan profil paket dilewati',
        'customer.profile_failed' => 'Pemasangan profil paket gagal di router',
        'customer.secret_disabled' => 'Secret PPPoE dinonaktifkan',
        'customer.secret_disable_skipped' => 'Penonaktifan secret PPPoE dilewati',
        'customer.secret_disable_failed' => 'Penonaktifan secret PPPoE gagal',
        'subscription.package_applied' => 'Paket baru mulai berlaku',
        'subscription.package_corrected' => 'Paket dikoreksi saat terbit ulang',
    ];

    /** Aksi yang belum punya label ditampilkan apa adanya agar tetap terbaca. */
    public static function for(string $action): string
    {
        return self::LABELS[$action] ?? $action;
    }
}
