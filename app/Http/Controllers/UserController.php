<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Users\CreateUser;
use App\Actions\Users\DeactivateUser;
use App\Actions\Users\DeleteUser;
use App\Actions\Users\ReactivateUser;
use App\Actions\Users\UpdateUser;
use App\Enums\Role;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Requests\Users\UserIndexRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manajemen akun pegawai oleh admin; tambah dan ubah memakai modal di halaman daftar.
 */
class UserController extends Controller
{
    public function index(UserIndexRequest $request): Response
    {
        $users = User::query()
            ->with('roles')
            ->withExists(['receivedPayments', 'activityLogs'])
            ->applyFilters($request->filters())
            ->orderBy('name')
            ->paginate($request->perPage())
            ->withQueryString();

        return Inertia::render('users/index', [
            'users' => UserResource::collection($users),
            'filters' => $request->validated(),
            'roles' => array_map(fn (Role $role): array => ['value' => $role->value, 'label' => $role->label()], Role::cases()),
        ]);
    }

    public function store(StoreUserRequest $request, CreateUser $createUser): RedirectResponse
    {
        $user = $createUser->handle($request->userAttributes(), $request->role(), $this->actor($request));
        $this->toast("Akun {$user->name} dibuat.");

        return to_route('users.index');
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUser $updateUser): RedirectResponse
    {
        $updateUser->handle($user, $request->userAttributes(), $request->role(), $this->actor($request));
        $this->toast("Akun {$user->name} diperbarui.");

        return to_route('users.index');
    }

    public function destroy(Request $request, User $user, DeleteUser $deleteUser): RedirectResponse
    {
        Gate::authorize('delete', $user);
        $deleteUser->handle($user, $this->actor($request));
        $this->toast("Akun {$user->name} dihapus.");

        return to_route('users.index');
    }

    public function deactivate(Request $request, User $user, DeactivateUser $deactivateUser): RedirectResponse
    {
        Gate::authorize('update', $user);
        $deactivateUser->handle($user, $this->actor($request));
        $this->toast("Akun {$user->name} dinonaktifkan dan tidak bisa masuk lagi.");

        return back();
    }

    public function reactivate(Request $request, User $user, ReactivateUser $reactivateUser): RedirectResponse
    {
        Gate::authorize('update', $user);
        $reactivateUser->handle($user, $this->actor($request));
        $this->toast("Akun {$user->name} diaktifkan kembali.");

        return back();
    }
}
