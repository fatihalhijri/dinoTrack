# Billing Internet ISP — Panduan Proyek untuk Claude

Aplikasi admin billing untuk ISP / RT-RW Net: kelola pelanggan dan paket,
tagihan bulanan otomatis, pembayaran QRIS, isolir dan aktivasi otomatis di
Mikrotik, serta notifikasi WhatsApp.

## Stack

- Laravel 13, PHP 8.4, MySQL 8
- Inertia.js + React + TypeScript (starter kit resmi Laravel)
- Queue: Redis (production), `database` (development)
- Test: Pest. Format: Laravel Pint. Analisis statis: Larastan
- Zona waktu aplikasi: `Asia/Jakarta`. Locale: `id`

## Dokumen acuan (sumber kebenaran)

Baca dokumen yang relevan sebelum mengerjakan fitur. Jika kode dan dokumen
bertentangan, **berhenti dan tanyakan**, jangan menebak.

- @docs/01-spesifikasi-produk.md — fitur dan role
- @docs/02-arsitektur.md — struktur folder dan pola kode
- @docs/03-database.md — skema tabel
- @docs/04-aturan-bisnis.md — aturan tagihan, isolir, denda
- @docs/05-integrasi.md — Midtrans QRIS, Mikrotik, WhatsApp
- @docs/06-standar-kode.md — konvensi penulisan kode
- @docs/07-definition-of-done.md — syarat fitur dianggap selesai
- @docs/08-kontrak-halaman.md — kontrak props halaman Inertia (acuan utama fase frontend)
- `docs/09-audit-keamanan.md` — hasil audit keamanan dan sisa risiko
- `docs/10-deploy.md` — panduan deploy production

Kemajuan proyek dicatat di `PROGRESS.md`.

## Fase saat ini: FRONTEND

Backend selesai (Tahap 00–11). Fase ini membangun halaman React di
`resources/js` sesuai `docs/08-kontrak-halaman.md`. Tahap ada di
`prompts/F00-*.md` … `prompts/F09-*.md`.

- Identitas: nama **dinoTrack**, warna utama biru tua laut (token `--primary`
  di `resources/css/app.css`; jangan memakai kode warna langsung di komponen).
- Pengguna: admin dan kasir di laptop, teknisi lebih sering di HP. Halaman
  pelanggan wajib nyaman dipakai di HP.
- Perubahan backend hanya jika kontrak docs/08 kurang atau salah. Ikuti aturan
  backend di bawah, tulis test, dan perbarui docs/08 di commit yang sama.

### Aturan frontend

1. **Komponen**: pakai komponen shadcn/ui yang sudah ada di
   `resources/js/components/ui`. Komponen baru ditambah lewat CLI
   (`npx shadcn@latest add <nama>`), tidak ditulis tangan. File di
   `components/ui` tidak diubah kecuali untuk token tema. Komponen bersama
   aplikasi ada di `resources/js/components/` (lihat F00); cek dulu sebelum
   membuat yang baru.
2. **Form**: memakai `useForm` Inertia (atau `<Form>`), dengan URL dari
   Wayfinder (`@/actions`, `@/routes`) dan tanpa URL hardcode. Error validasi
   Laravel tampil di bawah field (`InputError`). Error aturan bisnis dengan
   key non-field (`status`, `customer`, `package`, `router`, `invoice`,
   `user`, `role`) tampil sebagai pesan umum (`FormErrorAlert`). Tombol submit
   dinonaktifkan selama `processing`.
3. **TypeScript ketat**: tanpa `any`, tanpa `as unknown as`, dan tanpa
   `@ts-ignore`. Tipe props setiap halaman mengikuti docs/08 dan memakai tipe
   bersama di `resources/js/types/models.ts`. `npm run types:check` wajib bersih.
4. **Bahasa & format**: semua teks UI dalam Bahasa Indonesia. Uang selalu
   lewat `formatRupiah()` (`Rp150.000`) dan tanggal lewat
   `formatDate()`/`formatDateTime()` (`6 Okt 2026`, `6 Okt 2026 14.30`, zona
   Asia/Jakarta) dari `@/lib/format`. Jangan memformat di tempat lain. Label
   enum memakai `*_label` dari backend.
5. **Responsif**: setiap halaman layak dipakai di lebar 360 px. Tabel berubah
   menjadi daftar kartu di bawah `md`, tanpa scroll horizontal halaman, dengan
   target sentuh minimal 40 px dan input numerik memakai `inputMode`.
