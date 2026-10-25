<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\ConsentChannel;
use App\Enums\ConsentPurpose;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Users\AuthorizationDenied;
use Illuminate\Validation\ValidationException;

/**
 * Message templates. SMS templates must tell people how to opt out (STOP);
 * WhatsApp templates must name a template approved by the provider, because
 * business-initiated WhatsApp messages may only use approved templates.
 */
final class TemplateService
{
    public function __construct(private readonly MessageRenderer $renderer, private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, channel: string, purpose: string, body_en: string, body_pcm?: string|null, provider_template?: string|null}  $data
     */
    public function save(User $by, array $data, ?MessageTemplate $template = null): MessageTemplate
    {
        if (! $by->hasPermission('messaging.templates')) {
            throw new AuthorizationDenied(__('users.not_allowed'));
        }

        $channel = ConsentChannel::tryFrom($data['channel']);
        $purpose = ConsentPurpose::tryFrom($data['purpose']);
        $errors = [];

        if (! in_array($channel, [ConsentChannel::Sms, ConsentChannel::Whatsapp], true)) {
            $errors['channel'] = __('messaging.channel_unsupported');
        }
        if ($purpose === null) {
            $errors['purpose'] = __('validation.required', ['attribute' => 'purpose']);
        }

        foreach (['body_en' => $data['body_en'], 'body_pcm' => $data['body_pcm'] ?? null] as $field => $body) {
            if ($body === null || trim($body) === '') {
                continue;
            }
            if ($unknown = $this->renderer->unknownPlaceholders($body)) {
                $errors[$field] = __('messaging.unknown_placeholders', ['names' => '{'.implode('}, {', $unknown).'}']);
            } elseif ($channel === ConsentChannel::Sms && stripos($body, 'STOP') === false) {
                $errors[$field] = __('messaging.needs_stop');
            }
        }

        if ($channel === ConsentChannel::Whatsapp) {
            if (blank($data['provider_template'] ?? null)) {
                $errors['provider_template'] = __('messaging.needs_provider_template');
            }
            if (app()->isProduction() && config('services.whatsapp.driver') !== 'cloud_api') {
                $errors['channel'] = __('messaging.whatsapp_not_configured');
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $attributes = [
            'name' => mb_substr($data['name'], 0, 120),
            'channel' => $channel,
            'purpose' => $purpose,
            'body_en' => trim($data['body_en']),
            'body_pcm' => filled($data['body_pcm'] ?? null) ? trim((string) $data['body_pcm']) : null,
            'provider_template' => $channel === ConsentChannel::Whatsapp ? mb_substr((string) $data['provider_template'], 0, 120) : null,
        ];

        if ($template === null) {
            $template = MessageTemplate::query()->create($attributes + ['created_by' => $by->id]);
            $this->audit->record('messaging.template_created', $template, null, [], $by);

            return $template;
        }

        $template->fill($attributes)->forceFill(['version' => $template->version + 1])->save();
        $this->audit->recordChanges('messaging.template_updated', $template);

        return $template;
    }

    public function archive(User $by, MessageTemplate $template): void
    {
        if (! $by->hasPermission('messaging.templates')) {
            throw new AuthorizationDenied(__('users.not_allowed'));
        }

        $template->forceFill(['status' => 'archived'])->save();
        $this->audit->record('messaging.template_archived', $template, null, [], $by);
    }
}
