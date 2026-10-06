<?php

declare(strict_types=1);

namespace Tests\Fakes;

use Closure;
use PHPUnit\Framework\Assert;
use Throwable;

/**
 * Perekam panggilan dan pengatur kegagalan untuk semua fake integrasi.
 */
trait RecordsCalls
{
    /** @var list<array{method: string, args: array<int, mixed>}> */
    private array $calls = [];

    private ?Throwable $failure = null;

    private int $remainingFailures = 0;

    /** @var array<string, Closure(): mixed> */
    private array $callbacks = [];

    /**
     * Jalankan $callback setiap kali $method dipanggil, sebelum kegagalan terjadwal dilempar.
     * Dipakai untuk mensimulasikan kejadian yang berlangsung selama panggilan ke layanan luar.
     *
     * @param  Closure(): mixed  $callback
     */
    public function whenCalled(string $method, Closure $callback): static
    {
        $this->callbacks[$method] = $callback;

        return $this;
    }

    /** Semua panggilan berikutnya melempar $exception. */
    public function failWith(Throwable $exception): static
    {
        $this->failure = $exception;
        $this->remainingFailures = PHP_INT_MAX;

        return $this;
    }

    /** Hanya $times panggilan berikutnya yang gagal, setelah itu normal (menguji retry job). */
    public function failTimes(int $times, Throwable $exception): static
    {
        $this->failure = $exception;
        $this->remainingFailures = $times;

        return $this;
    }

    public function stopFailing(): static
    {
        $this->failure = null;
        $this->remainingFailures = 0;

        return $this;
    }

    /**
     * @return list<array{method: string, args: array<int, mixed>}>
     */
    public function calls(?string $method = null): array
    {
        if ($method === null) {
            return $this->calls;
        }

        return array_values(array_filter(
            $this->calls,
            fn (array $call): bool => $call['method'] === $method,
        ));
    }

    public function assertCalled(string $method, ?int $times = null): void
    {
        $count = count($this->calls($method));

        $times === null
            ? Assert::assertGreaterThan(0, $count, "{$method}() tidak pernah dipanggil.")
            : Assert::assertSame($times, $count, "{$method}() dipanggil {$count}x, diharapkan {$times}x.");
    }

    public function assertNotCalled(string $method): void
    {
        Assert::assertCount(0, $this->calls($method), "{$method}() seharusnya tidak dipanggil.");
    }

    public function assertNothingCalled(): void
    {
        Assert::assertCount(0, $this->calls, 'Seharusnya tidak ada panggilan sama sekali.');
    }

    /**
     * Catat panggilan lalu lempar kegagalan terjadwal bila ada.
     *
     * @param  array<int, mixed>  $args
     */
    private function record(string $method, array $args = []): void
    {
        $this->calls[] = ['method' => $method, 'args' => $args];

        if (isset($this->callbacks[$method])) {
            ($this->callbacks[$method])();
        }

        if ($this->failure !== null && $this->remainingFailures > 0) {
            $this->remainingFailures--;

            throw $this->failure;
        }
    }
}
