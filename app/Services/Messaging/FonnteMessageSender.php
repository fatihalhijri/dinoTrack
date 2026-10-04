<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Contracts\MessageSender;
use App\Data\MessageResult;
use App\Exceptions\NotImplementedException;

final class FonnteMessageSender implements MessageSender
{
    public function send(string $phone, string $message): MessageResult
    {
        throw NotImplementedException::for(self::class, __FUNCTION__);
    }
}
