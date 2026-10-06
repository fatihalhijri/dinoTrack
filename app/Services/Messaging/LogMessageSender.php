<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Contracts\MessageSender;
use App\Data\MessageResult;
use Illuminate\Support\Facades\Log;

/**
 * Driver `log` untuk development: pesan hanya ditulis ke log aplikasi, tidak dikirim ke WhatsApp.
 */
final class LogMessageSender implements MessageSender
{
    public function send(string $phone, string $message): MessageResult
    {
        Log::info('Pesan WhatsApp (driver log, tidak dikirim).', ['phone' => $phone, 'message' => $message]);

        return MessageResult::sent('log-'.bin2hex(random_bytes(6)));
    }
}
