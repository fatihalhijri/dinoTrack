# 06 — Standar Kode

## Umum

- `declare(strict_types=1);` di setiap file PHP baru
- Type hint untuk semua parameter dan return type
- Gunakan `readonly` dan constructor property promotion
- Nama kelas, method, variabel dalam **Bahasa Inggris**; komentar, pesan
  validasi, dan teks untuk pengguna dalam **Bahasa Indonesia**
- Komentar hanya untuk menjelaskan *mengapa*, bukan *apa*

## Action

```php
final class IsolateCustomer
{
    public function __construct(
        private readonly NetworkController $network,
        private readonly ActivityLogger $logger,
    ) {}

    public function handle(Customer $customer, ?User $by = null, ?string $reason = null): void
    {
        // ...
    }
}
```

- Satu public method `handle()`
- Dipanggil via container: `app(IsolateCustomer::class)->handle(...)`
- Tidak mengakses `request()` atau `auth()` langsung; terima sebagai parameter

## Controller

- Validasi via Form Request
- Otorisasi via `$this->authorize()` atau `Gate`
- Panggil Action, kembalikan Inertia response / redirect
- Maksimal ~15 baris per method

## Model

- `$fillable` eksplisit (jangan `$guarded = []`)
- `casts()` untuk Enum, tanggal, `encrypted`, `array`
- Scope untuk query yang sering dipakai (`scopeOverdue`, `scopeActive`)
- Tidak ada logika bisnis berat di model

## Enum

```php
enum InvoiceStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';

    public function label(): string { /* Bahasa Indonesia */ }
}
```

## Job

- `$tries`, `$backoff` (array, eksponensial), `$timeout`
- Implementasi `ShouldBeUnique` jika aksi tidak boleh berjalan ganda
  (contoh: isolir pelanggan yang sama)
- Method `failed()` mencatat log dan menandai untuk ditinjau admin

## Uang

- Selalu integer rupiah
- Format tampilan lewat helper `Money::format(150000)` → `Rp150.000`

## Test (Pest)

- Nama test deskriptif dalam Bahasa Indonesia:
  `it('tidak membuat tagihan ganda untuk periode yang sama')`
- Gunakan factory dengan state (`Customer::factory()->isolated()`)
- Fake semua integrasi eksternal
- Gunakan `Carbon::setTestNow()` / `$this->travelTo()` untuk logika tanggal
- Setiap aturan di `docs/04-aturan-bisnis.md` minimal punya satu test

## Git

- Conventional Commits, Bahasa Indonesia:
  `feat(scope): ...`, `fix(scope): ...`, `test(scope): ...`, `refactor(scope): ...`, `chore: ...`
- Satu commit per tahap atau sub-fitur yang utuh
