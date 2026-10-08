<?php

declare(strict_types=1);

namespace App\Services\Network;

use App\Models\Router;
use RouterOS\Client;
use RouterOS\Exceptions\ClientException;
use RouterOS\Exceptions\ConfigException;
use RouterOS\Exceptions\StreamException;
use RouterOS\Interfaces\ClientInterface;

/**
 * Membuka koneksi RouterOS API ke satu router. Dipisah dari MikrotikNetworkController agar
 * controller bisa diuji dengan client palsu tanpa router sungguhan.
 */
class RouterOsClientFactory
{
    /**
     * @throws ClientException gagal terhubung atau login ditolak
     * @throws ConfigException
     * @throws StreamException login tidak dibalas (socket timeout saat membaca)
     */
    public function make(Router $router): ClientInterface
    {
        return new Client([
            'host' => $router->host,
            'port' => $router->port,
            'user' => $router->username,
            'pass' => $router->password,
            'ssl' => $router->use_ssl,
            'timeout' => (int) config('services.mikrotik.connect_timeout'),
            'socket_timeout' => (int) config('services.mikrotik.socket_timeout'),
            // Bawaan library 10 percobaan dengan jeda 1 detik: router mati akan menahan worker
            // lebih dari semenit. Pengulangan diserahkan ke queue.
            'attempts' => 1,
            'delay' => 0,
        ]);
    }
}
