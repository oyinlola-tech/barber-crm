<?php

namespace App\Repositories\Conversations;

use App\Interfaces\Repositories\Conversations\ConversationRepositoryInterface;
use App\Models\Conversation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ConversationRepository implements ConversationRepositoryInterface
{
    public function paginateForBusiness(int $businessId, ?string $status = null, ?string $search = null): LengthAwarePaginator
    {
        return Conversation::query()->where('business_id', $businessId)
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($search !== null && $search !== '', function ($query) use ($search): void {
                $query->whereHas('customer', function ($customerQuery) use ($search): void {
                    $customerQuery->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%');
                });
            })
            ->with(['customer:id,first_name,last_name,phone', 'latestMessage:id,conversation_id,direction,type,status,body,created_at'])
            ->orderByDesc('last_message_at')->orderByDesc('id')->paginate(30)->withQueryString();
    }

    public function findForBusiness(int $businessId, int $conversationId, bool $includeInternalNotes = true): Conversation
    {
        $relations = [
            'customer:id,first_name,last_name,phone,status,archived_at',
            'messages' => fn ($query) => $query->orderBy('created_at')->orderBy('id'),
        ];
        if ($includeInternalNotes) {
            $relations['notes.creator'] = fn ($query) => $query->select(['id', 'name']);
        }

        return Conversation::query()
            ->where('business_id', $businessId)
            ->with($relations)
            ->findOrFail($conversationId);
    }
}
