<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function share(Request $request): array
    {
        $user = $request->user();
        $navigationItems = [
            ['label' => 'Dashboard', 'href' => '/dashboard', 'permission' => 'dashboard.view'],
            ['label' => 'Customers', 'href' => '/customers', 'permission' => 'customers.view'],
            ['label' => 'Services', 'href' => '/services', 'permission' => 'services.view'],
            ['label' => 'Inbox', 'href' => '/inbox', 'permission' => 'conversations.view'],
            ['label' => 'Follow-ups', 'href' => '/automations', 'permission' => 'automations.view'],
            ['label' => 'Scheduled messages', 'href' => '/messages/scheduled', 'permission' => 'messages.view'],
            ['label' => 'Templates', 'href' => '/templates', 'permission' => 'templates.view'],
            ['label' => 'Notifications', 'href' => '/notifications', 'permission' => 'notifications.view'],
            ['label' => 'Reviews', 'href' => '/reviews', 'permission' => 'reviews.view'],
            ['label' => 'Analytics', 'href' => '/analytics', 'permission' => 'analytics.view'],
            ['label' => 'Audit log', 'href' => '/operations/audit', 'permission' => 'audit.view'],
            ['label' => 'System health', 'href' => '/operations/health', 'permission' => 'system.health.view'],
            ['label' => 'Staff', 'href' => '/staff', 'permission' => 'staff.view'],
            ['label' => 'Roles', 'href' => '/roles', 'permission' => 'roles.view'],
            ['label' => 'WhatsApp', 'href' => '/settings/whatsapp', 'permission' => 'whatsapp.view'],
            ['label' => 'Business settings', 'href' => '/settings/business', 'permission' => 'settings.view'],
            ['label' => 'Account security', 'href' => '/settings/security', 'permission' => 'account.security'],
        ];

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user?->only(['id', 'name', 'email']),
            ],
            'currentBusiness' => fn () => $request->attributes->get('currentBusiness')?->only(['id', 'name', 'slug', 'timezone', 'currency']),
            'navigation' => function () use ($request, $navigationItems): array {
                $currentUser = $request->user();
                $currentBusinessId = $request->attributes->get('currentBusiness')?->getKey();
                if ($currentUser === null || $currentBusinessId === null) {
                    return [];
                }

                $permissions = $currentUser->businessPermissions((int) $currentBusinessId);

                return array_values(array_map(
                    static fn (array $item): array => ['label' => $item['label'], 'href' => $item['href']],
                    array_filter($navigationItems, static function (array $item) use ($permissions): bool {
                        $scope = strstr($item['permission'], '.', true);

                        return in_array('*', $permissions, true)
                            || $item['permission'] === 'account.security'
                            || in_array($item['permission'], $permissions, true)
                            || ($scope !== false && in_array($scope.'.*', $permissions, true));
                    }),
                ));
            },
            'status' => $request->session()->get('status'),
            'importResult' => $request->session()->get('importResult'),
        ]);
    }
}
