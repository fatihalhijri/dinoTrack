<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Router tidak bisa dijangkau; aman dicoba ulang oleh job. */
final class RouterUnreachableException extends RuntimeException {}
