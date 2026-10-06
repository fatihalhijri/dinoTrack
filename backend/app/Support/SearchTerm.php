<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Pola LIKE untuk kotak pencarian. Karakter wildcard dari input (`%`, `_`) di-escape agar
 * dicari apa adanya, bukan dijadikan pola.
 */
final class SearchTerm
{
    public static function contains(string $term): string
    {
        return '%'.addcslashes($term, '\\%_').'%';
    }
}
