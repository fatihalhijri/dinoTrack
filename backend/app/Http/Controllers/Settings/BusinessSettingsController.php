<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\UpdateBusinessProfile;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateBusinessSettingsRequest;
use App\Support\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Profil usaha untuk halaman publik dan pesan pelanggan. Logo menyusul di fase frontend.
 */
class BusinessSettingsController extends Controller
{
    public function edit(SettingsRepository $settings): Response
    {
        Gate::authorize(Permission::SettingsManage->value);
        $name = $settings->get('business.name');

        return Inertia::render('settings/business', [
            'business' => [
                'name' => is_string($name) ? $name : null,
                'address' => $settings->businessAddress(),
                'whatsapp' => $settings->businessWhatsapp(),
            ],
            'default_name' => (string) config('app.name'),
        ]);
    }

    public function update(UpdateBusinessSettingsRequest $request, UpdateBusinessProfile $updateProfile): RedirectResponse
    {
        $updateProfile->handle($request->profile(), $this->actor($request));
        $this->toast('Profil usaha disimpan.');

        return to_route('settings.business.edit');
    }
}
