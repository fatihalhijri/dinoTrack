<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Contracts\MessageSender;
use App\Data\MessageResult;

final class FakeMessageSender implements MessageSender
{
    use RecordsCalls;

    private ?string $rejection = null;

    /** Pesan berikutnya ditolak provider (MessageResult gagal, bukan exception). */
    public function rejectWith(string $error): static
    {
        $this->rejection = $error;

        return $this;
    }

    public function send(string $phone, string $message): MessageResult
    {
        $this->record(__FUNCTION__, [$phone, $message]);

        if ($this->rejection !== null) {
            return MessageResult::failed($this->rejection);
        }

        return MessageResult::sent('fake-message-'.count($this->calls('send')));
    }

    /**
     * @return list<array{phone: string, message: string}>
     */
    public function sentMessages(): array
    {
        return array_map(
            fn (array $call): array => ['phone' => $call['args'][0], 'message' => $call['args'][1]],
            $this->calls('send'),
        );
    }
}
