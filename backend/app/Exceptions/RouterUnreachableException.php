<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/** Router tidak bisa dijangkau; aman dicoba ulang oleh job. */
final class RouterUnreachableException extends RuntimeException
{
    /**
     * @param  string|null  $summary  pesan tanpa alamat router untuk data yang dilihat kasir/teknisi
     */
    public function __construct(
        string $message = '',
        private readonly ?string $summary = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    public function summary(): string
    {
        return $this->summary ?? $this->getMessage();
    }
}
