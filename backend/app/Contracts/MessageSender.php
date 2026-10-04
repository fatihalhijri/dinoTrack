<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Data\MessageResult;

interface MessageSender
{
    public function send(string $phone, string $message): MessageResult;
}
