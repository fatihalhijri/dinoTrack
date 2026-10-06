@php
    /** @var string $businessName */
    /** @var string|null $businessWhatsapp */
    /** @var \App\Models\Customer|null $customer */
    $waText = rawurlencode('Halo admin '.$businessName.', saya ingin menanyakan tagihan internet'.($code !== '' ? ' dengan kode pelanggan '.$code : '').'.');
@endphp
@extends('public.layout')

@section('title', 'Layanan Internet Dibatasi')

@section('content')
    <h1>Layanan internet Anda sedang dibatasi</h1>
    <p>Akses internet dibatasi karena ada tagihan yang belum dibayar. Layanan akan aktif kembali secara otomatis beberapa saat setelah tagihan dilunasi.</p>

    <section class="card">
        <h2>Cara membayar</h2>
        <ol>
            <li>Cek tagihan di bawah lalu tekan <strong>Bayar</strong>, atau buka link tagihan yang kami kirim lewat WhatsApp. Bayar dengan QRIS dari aplikasi bank atau e-wallet apa pun.</li>
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
                    <a class="btn btn-primary btn-small" href="{{ \App\Support\InvoicePaymentLink::for($invoice) }}">Bayar</a>
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
@endsection
