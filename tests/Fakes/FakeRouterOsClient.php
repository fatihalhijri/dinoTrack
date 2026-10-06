<?php

declare(strict_types=1);

namespace Tests\Fakes;

use LogicException;
use RouterOS\Interfaces\ClientInterface;
use RouterOS\Query;
use RouterOS\ResponseIterator;
use Throwable;

/**
 * Client RouterOS palsu: mencatat kata-kata perintah yang dikirim dan membalas dengan
 * balasan mentah yang diskenariokan per endpoint (format `read(false)` library).
 */
final class FakeRouterOsClient implements ClientInterface
{
    /** @var list<list<string>> */
    public array $sent = [];

    /** @var array<string, list<list<string>>> */
    private array $replies = [];

    /** @var array<string, Throwable> */
    private array $failures = [];

    private ?string $lastEndpoint = null;

    /**
     * Balasan berikutnya untuk $endpoint; dipakai berurutan, lalu `!done` kosong.
     *
     * @param  list<string>  $lines
     */
    public function reply(string $endpoint, array $lines): self
    {
        $this->replies[$endpoint][] = $lines;

        return $this;
    }

    /**
     * Daftar item `!re` dengan atribut masing-masing.
     *
     * @param  list<array<string, string>>  $items
     */
    public function replyItems(string $endpoint, array $items): self
    {
        $lines = [];

        foreach ($items as $item) {
            $lines[] = '!re';

            foreach ($item as $key => $value) {
                $lines[] = "={$key}={$value}";
            }
        }

        return $this->reply($endpoint, [...$lines, '!done']);
    }

    public function failOn(string $endpoint, Throwable $exception): self
    {
        $this->failures[$endpoint] = $exception;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function endpoints(): array
    {
        return array_map(fn (array $words): string => $words[0], $this->sent);
    }

    /**
     * @return list<list<string>>
     */
    public function sentTo(string $endpoint): array
    {
        return array_values(array_filter($this->sent, fn (array $words): bool => $words[0] === $endpoint));
    }

    public function getSocket()
    {
        return null;
    }

    public function query($endpoint, ?array $where = null, ?string $operations = null, ?string $tag = null): ClientInterface
    {
        $query = $endpoint instanceof Query ? $endpoint : new Query($endpoint);
        /** @var list<string> $words */
        $words = $query->getQuery();
        $this->sent[] = $words;
        $this->lastEndpoint = $words[0];

        if (isset($this->failures[$words[0]])) {
            throw $this->failures[$words[0]];
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<string>
     */
    public function read(bool $parse = true, array $options = []): array
    {
        $endpoint = $this->lastEndpoint ?? throw new LogicException('read() tanpa query().');
        $this->replies[$endpoint] ??= [];

        return array_shift($this->replies[$endpoint]) ?? ['!done'];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function readAsIterator(array $options = []): ResponseIterator
    {
        throw new LogicException('Tidak dipakai.');
    }

    public function export(?string $arguments = null): string
    {
        throw new LogicException('Tidak dipakai.');
    }
}
