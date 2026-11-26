<?php

namespace App\Http\Controllers\Conversations;

use App\Configs\WhatsAppConfig;
use App\Enums\MessageStatus;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\WhatsAppConnection;
use App\Services\Consent\Queries\CustomerCanReceiveMessageQuery;
use App\Services\Conversations\Commands\AddConversationNoteCommand;
use App\Services\Conversations\Commands\AssignConversationCommand;
use App\Services\Conversations\Commands\MarkConversationReadCommand;
use App\Services\Conversations\Commands\SendConversationReplyCommand;
use App\Services\Conversations\Commands\SendConversationTemplateReplyCommand;
use App\Services\Conversations\Commands\SetConversationStatusCommand;
use App\Services\Conversations\Queries\GetConversationQuery;
use App\Services\Conversations\Queries\ListConversationsQuery;
use App\Services\Templates\Queries\ListMessageTemplatesQuery;
use App\Validators\Conversations\AssignConversationRequest;
use App\Validators\Conversations\SetConversationStatusRequest;
use App\Validators\Conversations\StoreConversationNoteRequest;
use App\Validators\Conversations\StoreConversationReplyRequest;
use App\Validators\Conversations\StoreConversationTemplateReplyRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function index(Request $request, ListConversationsQuery $query): Response
    {
        $businessId = $this->businessId($request);
        abort_unless($request->user()->hasBusinessPermission($businessId, 'conversations.view'), 403);
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'closed'])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return Inertia::render('inbox/index', [
            'conversations' => $query->execute($businessId, $filters['status'] ?? null, $filters['search'] ?? null),
            'filters' => $filters,
        ]);
    }

    public function show(
        Request $request,
        int $conversation,
        GetConversationQuery $query,
        CustomerCanReceiveMessageQuery $consent,
        ListMessageTemplatesQuery $templatesQuery,
        WhatsAppConfig $whatsAppConfiguration,
    ): Response {
        $businessId = $this->businessId($request);
        abort_unless($request->user()->hasBusinessPermission($businessId, 'conversations.view'), 403);
        $user = $request->user();
        $canReplyPermission = $user->hasBusinessPermission($businessId, 'conversations.reply');
        $canAssign = $user->hasBusinessPermission($businessId, 'conversations.assign');
        $canChangeStatus = $user->hasBusinessPermission($businessId, 'conversations.close');
        $conversation = $query->execute($businessId, $conversation, $canReplyPermission);
        $business = Business::query()->findOrFail($businessId);
        $latestInbound = $conversation->messages()->where('direction', 'inbound')->whereNotNull('sent_at')->latest('sent_at')->first();
        $isWithinServiceWindow = $latestInbound !== null && $latestInbound->sent_at->gte(now()->subHours(24));
        $hasConsent = $consent->execute($businessId, (int) $conversation->customer_id, 'whatsapp');
        $customerIsActive = $conversation->customer->archived_at === null && $conversation->customer->status === 'active';
        $latestUncertainTemplate = $conversation->messages()
            ->where('direction', 'outbound')
            ->where('type', 'template')
            ->where('status', 'delivery_uncertain')
            ->latest('created_at')
            ->first();
        $latestInbound = $conversation->messages()
            ->where('direction', 'inbound')
            ->whereNotNull('sent_at')
            ->latest('sent_at')
            ->first();
        $templateSendBlocked = $latestUncertainTemplate instanceof Message
            && ($latestInbound?->sent_at === null || $latestInbound->sent_at->lessThanOrEqualTo($latestUncertainTemplate->created_at));
        $connectionIsReady = WhatsAppConnection::query()
            ->where('business_id', $businessId)
            ->where('status', 'connected')
            ->whereNotNull('access_token')
            ->whereNotNull('phone_number_id')
            ->exists() && $whatsAppConfiguration->graphApiConfigured();
        $canSendTemplate = $canReplyPermission && $hasConsent && $customerIsActive && $connectionIsReady;
        $availableTemplates = $canSendTemplate
            ? $templatesQuery->execute($businessId, true)
                ->filter(fn (MessageTemplate $template): bool => $template->provider_template_id !== null)
                ->map(fn (MessageTemplate $template): array => [
                    'id' => (int) $template->getKey(),
                    'name' => $template->name,
                    'language' => $template->language,
                    'purpose' => $template->purpose,
                ])
                ->values()
                ->all()
            : [];

        return Inertia::render('inbox/show', [
            'conversation' => $conversation,
            'assignees' => $business->members()->wherePivot('status', 'active')->orderBy('name')->get(['users.id', 'users.name']),
            'canReply' => $canReplyPermission
                && $hasConsent
                && $isWithinServiceWindow
                && $customerIsActive,
            'canSendTemplate' => $canSendTemplate,
            'templateSendBlocked' => $templateSendBlocked,
            'templates' => $availableTemplates,
            'canReplyPermission' => $canReplyPermission,
            'canAssign' => $canAssign,
            'canChangeStatus' => $canChangeStatus,
            'canAddNote' => $canReplyPermission,
            'consentGranted' => $hasConsent,
            'serviceWindowOpen' => $isWithinServiceWindow,
        ]);
    }

    public function reply(StoreConversationReplyRequest $request, int $conversation, SendConversationReplyCommand $command): RedirectResponse
    {
        $command->execute(
            $this->businessId($request),
            $conversation,
            (int) $request->user()->getKey(),
            $request->validated('body'),
        );

        return back()->with('success', 'Reply submitted to WhatsApp. Delivery status will update when Meta confirms it.');
    }

    public function templateReply(
        StoreConversationTemplateReplyRequest $request,
        int $conversation,
        SendConversationTemplateReplyCommand $command,
    ): RedirectResponse {
        $message = $command->execute(
            $this->businessId($request),
            $conversation,
            (int) $request->user()->getKey(),
            (int) $request->validated('template_id'),
            (string) $request->validated('idempotency_key'),
        );

        $feedback = match ($message->status) {
            MessageStatus::Accepted->value => 'WhatsApp accepted the template. Delivery status will update when Meta confirms it.',
            MessageStatus::Failed->value => 'WhatsApp rejected the template send. Review the message status before trying again.',
            MessageStatus::DeliveryUncertain->value => 'WhatsApp delivery is uncertain. Do not resend until the message is reconciled.',
            MessageStatus::Skipped->value => 'The template was not sent because customer eligibility changed. Review consent and customer status.',
            MessageStatus::Cancelled->value => 'The template was cancelled because your reply permission changed.',
            default => 'The template request is already being processed. Check its delivery status before taking further action.',
        };

        return back()->with('status', $feedback);
    }

    public function assign(AssignConversationRequest $request, int $conversation, AssignConversationCommand $command): RedirectResponse
    {
        $command->execute(
            $this->businessId($request),
            $conversation,
            (int) $request->user()->getKey(),
            $request->validated('assignee_id'),
        );

        return back()->with('success', 'Conversation assignment updated.');
    }

    public function addNote(StoreConversationNoteRequest $request, int $conversation, AddConversationNoteCommand $command): RedirectResponse
    {
        $command->execute(
            $this->businessId($request),
            $conversation,
            (int) $request->user()->getKey(),
            $request->validated('body'),
        );

        return back()->with('success', 'Internal note added.');
    }

    public function setStatus(SetConversationStatusRequest $request, int $conversation, SetConversationStatusCommand $command): RedirectResponse
    {
        $command->execute(
            $this->businessId($request),
            $conversation,
            (int) $request->user()->getKey(),
            $request->validated('status'),
        );

        return back()->with('success', 'Conversation status updated.');
    }

    public function markRead(Request $request, int $conversation, MarkConversationReadCommand $command): RedirectResponse
    {
        abort_unless($request->user()->hasBusinessPermission($this->businessId($request), 'conversations.view'), 403);
        $command->execute($this->businessId($request), $conversation, (int) $request->user()->getKey());

        return back()->with('success', 'Conversation marked as read.');
    }

    private function businessId(Request $request): int
    {
        $business = $request->attributes->get('currentBusiness');
        abort_unless($business instanceof Business, 404);

        return (int) $business->getKey();
    }
}
