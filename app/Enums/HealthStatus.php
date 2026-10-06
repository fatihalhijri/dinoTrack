<?php

declare(strict_types=1);

namespace App\Enums;

enum HealthStatus: string
{
    case Ok = 'ok';

    /** Perlu ditinjau admin, tetapi aplikasi tetap berjalan (router mati, pesan WA gagal). */
    case Warning = 'warning';

    /** Aplikasi tidak bekerja semestinya (database mati, worker berhenti, pembayaran tertahan). */
    case Fail = 'fail';

    /** Tidak berlaku untuk konfigurasi ini (misalnya Redis di development). */
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'OK',
            self::Warning => 'Peringatan',
            self::Fail => 'Gagal',
            self::Skipped => 'Dilewati',
        };
    }
}
