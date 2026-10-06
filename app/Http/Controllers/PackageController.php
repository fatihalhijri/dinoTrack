<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Packages\ActivatePackage;
use App\Actions\Packages\CreatePackage;
use App\Actions\Packages\DeactivatePackage;
use App\Actions\Packages\DeletePackage;
use App\Actions\Packages\UpdatePackage;
use App\Http\Requests\Packages\PackageIndexRequest;
use App\Http\Requests\Packages\StorePackageRequest;
use App\Http\Requests\Packages\UpdatePackageRequest;
use App\Http\Resources\PackageResource;
use App\Models\Package;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tambah dan ubah paket memakai modal di halaman daftar (tanpa halaman create/edit).
 */
class PackageController extends Controller
{
    public function index(PackageIndexRequest $request): Response
    {
        $packages = Package::query()
            ->withCount('subscriptions')
            ->applyFilters($request->filters())
            ->orderBy('name')
            ->paginate($request->perPage())
            ->withQueryString();

        return Inertia::render('packages/index', [
            'packages' => PackageResource::collection($packages),
            'filters' => $request->validated(),
        ]);
    }

    public function store(StorePackageRequest $request, CreatePackage $createPackage): RedirectResponse
    {
        $package = $createPackage->handle($request->packageData(), $request->user());
        $this->toast("Paket {$package->name} ditambahkan.");

        return to_route('packages.index');
    }

    public function update(UpdatePackageRequest $request, Package $package, UpdatePackage $updatePackage): RedirectResponse
    {
        $updatePackage->handle($package, $request->packageData(), $request->user());
        $this->toast("Paket {$package->name} diperbarui.");

        return to_route('packages.index');
    }

    public function destroy(Request $request, Package $package, DeletePackage $deletePackage): RedirectResponse
    {
        Gate::authorize('delete', $package);
        $deletePackage->handle($package, $request->user());
        $this->toast("Paket {$package->name} dihapus.");

        return to_route('packages.index');
    }

    public function activate(Request $request, Package $package, ActivatePackage $activatePackage): RedirectResponse
    {
        Gate::authorize('update', $package);
        $activatePackage->handle($package, $request->user());
        $this->toast("Paket {$package->name} diaktifkan.");

        return back();
    }

    public function deactivate(Request $request, Package $package, DeactivatePackage $deactivatePackage): RedirectResponse
    {
        Gate::authorize('update', $package);
        $deactivatePackage->handle($package, $request->user());
        $this->toast("Paket {$package->name} dinonaktifkan.");

        return back();
    }
}
