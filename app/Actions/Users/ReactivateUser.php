<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReactivateUser
{
    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    public function handle(User $user, User $by): User
    {
        return DB::transaction(function () use ($user, $by): User {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            if (! $user->isDeactivated()) {
                throw ValidationException::withMessages(['user' => 'Akun masih aktif.']);
            }

            $user->deactivated_at = null;
            $user->save();

            $this->logger->log('user.reactivated', $user, $by);

            return $user;
        });
    }
}
