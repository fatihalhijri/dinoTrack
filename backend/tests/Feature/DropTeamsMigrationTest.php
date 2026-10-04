<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('tidak lagi memiliki tabel dan kolom fitur Teams', function () {
    expect(Schema::hasTable('teams'))->toBeFalse()
        ->and(Schema::hasTable('team_members'))->toBeFalse()
        ->and(Schema::hasTable('team_invitations'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'current_team_id'))->toBeFalse();
});
