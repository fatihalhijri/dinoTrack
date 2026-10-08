<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\UpdateBillingSettings;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateBillingSettingsRequest;
use App\Support\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BillingSettingsController extends Controller
{
    public function edit(SettingsRepository $settings): Response
    {
        Gate::authorize(Permission::SettingsManage->value);

        return Inertia::render('settings/billing', [
            'billing' => [
                'due_days' => $settings->dueDays(),
                'grace_days' => $settings->graceDays(),
                'reminder_days_before' => $settings->reminderDaysBefore(),
                'prorate_first_month' => $settings->prorateFirstMonth(),
                'auto_isolate' => $settings->autoIsolate(),
                'auto_activate' => $settings->autoActivate(),
            ],
            'limits' => [
                'max_due_days' => UpdateBillingSettings::MAX_DUE_DAYS,
                'max_grace_days' => UpdateBillingSettings::MAX_GRACE_DAYS,
            ],
        ]);
    }

    public function update(UpdateBillingSettingsRequest $request, UpdateBillingSettings $updateSettings): RedirectResponse
    {
        $updateSettings->handle($request->billingSettings(), $this->actor($request));
        $this->toast('Aturan tagihan disimpan dan berlaku untuk proses berikutnya.');

        return to_route('settings.billing.edit');
    }
}
