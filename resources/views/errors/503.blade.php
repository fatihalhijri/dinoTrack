{{-- Mode maintenance (deploy): tanpa Vite dan tanpa database karena aset dan migration sedang diperbarui. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Sedang Pemeliharaan · {{ config('app.name') }}</title>
    <style>
        body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f4f5f7;color:#1f2933;line-height:1.5}
        main{max-width:480px;margin:0 auto;padding:64px 16px}
        .brand{font-size:.9rem;font-weight:600;color:#52606d;text-transform:uppercase;letter-spacing:.04em}
        h1{font-size:1.4rem;margin:8px 0 12px}
        @media (prefers-color-scheme: dark){body{background:#0b1220;color:#e4e7eb}.brand{color:#9aa5b1}}
    </style>
</head>
<body>
<main>
    <div class="brand">{{ config('app.name') }}</div>
    <h1>Sedang pemeliharaan</h1>
    <p>Kami sedang memperbarui sistem. Silakan coba lagi dalam beberapa menit; halaman ini akan dimuat ulang otomatis.</p>
</main>
</body>
</html>
