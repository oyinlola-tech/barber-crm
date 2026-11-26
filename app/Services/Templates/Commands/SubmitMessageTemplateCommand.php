<?php

namespace App\Services\Templates\Commands;

use App\Apis\WhatsApp\WhatsAppTemplateApi;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Models\WhatsAppConnection;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SubmitMessageTemplateCommand
{
    public function __construct(
        public WhatsAppTemplateApi $templateApi,
        public RecordAuditLogCommand $audit,
    ) {}

    public function execute(int $businessId, int $templateId, int $userId): MessageTemplate
    {
        if (! User::query()->findOrFail($userId)->hasBusinessPermission($businessId, 'templates.sync')) {
            throw new AuthorizationException('You are not allowed to synchronize WhatsApp templates.');
        }

        $template = DB::transaction(function () use ($businessId, $templateId): MessageTemplate {
            $template = MessageTemplate::query()
                ->where('business_id', $businessId)
                ->lockForUpdate()
                ->findOrFail($templateId);

            if ($template->status !== 'draft' || $template->provider_template_id !== null) {
                throw new RuntimeException('Only an unsubmitted local draft can be submitted to Meta.');
            }

            $template->update(['status' => 'submission_uncertain']);

            return $template->refresh();
        });

        $connection = WhatsAppConnection::query()
            ->where('business_id', $businessId)
            ->where('status', 'connected')
            ->first();
        if ($connection === null || $connection->business_account_id === null || $connection->access_token === null) {
            $template->update(['status' => 'draft']);
            throw new RuntimeException('A connected WhatsApp Business account is required to submit a template.');
        }

        try {
            $response = $this->templateApi->createTemplate(
                $connection,
                $template->name,
                $template->language,
                strtoupper($template->category),
                $template->components ?? [],
            );
        } catch (RequestException $exception) {
            if ($exception->response->clientError()) {
                $template->update(['status' => 'draft']);
            }

            throw $exception;
        }

        return DB::transaction(function () use ($template, $businessId, $userId, $response): MessageTemplate {
            $template->update([
                'provider_template_id' => $response['id'],
                'provider_status' => strtoupper($response['status']),
                'status' => 'submitted',
                'category' => is_string($response['category'] ?? null) ? strtolower($response['category']) : $template->category,
                'is_enabled' => false,
            ]);
            $this->audit->execute($businessId, $userId, 'message_template.submitted', MessageTemplate::class, $template->getKey(), null, [
                'provider_status' => strtoupper($response['status']),
            ]);

            return $template->refresh();
        });
    }
}
