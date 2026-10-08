<?php

declare(strict_types=1);

use App\Exceptions\RouterCommandException;
use App\Exceptions\RouterUnreachableException;
use App\Exceptions\SecretNotFoundException;
use App\Models\Customer;
use App\Models\Router;
use App\Services\Network\MikrotikNetworkController;
use App\Services\Network\RouterOsClientFactory;
use RouterOS\Exceptions\BadCredentialsException;
use RouterOS\Exceptions\StreamException;
use RouterOS\Interfaces\ClientInterface;
use Tests\Fakes\FakeRouterOsClient;

/**
 * Controller dengan factory yang selalu mengembalikan $client (atau melempar $connectError).
 */
function mikrotik(FakeRouterOsClient $client, ?Throwable $connectError = null): MikrotikNetworkController
{
    return new MikrotikNetworkController(new class($client, $connectError) extends RouterOsClientFactory
    {
        public function __construct(private ClientInterface $client, private ?Throwable $connectError) {}

        public function make(Router $router): ClientInterface
        {
            return $this->connectError !== null ? throw $this->connectError : $this->client;
        }
    });
}

function mikrotikCustomer(): Customer
{
    $router = Router::factory()->create(['name' => 'Router Pusat', 'isolation_profile' => 'ISOLIR']);

    return Customer::factory()->for($router)->create(['pppoe_username' => 'budi@net']);
}

it('mengisolir dengan profil isolir router lalu memutus semua sesi aktif', function () {
    $client = (new FakeRouterOsClient)
        ->replyItems('/ppp/secret/print', [['.id' => '*A', 'name' => 'budi@net', 'profile' => 'Home-20']])
        ->replyItems('/ppp/active/print', [['.id' => '*S1'], ['.id' => '*S2']]);

    mikrotik($client)->isolate(mikrotikCustomer());

    expect($client->sent)->toBe([
        ['/ppp/secret/print', '?name=budi@net'],
        ['/ppp/secret/set', '=.id=*A', '=profile=ISOLIR'],
        ['/ppp/active/print', '?name=budi@net'],
        ['/ppp/active/remove', '=.id=*S1'],
        ['/ppp/active/remove', '=.id=*S2'],
        ['/quit'],
    ]);
});

it('mengaktifkan dengan profil paket, meng-enable secret, lalu memutus sesi', function () {
    $client = (new FakeRouterOsClient)
        ->replyItems('/ppp/secret/print', [['.id' => '*A']])
        ->replyItems('/ppp/active/print', [['.id' => '*S1']]);

    mikrotik($client)->activate(mikrotikCustomer(), 'Home-20');

    expect($client->sentTo('/ppp/secret/set'))->toBe([['/ppp/secret/set', '=.id=*A', '=profile=Home-20', '=disabled=no']])
        ->and($client->sentTo('/ppp/active/remove'))->toBe([['/ppp/active/remove', '=.id=*S1']]);
});

it('menonaktifkan secret tanpa menghapusnya lalu memutus sesi', function () {
    $client = (new FakeRouterOsClient)->replyItems('/ppp/secret/print', [['.id' => '*A']]);

    mikrotik($client)->disableSecret(mikrotikCustomer());

    expect($client->sentTo('/ppp/secret/set'))->toBe([['/ppp/secret/set', '=.id=*A', '=disabled=yes']])
        ->and($client->endpoints())->not->toContain('/ppp/secret/remove')
        ->and($client->endpoints())->toContain('/ppp/active/print');
});

it('melaporkan pelanggan online jika punya sesi PPP aktif', function (array $sessions, bool $online) {
    $client = (new FakeRouterOsClient)->replyItems('/ppp/active/print', $sessions);

    expect(mikrotik($client)->isOnline(mikrotikCustomer()))->toBe($online);
})->with([
    'ada sesi' => [[['.id' => '*S1', 'address' => '10.10.0.2']], true],
    'tanpa sesi' => [[], false],
]);

it('menguji koneksi dengan membaca identitas router', function () {
    $client = (new FakeRouterOsClient)->replyItems('/system/identity/print', [['name' => 'MikroTik']]);

    expect(mikrotik($client)->testConnection(Router::factory()->create()))->toBeTrue()
        ->and($client->endpoints())->toBe(['/system/identity/print', '/quit']);
});

