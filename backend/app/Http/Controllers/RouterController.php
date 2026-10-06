<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Routers\CreateRouter;
use App\Actions\Routers\DeleteRouter;
use App\Actions\Routers\TestRouterConnection;
use App\Actions\Routers\UpdateRouter;
use App\Http\Requests\Routers\StoreRouterRequest;
use App\Http\Requests\Routers\UpdateRouterRequest;
use App\Http\Resources\RouterResource;
use App\Models\Router;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tambah dan ubah router memakai modal di halaman daftar. Password tidak pernah dikirim balik.
 */
class RouterController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Router::class);

        return Inertia::render('routers/index', [
            'routers' => RouterResource::collection(Router::query()->withCount('customers')->orderBy('name')->paginate(20)),
        ]);
    }

    public function store(StoreRouterRequest $request, CreateRouter $createRouter): RedirectResponse
    {
        $router = $createRouter->handle($request->routerData(), $request->user());
        $this->toast("Router {$router->name} ditambahkan.");

        return to_route('routers.index');
    }

    public function update(UpdateRouterRequest $request, Router $router, UpdateRouter $updateRouter): RedirectResponse
    {
        $updateRouter->handle($router, $request->routerData(), $request->user());
        $this->toast("Router {$router->name} diperbarui.");

        return to_route('routers.index');
    }

    public function destroy(Request $request, Router $router, DeleteRouter $deleteRouter): RedirectResponse
    {
        Gate::authorize('delete', $router);
        $deleteRouter->handle($router, $request->user());
        $this->toast("Router {$router->name} dihapus.");

        return to_route('routers.index');
    }

    public function testConnection(Request $request, Router $router, TestRouterConnection $testConnection): RedirectResponse
    {
        Gate::authorize('testConnection', $router);

        $testConnection->handle($router, $request->user())
            ? $this->toast("Router {$router->name} terhubung.")
            : $this->toast("Router {$router->name} tidak dapat dihubungi. Periksa host, port, dan akun API.", 'error');

        return back();
    }
}
