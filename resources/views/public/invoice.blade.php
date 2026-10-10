@php
    /** @var string $businessName */
    /** @var string|null $businessWhatsapp */
    /** @var \App\Models\Invoice $invoice */
    /** @var bool $isPayable */
    /** @var array{status: string, qr_url: string|null, expires_at: string|null}|null $charge */
    use App\Enums\InvoiceStatus;
    use App\Support\Money;

    $badgeClass = match ($invoice->status) {
        InvoiceStatus::Paid => 'badge-paid',
        InvoiceStatus::Overdue => 'badge-overdue',
        default => '',
    };
@endphp
@extends('public.layout')

@section('title', 'Tagihan '.$invoice->number)

@section('content')
    <h1>Tagihan internet</h1>

    <section class="card">
        <div class="row">
            <strong>{{ $invoice->number }}</strong>
            <span class="badge {{ $badgeClass }}">{{ $invoice->status->label() }}</span>
        </div>
        <p class="muted">Pelanggan: <strong>{{ $invoice->customer->name }}</strong> ({{ $invoice->customer->code }})</p>

        @foreach ($invoice->items as $item)
            <div class="invoice">
                <div class="row"><span>{{ $item->description }}</span><span>{{ Money::format($item->amount) }}</span></div>
            </div>
        @endforeach

        <div class="invoice">
            <div class="row"><span class="total">Total</span><span class="total">{{ Money::format($invoice->total) }}</span></div>
            <div class="muted">Periode {{ $invoice->period_start->translatedFormat('j M Y') }} – {{ $invoice->period_end->translatedFormat('j M Y') }}</div>
            <div class="muted">Jatuh tempo {{ $invoice->due_at->translatedFormat('j F Y') }}</div>
        </div>
    </section>

    @if ($invoice->status === InvoiceStatus::Paid)
        <p class="notice notice-ok">Tagihan sudah lunas{{ $invoice->paid_at ? ' pada '.$invoice->paid_at->translatedFormat('j F Y H:i') : '' }}. Terima kasih.</p>
    @elseif ($invoice->status === InvoiceStatus::Cancelled)
        <p class="notice notice-info">Tagihan ini sudah dibatalkan dan tidak perlu dibayar. Hubungi admin jika ada pertanyaan.</p>
    @else
        <section class="card" id="payment">
            <h2>Bayar dengan QRIS</h2>
            <p class="muted">Pindai QR dengan aplikasi bank atau e-wallet apa pun. Halaman ini diperbarui otomatis setelah pembayaran diterima.</p>

            <p class="notice notice-error" id="pay-error" hidden></p>

            <div class="qr" id="qr" hidden>
                <img id="qr-image" alt="Kode QRIS tagihan {{ $invoice->number }}">
                <p class="muted" id="qr-expiry"></p>
            </div>

            <p class="notice notice-info" id="qr-expired" hidden>Kode QR sudah kedaluwarsa. Buat kode baru untuk membayar.</p>

            <button type="button" class="btn btn-primary" id="pay-button">Tampilkan kode QRIS</button>
        </section>
    @endif

    @if ($businessWhatsapp !== null)
        <a class="btn btn-wa" href="https://wa.me/{{ $businessWhatsapp }}?text={{ rawurlencode('Halo admin '.$businessName.', saya ingin menanyakan tagihan '.$invoice->number.'.') }}" rel="noopener">Hubungi admin via WhatsApp</a>
    @endif
@endsection

@if ($isPayable)
    @push('scripts')
        <script>
            (function () {
                var payUrl = @json($payUrl);
                var statusUrl = @json($statusUrl);
                var charge = @json($charge);
                var pollTimer = null;
                var countdownTimer = null;

                var el = function (id) { return document.getElementById(id); };
                var button = el('pay-button');

                function showError(message) {
                    el('pay-error').textContent = message;
                    el('pay-error').hidden = false;
                }

                function stopTimers() {
                    clearInterval(pollTimer);
                    clearInterval(countdownTimer);
                }

                function showExpired() {
                    stopTimers();
                    el('qr').hidden = true;
                    el('qr-expired').hidden = false;
                    button.hidden = false;
                    button.disabled = false;
                    button.textContent = 'Buat kode QRIS baru';
                }

                function updateCountdown(expiresAt) {
                    var seconds = Math.floor((expiresAt - Date.now()) / 1000);
                    if (seconds <= 0) {
                        showExpired();
                        return;
                    }
                    var minutes = Math.floor(seconds / 60);
                    var rest = seconds % 60;
                    el('qr-expiry').textContent = 'Berlaku ' + minutes + ':' + (rest < 10 ? '0' : '') + rest + ' lagi';
                }

                function showCharge(data) {
                    if (!data || data.status !== 'pending' || !data.qr_url || !data.expires_at) {
                        showError('Kode QR belum tersedia. Silakan coba lagi.');
                        button.disabled = false;
                        button.textContent = 'Coba lagi';
                        return;
                    }
                    var expiresAt = Date.parse(data.expires_at);
                    el('qr-image').src = data.qr_url;
                    el('qr').hidden = false;
                    el('qr-expired').hidden = true;
                    el('pay-error').hidden = true;
                    button.hidden = true;
                    stopTimers();
                    updateCountdown(expiresAt);
                    countdownTimer = setInterval(function () { updateCountdown(expiresAt); }, 1000);
                    pollTimer = setInterval(poll, 5000);
                }

                function poll() {
                    fetch(statusUrl, { headers: { Accept: 'application/json' } })
                        .then(function (response) { return response.ok ? response.json() : null; })
                        .then(function (data) {
                            if (!data) {
                                return;
                            }
                            if (data.is_paid || data.status === 'cancelled') {
                                stopTimers();
                                window.location.reload();
                            } else if (data.charge && data.charge.status !== 'pending') {
                                showExpired();
                            }
                        })
                        .catch(function () {});
                }

                button.addEventListener('click', function () {
                    button.disabled = true;
                    button.textContent = 'Memuat kode QRIS…';
                    fetch(payUrl, { method: 'POST', headers: { Accept: 'application/json' } })
                        .then(function (response) {
                            return response.json().catch(function () { return {}; }).then(function (data) {
                                return { status: response.status, data: data };
                            });
                        })
                        .then(function (result) {
                            if (result.status === 200) {
                                showCharge(result.data.charge);
                                return;
                            }
                            showError(result.data.message || 'Terjadi kesalahan. Silakan coba lagi.');
                            if (result.status === 422) {
                                setTimeout(function () { window.location.reload(); }, 2000);
                                return;
                            }
                            button.disabled = false;
                            button.textContent = 'Coba lagi';
                        })
                        .catch(function () {
                            showError('Tidak bisa terhubung. Periksa koneksi lalu coba lagi.');
                            button.disabled = false;
                            button.textContent = 'Coba lagi';
                        });
                });

                if (charge) {
                    showCharge(charge);
                }
            })();
        </script>
    @endpush
@endif
