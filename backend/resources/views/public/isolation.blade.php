@php
    /** @var string $businessName */
    /** @var string|null $businessWhatsapp */
    /** @var \App\Models\Customer|null $customer */
    $waText = rawurlencode('Halo admin '.$businessName.', saya ingin menanyakan tagihan internet'.($code !== '' ? ' dengan kode pelanggan '.$code : '').'.');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Layanan Internet Dibatasi · {{ $businessName }}</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f4f5f7;color:#1f2933;line-height:1.5}
        main{max-width:480px;margin:0 auto;padding:24px 16px 40px}
        .brand{font-size:.9rem;font-weight:600;color:#52606d;text-transform:uppercase;letter-spacing:.04em}
        h1{font-size:1.4rem;margin:8px 0 12px}
        .card{background:#fff;border-radius:12px;padding:18px;margin-top:16px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
        .card h2{font-size:1.05rem;margin:0 0 10px}
        ol{padding-left:20px;margin:0}
        li{margin-bottom:6px}
        label{display:block;font-size:.9rem;font-weight:600;margin:12px 0 4px}
        input{width:100%;padding:11px 12px;border:1px solid #cbd2d9;border-radius:8px;font-size:1rem}
        .btn{display:block;width:100%;text-align:center;padding:12px;border:0;border-radius:8px;font-size:1rem;font-weight:600;text-decoration:none;cursor:pointer;margin-top:16px}
        .btn-primary{background:#1f6feb;color:#fff}
        .btn-wa{background:#1a7f37;color:#fff}
        .notice{padding:12px;border-radius:8px;font-size:.95rem}
        .notice-error{background:#fdecea;color:#8a1c1c}
        .notice-ok{background:#e6f4ea;color:#1a5e2a}
        .invoice{border-top:1px solid #e4e7eb;padding:10px 0}
        .invoice:first-of-type{border-top:0}
        .row{display:flex;justify-content:space-between;gap:12px}
        .muted{color:#616e7c;font-size:.88rem}
        .total{font-weight:700}
    </style>
</head>
<body>
<main>
    <div class="brand">{{ $businessName }}</div>
    <h1>Layanan internet Anda sedang dibatasi</h1>
    <p>Akses internet dibatasi karena ada tagihan yang belum dibayar. Layanan akan aktif kembali secara otomatis beberapa saat setelah tagihan dilunasi.</p>

    <section class="card">
        <h2>Cara membayar</h2>
        <ol>
            <li>Buka link tagihan yang kami kirim lewat WhatsApp, lalu bayar dengan QRIS dari aplikasi bank atau e-wallet apa pun.</li>
            <li>Atau bayar tunai/transfer melalui petugas kami.</li>
            <li>Setelah pembayaran diterima, internet aktif kembali tanpa perlu menghubungi kami. Matikan lalu nyalakan kembali modem jika belum tersambung.</li>
        </ol>
    </section>

    <section class="card">
        <h2>Cek tagihan</h2>

        @if ($isLookup && $customer === null)
            <p class="notice notice-error">Data tidak ditemukan. Periksa kembali kode pelanggan dan 4 digit terakhir nomor WhatsApp Anda.</p>
        @endif

        @if ($customer !== null)
            <p class="muted">Pelanggan: <strong>{{ $customer->name }}</strong> ({{ $customer->code }})</p>

            @forelse ($customer->invoices as $invoice)
                <div class="invoice">
                    <div class="row"><span>{{ $invoice->number }}</span><span class="total">{{ \App\Support\Money::format($invoice->total) }}</span></div>
                    <div class="muted">Periode {{ $invoice->period_start->translatedFormat('j M Y') }} – {{ $invoice->period_end->translatedFormat('j M Y') }}</div>
                    <div class="muted">Jatuh tempo {{ $invoice->due_at->translatedFormat('j F Y') }}</div>
                </div>
            @empty
                <p class="notice notice-ok">Tidak ada tagihan yang belum dibayar. Jika internet belum aktif dalam beberapa menit, hubungi admin kami.</p>
            @endforelse
        @else
            <form method="GET" action="{{ route('isolation.show') }}">
                <label for="kode">Kode pelanggan</label>
                <input id="kode" name="kode" value="{{ $code }}" placeholder="PLG-000123" autocomplete="off" required maxlength="30">

                <label for="hp">4 digit terakhir nomor WhatsApp</label>
                <input id="hp" name="hp" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" placeholder="1234" autocomplete="off" required>

                <button type="submit" class="btn btn-primary">Cek tagihan</button>
            </form>
        @endif
    </section>

    @if ($businessWhatsapp !== null)
        <a class="btn btn-wa" href="https://wa.me/{{ $businessWhatsapp }}?text={{ $waText }}" rel="noopener">Hubungi admin via WhatsApp</a>
        {{-- WhatsApp bisa ikut terblokir selama isolir, jadi nomornya juga ditampilkan untuk ditelepon. --}}
        <p class="muted" style="text-align:center">Admin: +{{ $businessWhatsapp }}</p>
    @endif
</main>
</body>
</html>
