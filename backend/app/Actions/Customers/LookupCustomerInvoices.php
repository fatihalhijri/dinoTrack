<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Models\Customer;

/**
 * Cek tagihan dari halaman isolir publik (tanpa login). Kode pelanggan berurutan dan mudah
 * ditebak, sehingga wajib disertai 4 digit terakhir nomor WhatsApp. Semua kegagalan (format
 * salah, kode tidak ada, digit salah) sama-sama mengembalikan null agar tidak bisa dipakai
 * untuk menebak kode yang terdaftar.
 */
final class LookupCustomerInvoices
{
    public const int PHONE_SUFFIX_LENGTH = 4;

    /**
     * @return Customer|null pelanggan dengan relasi `invoices` berisi tagihan yang belum dibayar
     */
    public function handle(string $code, string $phoneSuffix): ?Customer
    {
        $code = strtoupper(trim($code));
        $phoneSuffix = trim($phoneSuffix);

        if ($code === '' || mb_strlen($code) > 30 || preg_match('/^\d{'.self::PHONE_SUFFIX_LENGTH.'}$/', $phoneSuffix) !== 1) {
            return null;
        }

        $customer = Customer::query()->where('code', $code)->first();

        if ($customer === null || ! hash_equals(substr($customer->phone, -self::PHONE_SUFFIX_LENGTH), $phoneSuffix)) {
            return null;
        }

        return $customer->setRelation('invoices', $customer->invoices()->outstanding()->orderBy('due_at')->get());
    }
}
