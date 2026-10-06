<?php

declare(strict_types=1);

use App\Enums\MessageStatus;
use App\Exceptions\MessageSendException;
use App\Jobs\SendWhatsAppMessage;
use App\Models\ActivityLog;
use App\Models\MessageLog;

function queuedMessage(): MessageLog
{
    return MessageLog::factory()->create([
        'phone' => '6281234567890',
        'body' => 'Halo Budi',
        'status' => MessageStatus::Queued,
        'provider_message_id' => null,
        'sent_at' => null,
    ]);
}

it('menandai pesan terkirim beserta ID provider dan mencatat activity log', function () {
    $this->freezeSecond();
    $messages = fakeMessages();
    $messageLog = queuedMessage();

    SendWhatsAppMessage::dispatch($messageLog);

    $messageLog->refresh();
    expect($messages->sentMessages())->toBe([['phone' => '6281234567890', 'message' => 'Halo Budi']])
        ->and($messageLog->status)->toBe(MessageStatus::Sent)
        ->and($messageLog->provider_message_id)->toBe('fake-message-1')
        ->and($messageLog->sent_at?->equalTo(now()))->toBeTrue()
        ->and(ActivityLog::query()->where('action', 'message.sent')->whereMorphedTo('subject', $messageLog)->exists())->toBeTrue();
});

it('mencatat penolakan provider sebagai gagal tanpa mencoba ulang', function () {
    $messages = fakeMessages()->rejectWith('Fonnte menolak pesan: target invalid (HTTP 200).');
    $messageLog = queuedMessage();

    SendWhatsAppMessage::dispatch($messageLog);

    $messageLog->refresh();
    $messages->assertCalled('send', 1);
    expect($messageLog->status)->toBe(MessageStatus::Failed)
        ->and($messageLog->error)->toBe('Fonnte menolak pesan: target invalid (HTTP 200).')
        ->and(ActivityLog::query()->where('action', 'message.failed')->whereMorphedTo('subject', $messageLog)->value('properties'))
        ->toMatchArray(['error' => 'Fonnte menolak pesan: target invalid (HTTP 200).']);
});

it('membiarkan pesan tetap queued saat provider tidak tersedia agar dicoba ulang queue', function () {
    fakeMessages()->failWith(new MessageSendException('Fonnte sedang tidak tersedia (HTTP 502).'));
    $messageLog = queuedMessage();
    $job = new SendWhatsAppMessage($messageLog);

    expect(fn () => app()->call([$job, 'handle']))->toThrow(MessageSendException::class);

    expect($messageLog->refresh()->status)->toBe(MessageStatus::Queued);
});

it('mencatat galat terakhir sebagai gagal setelah semua percobaan habis', function () {
    fakeMessages()->failWith(new MessageSendException('Fonnte sedang tidak tersedia (HTTP 502).'));
    $messageLog = queuedMessage();

    expect(fn () => SendWhatsAppMessage::dispatch($messageLog))->toThrow(MessageSendException::class);

    $messageLog->refresh();
    expect($messageLog->status)->toBe(MessageStatus::Failed)
        ->and($messageLog->error)->toBe('Fonnte sedang tidak tersedia (HTTP 502).');
});

it('tidak mengirim ulang pesan yang sudah terkirim atau gagal', function (MessageStatus $status) {
    $messages = fakeMessages();
    $messageLog = queuedMessage();
    $messageLog->update(['status' => $status]);

    SendWhatsAppMessage::dispatch($messageLog);

    $messages->assertNotCalled('send');
    expect($messageLog->refresh()->status)->toBe($status);
})->with([MessageStatus::Sent, MessageStatus::Failed]);

it('menahan pesan berikutnya sampai jeda antarpesan berlalu', function () {
    config(['services.whatsapp.seconds_per_message' => 5]);
    $this->freezeTime();
    $messages = fakeMessages();
    $first = queuedMessage();
    $second = queuedMessage();

    SendWhatsAppMessage::dispatch($first);
    SendWhatsAppMessage::dispatch($second);

    $messages->assertCalled('send', 1);
    expect($second->refresh()->status)->toBe(MessageStatus::Queued);

    $this->travel(5)->seconds();
    SendWhatsAppMessage::dispatch($second);

    $messages->assertCalled('send', 2);
    expect($second->refresh()->status)->toBe(MessageStatus::Sent);
});
