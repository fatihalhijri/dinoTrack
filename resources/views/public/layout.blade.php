{{-- Layout halaman publik pelanggan: tanpa Vite dan tanpa aset luar agar ringan di HP dan tetap tampil selama isolir. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>@yield('title') · {{ $businessName }}</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f4f5f7;color:#1f2933;line-height:1.5}
        main{max-width:480px;margin:0 auto;padding:24px 16px 40px}
        .brand{display:flex;align-items:center;gap:10px;font-size:.9rem;font-weight:600;color:#52606d;text-transform:uppercase;letter-spacing:.04em}
        .brand-logo{display:block;max-height:40px;max-width:160px;width:auto;height:auto}
        h1{font-size:1.4rem;margin:8px 0 12px}
        .card{background:#fff;border-radius:12px;padding:18px;margin-top:16px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
        .card h2{font-size:1.05rem;margin:0 0 10px}
        ol{padding-left:20px;margin:0}
        li{margin-bottom:6px}
        label{display:block;font-size:.9rem;font-weight:600;margin:12px 0 4px}
        input{width:100%;padding:11px 12px;border:1px solid #cbd2d9;border-radius:8px;font-size:1rem}
        .btn{display:block;width:100%;text-align:center;padding:12px;border:0;border-radius:8px;font-size:1rem;font-weight:600;text-decoration:none;cursor:pointer;margin-top:16px}
        .btn[disabled]{opacity:.6;cursor:wait}
        .btn-primary{background:#1f6feb;color:#fff}
        .btn-wa{background:#1a7f37;color:#fff}
        .btn-small{display:inline-block;width:auto;padding:6px 14px;font-size:.9rem;margin-top:6px}
        .notice{padding:12px;border-radius:8px;font-size:.95rem}
        .notice-error{background:#fdecea;color:#8a1c1c}
        .notice-ok{background:#e6f4ea;color:#1a5e2a}
        .notice-info{background:#e8f0fe;color:#1d3f8a}
        .invoice{border-top:1px solid #e4e7eb;padding:10px 0}
        .invoice:first-of-type{border-top:0}
        .row{display:flex;justify-content:space-between;gap:12px}
        .muted{color:#616e7c;font-size:.88rem}
        .total{font-weight:700}
        .badge{display:inline-block;padding:2px 10px;border-radius:999px;font-size:.85rem;font-weight:600;background:#e4e7eb}
        .badge-paid{background:#e6f4ea;color:#1a5e2a}
        .badge-overdue{background:#fdecea;color:#8a1c1c}
        .qr{text-align:center}
        .qr img{width:100%;max-width:300px;height:auto;border:1px solid #e4e7eb;border-radius:8px;background:#fff}
        [hidden]{display:none!important}
    </style>
</head>
<body>
<main>
    <div class="brand">
        @if (! empty($businessLogoUrl))
            <img class="brand-logo" src="{{ $businessLogoUrl }}" alt="Logo {{ $businessName }}">
        @endif
        <span>{{ $businessName }}</span>
    </div>
    @yield('content')
</main>
@stack('scripts')
</body>
</html>
