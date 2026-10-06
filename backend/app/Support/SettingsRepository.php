<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Pengaturan dari tabel `settings` dengan nilai default dari docs/04. Disimpan di cache
 * dan dibersihkan otomatis setiap kali model Setting disimpan atau dihapus, jadi pengaturan
 * harus diubah lewat model (bukan query update massal yang tidak memicu event).
 *
 * Didaftarkan `scoped` di container: satu instance per request/job, sehingga nilai cukup
 * dibaca dari cache sekali per request/job.
 */
final class SettingsRepository
{
    /** @var array<string, mixed>|null */
    private ?array $values = null;

    /** @var array<string, int|bool> */
    public const array DEFAULTS = [
        'billing.due_days' => 7,
        'billing.grace_days' => 3,
        'billing.reminder_days_before' => 3,
        'billing.prorate_first_month' => true,
        'billing.auto_isolate' => true,
        'billing.auto_activate' => true,
    ];

    private const string CACHE_KEY = 'settings';

    public function flush(): void
    {
        $this->values = null;
        Cache::forget(self::CACHE_KEY);
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key] ?? self::DEFAULTS[$key] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        /** @var array<string, mixed> */
        return $this->values ??= Cache::rememberForever(self::CACHE_KEY, fn (): array => Setting::query()->pluck('value', 'key')->all());
    }

    public function dueDays(): int
    {
        return (int) $this->get('billing.due_days');
    }

    public function graceDays(): int
    {
        return (int) $this->get('billing.grace_days');
    }

    public function reminderDaysBefore(): int
    {
        return (int) $this->get('billing.reminder_days_before');
    }

    public function prorateFirstMonth(): bool
    {
        return (bool) $this->get('billing.prorate_first_month');
    }

    public function autoIsolate(): bool
    {
        return (bool) $this->get('billing.auto_isolate');
    }

    public function autoActivate(): bool
    {
        return (bool) $this->get('billing.auto_activate');
    }

    /**
     * Profil usaha diisi admin lewat UpdateBusinessProfile dan tidak di-seed; selama kosong
     * dipakai APP_NAME.
     */
    public function businessName(): string
    {
        $name = $this->get('business.name');

        return is_string($name) && trim($name) !== '' ? $name : (string) config('app.name');
    }

    public function businessAddress(): ?string
    {
        $address = $this->get('business.address');

        return is_string($address) && trim($address) !== '' ? $address : null;
    }

    /**
     * Nomor WhatsApp admin (format 62xxx) untuk tombol kontak di halaman publik; null jika belum diisi.
     */
    public function businessWhatsapp(): ?string
    {
        $phone = $this->get('business.whatsapp');

        return is_string($phone) && trim($phone) !== '' ? PhoneNumber::normalize($phone) : null;
    }
}
