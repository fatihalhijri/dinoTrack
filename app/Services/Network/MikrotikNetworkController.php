<?php

declare(strict_types=1);

namespace App\Services\Network;

use App\Contracts\NetworkController;
use App\Exceptions\RouterCommandException;
use App\Exceptions\RouterUnreachableException;
use App\Exceptions\SecretNotFoundException;
use App\Models\Customer;
use App\Models\Router;
use Closure;
use RouterOS\Exceptions\ClientException;
use RouterOS\Exceptions\ConfigException;
use RouterOS\Exceptions\StreamException;
use RouterOS\Interfaces\ClientInterface;
use RouterOS\Interfaces\QueryInterface;
use RouterOS\Query;
use Throwable;

/**
 * Mikrotik RouterOS API lewat evilfreelancer/routeros-api-php. Setiap pemanggilan membuka
 * satu koneksi dan menutupnya lagi (`/quit`), karena dipanggil dari job yang jarang dan
 * router yang berbeda-beda.
 *
 * Setelah profil secret diganti, sesi aktifnya diputus agar profil baru langsung berlaku
 * saat modem tersambung ulang.
 */
final class MikrotikNetworkController implements NetworkController
{
    public function __construct(
        private readonly RouterOsClientFactory $clients,
    ) {}

    public function testConnection(Router $router): bool
    {
        $this->withClient($router, fn (ClientInterface $client): array => $this->run($client, new Query('/system/identity/print')));

        return true;
    }

    public function isolate(Customer $customer): void
    {
        $router = $customer->router()->firstOrFail();

        $this->updateSecret($router, $customer, ['profile' => $router->isolation_profile]);
    }

    /**
     * Secret ikut di-enable karena aktivasi pelanggan baru atau yang diaktifkan kembali
     * memakai secret yang mungkin sedang dinonaktifkan.
     */
    public function activate(Customer $customer, string $profile): void
    {
        $this->updateSecret($customer->router()->firstOrFail(), $customer, ['profile' => $profile, 'disabled' => 'no']);
    }

    public function disableSecret(Customer $customer): void
    {
        $this->updateSecret($customer->router()->firstOrFail(), $customer, ['disabled' => 'yes']);
    }

    public function isOnline(Customer $customer): bool
    {
        return $this->withClient(
            $customer->router()->firstOrFail(),
            fn (ClientInterface $client): bool => $this->activeSessions($client, $customer) !== [],
        );
    }

    /**
     * @param  array<string, string>  $attributes
     */
    private function updateSecret(Router $router, Customer $customer, array $attributes): void
    {
        $this->withClient($router, function (ClientInterface $client) use ($router, $customer, $attributes): void {
            $query = (new Query('/ppp/secret/set'))->equal('.id', $this->secretId($client, $router, $customer));

            foreach ($attributes as $key => $value) {
                $query->equal($key, $value);
            }

            $this->run($client, $query);
            $this->kickSessions($client, $customer);
        });
    }

    private function secretId(ClientInterface $client, Router $router, Customer $customer): string
    {
        $secrets = $this->run($client, (new Query('/ppp/secret/print'))->where('name', $customer->pppoe_username));

        if (! isset($secrets[0]['.id'])) {
            throw new SecretNotFoundException(sprintf('Secret PPPoE "%s" tidak ditemukan di router %s.', $customer->pppoe_username, $router->name));
        }

        return $secrets[0]['.id'];
    }

    /**
     * @return list<array<string, string>>
     */
    private function activeSessions(ClientInterface $client, Customer $customer): array
    {
        return $this->run($client, (new Query('/ppp/active/print'))->where('name', $customer->pppoe_username));
    }

    private function kickSessions(ClientInterface $client, Customer $customer): void
    {
        foreach ($this->activeSessions($client, $customer) as $session) {
            try {
                $this->run($client, (new Query('/ppp/active/remove'))->equal('.id', $session['.id'] ?? ''));
            } catch (RouterCommandException) {
                // Sesi sudah terputus sendiri di antara print dan remove; tujuannya tercapai.
            }
        }
    }

    /**
     * Membuka koneksi, menjalankan $callback, lalu menutup koneksi apa pun hasilnya. Semua
     * kegagalan koneksi (termasuk login ditolak dan timeout) menjadi RouterUnreachableException
     * tanpa membawa password.
     *
     * @template TResult
     *
     * @param  Closure(ClientInterface): TResult  $callback
     * @return TResult
     */
    private function withClient(Router $router, Closure $callback): mixed
    {
        try {
            $client = $this->clients->make($router);
        } catch (ClientException|ConfigException $exception) {
            throw $this->unreachable($router, $exception);
        }

        try {
            return $callback($client);
        } catch (ClientException|StreamException $exception) {
            throw $this->unreachable($router, $exception);
        } finally {
            $this->quit($client);
        }
    }

    private function quit(ClientInterface $client): void
    {
        try {
            // Hanya dikirim tanpa menunggu balasan; router menutup sesi API-nya sendiri.
            $client->query('/quit');
        } catch (Throwable) {
            // Koneksi yang sudah putus tidak perlu ditutup lagi.
        }
    }

    private function unreachable(Router $router, Throwable $exception): RouterUnreachableException
    {
        return new RouterUnreachableException(
            sprintf('Router %s (%s:%d) tidak bisa dijangkau: %s', $router->name, $router->host, $router->port, $exception->getMessage()),
            summary: sprintf('Router tidak bisa dijangkau (%s).', $router->name),
            previous: $exception,
        );
    }

    /**
     * Menjalankan satu perintah dan mengurai balasan mentah. Balasan dibaca mentah karena
     * parser bawaan library menghilangkan penanda `!trap`, sehingga perintah yang ditolak
     * router akan terlihat berhasil.
     *
     * @return list<array<string, string>>
     */
    private function run(ClientInterface $client, QueryInterface $query): array
    {
        /** @var list<string> $lines */
        $lines = $client->query($query)->read(false);

        $items = [];
        $error = null;

        foreach ($lines as $line) {
            if ($line === '!re') {
                $items[] = [];

                continue;
            }

            if ($line === '!trap' || $line === '!fatal') {
                $error = $line;

                continue;
            }

            if (preg_match('/^=([^=]+)=(.*)$/s', $line, $matches) !== 1) {
                // Pesan !fatal dikirim sebagai kata biasa tanpa awalan "=".
                if ($error === '!fatal' && $line !== '!done' && ! str_starts_with($line, '.')) {
                    $error = $line;
                }

                continue;
            }

            if ($error !== null) {
                if ($matches[1] === 'message') {
                    $error = $matches[2];
                }

                continue;
            }

            if ($items !== []) {
                $items[array_key_last($items)][$matches[1]] = $matches[2];
            }
        }

        if ($error !== null) {
            throw new RouterCommandException(sprintf('Router menolak perintah %s: %s', $query->getEndpoint(), $error));
        }

        return $items;
    }
}