it('melempar SecretNotFoundException tanpa mengubah apa pun jika secret tidak ada', function () {
    $client = (new FakeRouterOsClient)->replyItems('/ppp/secret/print', []);

    expect(fn () => mikrotik($client)->isolate(mikrotikCustomer()))
        ->toThrow(SecretNotFoundException::class, 'Secret PPPoE "budi@net" tidak ditemukan di router Router Pusat.');

    expect($client->endpoints())->toBe(['/ppp/secret/print', '/quit']);
});

it('melempar RouterCommandException jika router menolak perintah', function () {
    $client = (new FakeRouterOsClient)
        ->replyItems('/ppp/secret/print', [['.id' => '*A']])
        ->reply('/ppp/secret/set', ['!trap', '=message=input does not match any value of profile', '!done']);

    expect(fn () => mikrotik($client)->isolate(mikrotikCustomer()))
        ->toThrow(RouterCommandException::class, 'input does not match any value of profile');

    expect($client->endpoints())->not->toContain('/ppp/active/print')
        ->and(last($client->endpoints()))->toBe('/quit');
});

it('mengabaikan sesi yang sudah terputus sendiri saat akan diputus', function () {
    $client = (new FakeRouterOsClient)
        ->replyItems('/ppp/secret/print', [['.id' => '*A']])
        ->replyItems('/ppp/active/print', [['.id' => '*S1']])
        ->reply('/ppp/active/remove', ['!trap', '=message=no such item', '!done']);

    mikrotik($client)->isolate(mikrotikCustomer());

    expect(last($client->endpoints()))->toBe('/quit');
});

it('mengubah galat koneksi di tengah perintah menjadi RouterUnreachableException dan tetap menutup koneksi', function () {
    $client = (new FakeRouterOsClient)->failOn('/ppp/secret/print', new StreamException('Stream timed out'));

    expect(fn () => mikrotik($client)->isolate(mikrotikCustomer()))
        ->toThrow(RouterUnreachableException::class, 'Stream timed out');

    expect(last($client->endpoints()))->toBe('/quit');
});

it('mengubah login API yang ditolak menjadi RouterUnreachableException', function () {
    $network = mikrotik(new FakeRouterOsClient, new BadCredentialsException('Invalid user name or password'));

    expect(fn () => $network->testConnection(Router::factory()->create(['name' => 'Router Pusat'])))
        ->toThrow(RouterUnreachableException::class, 'Router Pusat');
});

it('memberi ringkasan galat tanpa alamat router untuk data yang dilihat kasir dan teknisi', function () {
    $network = mikrotik(new FakeRouterOsClient, new BadCredentialsException('Invalid user name or password'));
    $router = Router::factory()->create(['name' => 'Router Pusat', 'host' => '10.20.30.40', 'port' => 8728]);

    try {
        $network->testConnection($router);
        $this->fail('Seharusnya melempar RouterUnreachableException.');
    } catch (RouterUnreachableException $exception) {
        expect($exception->getMessage())->toContain('10.20.30.40:8728')
            ->and($exception->summary())->toBe('Router tidak bisa dijangkau (Router Pusat).');
    }
});

it('gagal cepat dalam sekali percobaan tanpa membocorkan password saat router tidak bisa dijangkau', function () {
    config(['services.mikrotik.connect_timeout' => 2]);
    // Port 1 di localhost tertutup: koneksi langsung ditolak tanpa menyentuh jaringan luar.
    $router = Router::factory()->create(['host' => '127.0.0.1', 'port' => 1, 'password' => 'rahasia-router-123']);
    $startedAt = microtime(true);

    try {
        app(MikrotikNetworkController::class)->testConnection($router);
        $this->fail('Seharusnya melempar RouterUnreachableException.');
    } catch (RouterUnreachableException $exception) {
        expect($exception->getMessage())->toContain('127.0.0.1:1')
            ->not->toContain('rahasia-router-123');
    }

    expect(microtime(true) - $startedAt)->toBeLessThan(4);
});
