<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * User baru dengan role dan permission hasil RolePermissionSeeder.
 */
function userWithRole(Role $role): User
{
    test()->seed(RolePermissionSeeder::class);

    return User::factory()->create()->assignRole($role);
}

/**
 * Dataset role × ability untuk test policy. Setiap ability menyebut role yang boleh;
 * role lain diharapkan ditolak. `viewAny` dan `create` dicek terhadap nama kelas.
 * Target berupa closure agar model dibuat setelah aplikasi siap (bound dataset Pest).
 *
 * @param  class-string<Model>  $modelClass
 * @param  array<string, list<Role>>  $allowedRoles
 * @return array<string, array{string, Closure(): (Model|class-string<Model>), Role, bool}>
 */
function policyMatrix(string $modelClass, array $allowedRoles): array
{
    $dataset = [];

    foreach ($allowedRoles as $ability => $roles) {
        $target = in_array($ability, ['viewAny', 'create'], true)
            ? fn (): string => $modelClass
            : fn (): Model => new $modelClass;

        foreach (Role::cases() as $role) {
            $allowed = in_array($role, $roles, true);
            $dataset[sprintf('%s %s %s', $role->value, $allowed ? 'boleh' : 'tidak boleh', $ability)] = [$ability, $target, $role, $allowed];
        }
    }

    return $dataset;
}
