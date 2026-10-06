<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Customers\LookupCustomerInvoices;
use App\Support\SettingsRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Halaman tujuan redirect Mikrotik untuk pelanggan yang diisolir. Tanpa session dan cookie
 * (setiap request HTTP dari perangkat terisolir diarahkan ke sini), sehingga form cek tagihan
 * memakai GET dan masukan yang salah cukup ditampilkan sebagai pesan, bukan error validasi.
 */
class IsolationPageController extends Controller
{
    public function __invoke(Request $request, LookupCustomerInvoices $lookup, SettingsRepository $settings): Response
    {
        $code = $request->string('kode')->trim()->toString();
        $phoneSuffix = $request->string('hp')->trim()->toString();
        $isLookup = $code !== '' || $phoneSuffix !== '';

        return response()->view('public.isolation', [
            'businessName' => $settings->businessName(),
            'businessWhatsapp' => $settings->businessWhatsapp(),
            'code' => $code,
            'isLookup' => $isLookup,
            'customer' => $isLookup ? $lookup->handle($code, $phoneSuffix) : null,
        ])->header('Cache-Control', 'no-store');
    }
}
