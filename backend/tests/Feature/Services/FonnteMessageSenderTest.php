<?php

declare(strict_types=1);

use App\Exceptions\MessageSendException;
use App\Services\Messaging\FonnteMessageSender;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const FONNTE_SEND_URL = 'https://api.fonnte.com/send';

beforeEach(function () {
    config(['services.fonnte.token' => 'fonnte-token-rahasia']);
    Http::preventStrayRequests();
});

it('mengirim target dan pesan dengan token di header Authorization tanpa Bearer', function () {
    Http::fake([FONNTE_SEND_URL => Http::response([
        'detail' => 'success! message in queue',
        'id' => ['80367170'],
        'process' => 'pending',
        'requestid' => 2937124,
        'status' => true,
        'target' => ['6281234567890'],
    ])]);

    $result = app(FonnteMessageSender::class)->send('6281234567890', 'Halo Budi');

    expect($result->success)->toBeTrue()
        ->and($result->providerMessageId)->toBe('80367170');
    Http::assertSent(fn (Request $request): bool => $request->url() === FONNTE_SEND_URL
        && $request->method() === 'POST'
        && $request->header('Authorization') === ['fonnte-token-rahasia']
        && $request['target'] === '6281234567890'
        && $request['message'] === 'Halo Budi');
});

it('mengembalikan hasil gagal tanpa dicoba ulang jika Fonnte menolak pesan', function (array $body) {
    Http::fake([FONNTE_SEND_URL => Http::response($body)]);

    $result = app(FonnteMessageSender::class)->send('6281234567890', 'Halo');

    expect($result->success)->toBeFalse()
        ->and($result->error)->toBe('Fonnte menolak pesan: target invalid (HTTP 200).');
})->with([
    'status huruf kecil' => [['reason' => 'target invalid', 'status' => false, 'requestid' => 1]],
    // Contoh "token invalid" di dokumentasi memakai "Status" berhuruf besar.
    'Status huruf besar' => [['Status' => false, 'reason' => 'target invalid', 'requestid' => 1]],
]);

it('melempar MessageSendException agar job mencoba ulang saat Fonnte tidak tersedia', function (Closure $response, string $message) {
    Http::fake([FONNTE_SEND_URL => $response()]);

    expect(fn () => app(FonnteMessageSender::class)->send('6281234567890', 'Halo'))
        ->toThrow(MessageSendException::class, $message);
})->with([
    'gagal koneksi' => [fn () => Http::failedConnection('cURL error 28: timeout'), 'Tidak bisa terhubung ke Fonnte'],
    'HTTP 502' => [fn () => Http::response('Bad Gateway', 502), 'Fonnte sedang tidak tersedia (HTTP 502)'],
    'HTTP 429' => [fn () => Http::response(['status' => false], 429), 'Fonnte sedang tidak tersedia (HTTP 429)'],
    'bukan JSON' => [fn () => Http::response('<html>maintenance</html>', 200), 'Respons Fonnte bukan JSON'],
]);

it('tidak mengirim apa pun jika token belum diatur', function () {
    config(['services.fonnte.token' => null]);
    Http::fake();

    $result = app(FonnteMessageSender::class)->send('6281234567890', 'Halo');

    expect($result->success)->toBeFalse()
        ->and($result->error)->toBe('Token Fonnte belum diatur (FONNTE_TOKEN).');
    Http::assertNothingSent();
});

it('tidak menyertakan token di pesan galat', function () {
    Http::fake([FONNTE_SEND_URL => Http::response(['reason' => 'token invalid', 'status' => false])]);

    $result = app(FonnteMessageSender::class)->send('6281234567890', 'Halo');

    expect($result->error)->not->toContain('fonnte-token-rahasia');
});
