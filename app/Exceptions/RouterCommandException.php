<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Router menolak perintah (`!trap`), misalnya profil PPP tidak ada. Biasanya salah konfigurasi
 * router, sehingga mengulang tidak akan membantu.
 */
final class RouterCommandException extends RuntimeException {}
