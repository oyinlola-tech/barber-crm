<?php

namespace App\Http\Controllers\Conversations;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\Consent\Queries\CustomerCanReceiveMessageQuery;
use App\Services\Conversations\Commands\AddConversationNoteCommand;
use App\Services\Conversations\Commands\AssignConversationCommand;
use App\Services\Conversations\Commands\MarkConversationReadCommand;
use App\Services\Conversations\Commands\SendConversationReplyCommand;
use App\Services\Conversations\Commands\SetConversationStatusCommand;
use App\Services\Conversations\Queries\GetConversationQuery;
use App\Services\Conversations\Queries\ListConversationsQuery;
use App\Validators\Conversations\AssignConversationRequest;
use App\Validators\Conversations\SetConversationStatusRequest;
use App\Validators\Conversations\StoreConversationNoteRequest;
use App\Validators\Conversations\StoreConversationReplyRequest;
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

    public function show(Request $request, int $conversation, GetConversationQuery $query, CustomerCanReceiveMessageQuery $consent): Response
    {
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

        return Inertia::render('inbox/show', [
            'conversation' => $conversation,
            'assignees' => $business->members()->wherePivot('status', 'active')->orderBy('name')->get(['users.id', 'users.name']),
            'canReply' => $canReplyPermission
                && $hasConsent
                && $isWithinServiceWindow
                && $conversation->customer->archived_at === null
                && $conversation->customer->status === 'active',
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
