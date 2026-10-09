<?php

declare(strict_types=1);

use App\Enums\MessageTemplateKey;
use App\Enums\Role;
use App\Models\MessageTemplate;
use App\Support\MessageTemplateRenderer;
use Inertia\Testing\AssertableInertia as Assert;

it('menampilkan template pesan beserta placeholder yang dikenali', function () {
    MessageTemplate::factory()->create(['key' => MessageTemplateKey::InvoiceIssued]);

    $this->actingAs(userWithRole(Role::Admin))
        ->get(route('settings.message-templates.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/message-templates', true)
            ->has('templates', 1)
            ->where('templates.0.label', 'Tagihan terbit')
            ->has('placeholders.{link_bayar}'));
});

it('mengirim contoh nilai placeholder yang diformat seperti pesan sungguhan', function () {
    $this->travelTo('2026-10-09 10:00');

    $this->actingAs(userWithRole(Role::Admin))
        ->get(route('settings.message-templates.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('placeholder_examples', fn ($examples): bool => array_keys($examples->all()) === array_keys(MessageTemplateRenderer::PLACEHOLDERS))
            ->where('placeholder_examples.{total}', 'Rp150.000')
            ->where('placeholder_examples.{nomor_invoice}', 'INV/2026/10/00001')
            ->where('placeholder_examples.{jatuh_tempo}', '16 Oktober 2026'));
});

it('menyimpan perubahan template pesan', function () {
    $template = MessageTemplate::factory()->create(['key' => MessageTemplateKey::ReminderDue, 'is_active' => true]);

    $this->actingAs(userWithRole(Role::Admin))
        ->put(route('settings.message-templates.update', $template), ['body' => 'Halo {nama}', 'is_active' => '0'])
        ->assertRedirect(route('settings.message-templates.index'));

    expect($template->refresh())
        ->body->toBe('Halo {nama}')
        ->is_active->toBeFalse();
});
