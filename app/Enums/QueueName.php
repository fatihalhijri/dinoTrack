<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Nama queue yang dipisah per jenis pekerjaan, masing-masing dengan worker Supervisor sendiri
 * (docs/10-deploy.md), agar aktivasi setelah bayar tidak mengantre di belakang ratusan pesan WA.
 */
enum QueueName: string
{
    /** Pembayaran (notifikasi webhook) dan pekerjaan lain yang harus cepat. */
    case Default = 'default';

    /** Perintah ke router Mikrotik (isolir, aktivasi, profil, nonaktif secret). */
    case Network = 'network';

    /** Pesan WhatsApp; dibatasi rate limit sehingga antreannya bisa panjang. */
    case Notifications = 'notifications';
}
