<?php

declare(strict_types=1);

namespace App\Concerns;

/**
 * Aturan bersama Form Request halaman daftar: pencarian dan jumlah baris per halaman.
 * Dipakai oleh kelas turunan FormRequest.
 */
trait IndexQueryRules
{
    /** @var list<int> */
    public const array PER_PAGE_OPTIONS = [10, 20, 50, 100];

    public const int DEFAULT_PER_PAGE = 20;

    /**
     * @return array<string, array<int, string>>
     */
    protected function indexRules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'in:'.implode(',', self::PER_PAGE_OPTIONS)],
        ];
    }

    public function perPage(): int
    {
        return $this->filled('per_page') ? $this->integer('per_page') : self::DEFAULT_PER_PAGE;
    }

    public function searchTerm(): ?string
    {
        $search = $this->string('search')->trim()->toString();

        return $search === '' ? null : $search;
    }

    /**
     * @return array<string, string>
     */
    protected function indexAttributes(): array
    {
        return [
            'search' => 'pencarian',
            'per_page' => 'jumlah per halaman',
        ];
    }
}
