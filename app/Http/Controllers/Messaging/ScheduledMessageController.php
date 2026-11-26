<?php

namespace App\Http\Controllers\Messaging;

use App\Http\Controllers\Controller;
use App\Services\Automations\Commands\CancelScheduledMessageCommand;
use App\Services\Automations\Commands\RetryFailedScheduledMessageCommand;
use App\Services\Automations\Queries\ListScheduledMessagesQuery;
use App\Validators\Messaging\CancelScheduledMessageRequest;
use App\Validators\Messaging\RetryFailedScheduledMessageRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScheduledMessageController extends Controller
{
    public function index(Request $request, ListScheduledMessagesQuery $query): Response
    {
        $businessId = (int) $request->attributes->get('currentBusiness')->getKey();
        abort_unless($request->user()->hasBusinessPermission($businessId, 'messages.view'), 403);

        $status = $request->query('status');
        abort_if($status !== null && ! in_array($status, ['pending', 'queued', 'processing', 'sending', 'sent', 'failed', 'cancelled', 'reconciliation_required'], true), 422);

        return Inertia::render('messages/scheduled', [
            'messages' => $query->execute($businessId, is_string($status) ? $status : null),
            'filters' => ['status' => $status],
            'permissions' => [
                'cancel' => $request->user()->hasBusinessPermission($businessId, 'messages.cancel'),
                'retry' => $request->user()->hasBusinessPermission($businessId, 'messages.retry'),
            ],
            'manualRetryLimit' => min(5, max(0, (int) config('automation.manual_retry_limit', 2))),
        ]);
    }

    public function cancel(CancelScheduledMessageRequest $request, int $scheduledMessage, CancelScheduledMessageCommand $command): RedirectResponse
    {
        $command->execute((int) $request->attributes->get('currentBusiness')->getKey(), $scheduledMessage, (int) $request->user()->getKey());

        return back()->with('success', 'Scheduled message cancelled.');
    }

    public function retry(RetryFailedScheduledMessageRequest $request, int $scheduledMessage, RetryFailedScheduledMessageCommand $command): RedirectResponse
    {
        $command->execute((int) $request->attributes->get('currentBusiness')->getKey(), $scheduledMessage, (int) $request->user()->getKey());

        return back()->with('success', 'Failed message queued for a safe retry.');
    }
}
