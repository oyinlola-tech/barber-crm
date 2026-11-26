<?php

namespace App\Services\Notifications\Queries;

use App\Models\User;

class ListUserNotificationsQuery
{
    /** @return list<array{id:string,title:string,message:string,url:?string,created_at:?string,read_at:?string}> */
    public function execute(User $user): array
    {
        return $user->notifications()
            ->latest()
            ->limit(50)
            ->get()
            ->map(function ($notification): array {
                $data = $notification->data;

                return [
                    'id' => (string) $notification->getKey(),
                    'title' => (string) ($data['title'] ?? 'Notification'),
                    'message' => (string) ($data['message'] ?? ''),
                    'url' => $this->safeUrl($data['url'] ?? null),
                    'created_at' => $notification->created_at?->toIso8601String(),
                    'read_at' => $notification->read_at?->toIso8601String(),
                ];
            })
            ->all();
    }

    private function safeUrl(mixed $url): ?string
    {
        if (! is_string($url) || ! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return null;
        }

        return $url;
    }
}
