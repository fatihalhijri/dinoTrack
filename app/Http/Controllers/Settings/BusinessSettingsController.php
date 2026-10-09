<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\RemoveBusinessLogo;
use App\Actions\Settings\UpdateBusinessProfile;
use App\Actions\Settings\UploadBusinessLogo;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateBusinessSettingsRequest;
use App\Http\Requests\Settings\UploadBusinessLogoRequest;
use App\Support\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Profil usaha (termasuk logo) untuk halaman publik dan pesan pelanggan.
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
            'logo_url' => $settings->businessLogoUrl(),
            'logo_max_kilobytes' => UploadBusinessLogoRequest::MAX_KILOBYTES,
        ]);
    }

    public function update(UpdateBusinessSettingsRequest $request, UpdateBusinessProfile $updateProfile): RedirectResponse
    {
        $updateProfile->handle($request->profile(), $this->actor($request));
        $this->toast('Profil usaha disimpan.');

        return to_route('settings.business.edit');
    }

    public function storeLogo(UploadBusinessLogoRequest $request, UploadBusinessLogo $uploadLogo): RedirectResponse
    {
        $uploadLogo->handle($request->logo(), $this->actor($request));
        $this->toast('Logo usaha disimpan.');

        return to_route('settings.business.edit');
    }

    public function destroyLogo(Request $request, RemoveBusinessLogo $removeLogo): RedirectResponse
    {
        Gate::authorize(Permission::SettingsManage->value);
        $removeLogo->handle($this->actor($request));
        $this->toast('Logo usaha dihapus.');

        return to_route('settings.business.edit');
    }
}
