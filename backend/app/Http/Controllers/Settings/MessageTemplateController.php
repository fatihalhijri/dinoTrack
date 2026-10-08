<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\UpdateMessageTemplate;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateMessageTemplateRequest;
use App\Http\Resources\MessageTemplateResource;
use App\Models\MessageTemplate;
use App\Support\MessageTemplateRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MessageTemplateController extends Controller
{
    public function index(): Response
    {
        Gate::authorize(Permission::SettingsManage->value);

        return Inertia::render('settings/message-templates', [
            'templates' => MessageTemplateResource::collection(MessageTemplate::query()->orderBy('id')->get()),
            'placeholders' => MessageTemplateRenderer::PLACEHOLDERS,
            'max_length' => UpdateMessageTemplateRequest::MAX_BODY_LENGTH,
        ]);
    }

    public function update(UpdateMessageTemplateRequest $request, MessageTemplate $template, UpdateMessageTemplate $updateTemplate): RedirectResponse
    {
        $updateTemplate->handle($template, $request->template(), $this->actor($request));
        $this->toast("Template \"{$template->key->label()}\" disimpan.");

        return to_route('settings.message-templates.index');
    }
}
