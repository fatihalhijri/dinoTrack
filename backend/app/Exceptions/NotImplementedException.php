<?php

declare(strict_types=1);

namespace App\Exceptions;

use LogicException;

final class NotImplementedException extends LogicException
{
    public static function for(string $class, string $method): self
    {
        return new self("{$class}::{$method}() belum diimplementasikan.");
    }
}
