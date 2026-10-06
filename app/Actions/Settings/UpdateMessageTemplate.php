<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Models\MessageTemplate;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;

/**
 * Isi template berlaku untuk pesan yang dijadwalkan setelahnya; pesan yang sudah antre
 * menyimpan body yang dirender saat dijadwalkan. Template nonaktif tidak dikirim.
 */
final class UpdateMessageTemplate
{
    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @param  array{body: string, is_active: bool}  $attributes
     */
    public function handle(MessageTemplate $template, array $attributes, User $by): MessageTemplate
    {
        return DB::transaction(function () use ($template, $attributes, $by): MessageTemplate {
            $template->fill(['body' => $attributes['body'], 'is_active' => $attributes['is_active']]);
            $changes = $this->logger->pendingChanges($template);

            if ($changes === []) {
                return $template;
            }

            $template->save();
            $this->logger->log('message_template.updated', $template, $by, ['changes' => $changes]);

            return $template;
        });
    }
}
