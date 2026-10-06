<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Contracts\MessageSender;
use App\Data\MessageResult;
use App\Exceptions\MessageSendException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * WhatsApp lewat Fonnte (tidak resmi), dicocokkan dengan dokumentasi resmi pada 2026-10-06:
 * `POST /send` dengan header `Authorization: {token}` (tanpa "Bearer"), form `target` + `message`.
 *
 * Penolakan Fonnte selalu `status: false` + `reason` (token salah, nomor tidak valid, kuota habis,
 * perangkat terputus) dan dikembalikan sebagai MessageResult gagal tanpa dicoba ulang. Hanya galat
 * jaringan, HTTP 429/5xx, dan respons yang tidak terbaca yang dilempar sebagai MessageSendException
 * agar job mencoba ulang.
 */
final class FonnteMessageSender implements MessageSender
{
    public function send(string $phone, string $message): MessageResult
    {
        $token = config('services.fonnte.token');

        if (! is_string($token) || $token === '') {
            return MessageResult::failed('Token Fonnte belum diatur (FONNTE_TOKEN).');
        }

        // Tanpa retry di HTTP client: timeout bisa terjadi setelah Fonnte menerima pesan, dan
        // mengirim ulang langsung berarti pesan ganda. Pengulangan diserahkan ke queue.
        try {
            $response = Http::baseUrl((string) config('services.fonnte.base_url'))
                ->withHeaders(['Authorization' => $token])
                ->acceptJson()
                ->asForm()
                ->connectTimeout((int) config('services.fonnte.connect_timeout'))
                ->timeout((int) config('services.fonnte.timeout'))
                ->post('/send', ['target' => $phone, 'message' => $message]);
        } catch (ConnectionException $exception) {
            throw new MessageSendException("Tidak bisa terhubung ke Fonnte ({$exception->getMessage()}).", previous: $exception);
        }

        if ($response->status() === 429 || $response->serverError()) {
            throw new MessageSendException("Fonnte sedang tidak tersedia (HTTP {$response->status()}).");
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new MessageSendException("Respons Fonnte bukan JSON (HTTP {$response->status()}).");
        }

        // Contoh galat di dokumentasi kadang memakai "Status" berhuruf besar.
        $isSent = ($body['status'] ?? $body['Status'] ?? false) === true;

        if (! $isSent) {
            $reason = is_string($body['reason'] ?? null) ? $body['reason'] : 'tanpa alasan';

            return MessageResult::failed("Fonnte menolak pesan: {$reason} (HTTP {$response->status()}).");
        }

        $ids = $body['id'] ?? null;
        $providerMessageId = is_array($ids) && isset($ids[0]) && is_scalar($ids[0]) ? (string) $ids[0] : null;

        return MessageResult::sent($providerMessageId);
    }
}