6. **Permission**: tombol, menu, dan tab disembunyikan dengan `useCan()`
   (membaca `auth.permissions`) serta syarat status data (misalnya "Tandai
   terpasang" hanya untuk `pending`). Otorisasi tetap di backend.
7. **Test**: setiap halaman punya feature test Pest dengan `assertInertia()`
   memakai `->component('<nama>', true)` (memastikan file halaman ada) dan
   memeriksa props per role. Perluas test di `tests/Feature/Http` yang sudah
   ada. Jangan membuat duplikat.
8. **Data**: halaman tidak menghitung aturan bisnis (prorata, toleransi, dan
   sejenisnya); semuanya datang dari props. Deferred prop memakai skeleton.
9. Tanpa package npm baru kecuali disebut di prompt tahap atau dijelaskan
   alasannya terlebih dahulu.

## Aturan wajib

1. Logika bisnis di `app/Actions` (satu aksi = satu kelas) dan `app/Services`
   (integrasi eksternal). Controller tetap tipis.
2. Semua integrasi eksternal (payment, Mikrotik, WhatsApp) memakai **interface**
   di `app/Contracts` dan di-bind di service provider, agar bisa di-fake di test.
   **Test tidak boleh memanggil layanan eksternal sungguhan.**
3. Proses lambat atau yang bisa gagal (Mikrotik, WhatsApp) wajib lewat **Job**
   di queue, dengan `$tries`, `$backoff`, dan `failed()`.
4. Uang disimpan sebagai **integer rupiah** (`unsignedBigInteger`), tidak
   pernah float/decimal.
5. Perubahan data yang saling bergantung dibungkus `DB::transaction()`.
6. Webhook wajib: verifikasi signature, idempotent, cepat merespons, proses
   berat di-dispatch ke queue.
7. Validasi input lewat Form Request. Otorisasi lewat Policy dan permission
   (spatie/laravel-permission).
8. Kredensial hanya lewat `.env` → `config/services.php`. Jangan pernah
   hardcode. Boleh membaca dan mengubah `.env` untuk development, tetapi
   selalu tunjukkan perubahannya dulu, jangan pernah menampilkan nilai
   rahasia (key, token, password) di jawaban, dan selalu perbarui
   `.env.example` dengan nama variabel tanpa nilai.
9. Gunakan Enum PHP untuk status (`CustomerStatus`, `InvoiceStatus`, dll).
10. Setiap aksi penting (isolir, aktivasi, pembayaran, kirim WA) dicatat di
    activity log.

## Perintah

```bash
php artisan test                      # semua test
php artisan test --filter=NamaTest    # satu test
./vendor/bin/pint                     # format kode
./vendor/bin/phpstan analyse          # analisis statis
php artisan migrate:fresh --seed      # reset database development
php artisan schedule:list             # cek jadwal
php artisan queue:work                # jalankan worker
npm run dev                           # Vite dev server
npm run types:check                   # cek TypeScript
npm run check                         # lint + format frontend (vp check)
npm run build                         # build produksi
php artisan wayfinder:generate        # regenerasi helper route TS
```

Lingkungan pengembang: Windows + Git Bash. Gunakan perintah yang kompatibel.

## Cara bekerja

- Untuk tugas lebih dari satu file: **susun rencana dulu**, tunggu persetujuan.
- Tulis atau perbarui test bersamaan dengan kode, bukan setelahnya.
- Sebelum menyatakan selesai: jalankan Pint, PHPStan, seluruh test,
  `npm run types:check`, `npm run check`, dan `npm run build`.
  Laporkan hasilnya apa adanya, termasuk jika ada yang gagal.
- Jangan menambah package baru tanpa menyebutkan alasannya.
- Jangan mengubah migration yang sudah di-commit; buat migration baru.
- Jika ragu tentang aturan bisnis, tanyakan. Jangan mengarang aturan.
- Pesan commit: Conventional Commits dalam Bahasa Indonesia
  (contoh: `feat(invoice): generate tagihan bulanan otomatis`).

## Pedoman Laravel Boost

Aturan tambahan dari Laravel Boost (versi paket, konvensi Laravel, tools MCP,
skill) ada di file berikut dan wajib diikuti. Jika bertentangan dengan aturan
proyek di atas, aturan proyek yang menang.

@AGENTS.md

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>
