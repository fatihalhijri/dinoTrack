<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\HealthStatus;

final readonly class HealthCheckResult
{
    public function __construct(
        public string $name,
        public HealthStatus $status,
        public string $message,
    ) {}

    public static function ok(string $name, string $message): self
    {
        return new self($name, HealthStatus::Ok, $message);
    }

    public static function warning(string $name, string $message): self
    {
        return new self($name, HealthStatus::Warning, $message);
    }

    public static function fail(string $name, string $message): self
    {
        return new self($name, HealthStatus::Fail, $message);
    }

    public static function skipped(string $name, string $message): self
    {
        return new self($name, HealthStatus::Skipped, $message);
    }
}
