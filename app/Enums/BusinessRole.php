<?php

namespace App\Enums;

enum BusinessRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Barber = 'barber';
    case Receptionist = 'receptionist';
    case Viewer = 'viewer';

    /** @return array<string, string> */
    public static function permissions(): array
    {
        return [
            'dashboard.view' => 'View dashboard', 'customers.view' => 'View customers',
            'customers.create' => 'Create customers', 'customers.update' => 'Update customers',
            'customers.archive' => 'Archive customers', 'customers.restore' => 'Restore customers',
            'customers.delete' => 'Delete customers', 'customers.import' => 'Import customers',
            'customers.export' => 'Export customers', 'customers.anonymize' => 'Anonymize customer data', 'consent.view' => 'View consent',
            'consent.record' => 'Record consent', 'consent.revoke' => 'Revoke consent',
            'visits.view' => 'View visits', 'visits.create' => 'Record visits',
            'visits.update' => 'Update visits', 'visits.delete' => 'Void visits', 'visits.restore' => 'Restore voided visits',
            'services.view' => 'View service catalog', 'services.create' => 'Create services',
            'services.update' => 'Update or deactivate services',
            'services.categories.create' => 'Create service categories',
            'services.categories.update' => 'Update service categories',
            'services.categories.delete' => 'Delete unused service categories',
            'staff.view' => 'View staff', 'staff.invite' => 'Invite staff', 'staff.update' => 'Update staff',
            'staff.suspend' => 'Suspend staff', 'staff.activate' => 'Activate staff',
            'staff.delete' => 'Remove staff access and redact profile',
            'roles.view' => 'View roles', 'roles.create' => 'Create roles',
            'roles.update' => 'Update roles', 'roles.delete' => 'Delete roles', 'roles.assign' => 'Assign roles',
            'permissions.view' => 'View permissions', 'conversations.view' => 'View conversations',
            'conversations.reply' => 'Reply to conversations', 'conversations.assign' => 'Assign conversations',
            'conversations.close' => 'Close conversations', 'messages.view' => 'View messages',
            'messages.send' => 'Send messages', 'messages.cancel' => 'Cancel messages',
            'messages.retry' => 'Retry failed messages', 'automations.view' => 'View automations',
            'automations.create' => 'Create automations', 'automations.update' => 'Update automations',
            'automations.enable' => 'Enable automations', 'automations.disable' => 'Disable automations',
            'automations.delete' => 'Delete automations', 'templates.view' => 'View templates',
            'templates.create' => 'Create templates', 'templates.update' => 'Update templates',
            'templates.sync' => 'Synchronize templates', 'templates.delete' => 'Delete templates',
            'reviews.view' => 'View reviews', 'reviews.create' => 'Record reviews',
            'reviews.update' => 'Update reviews', 'reviews.export' => 'Export reviews',
            'analytics.view' => 'View analytics', 'analytics.export' => 'Export analytics',
            'whatsapp.view' => 'View WhatsApp connection', 'whatsapp.configure' => 'Configure WhatsApp connection',
            'whatsapp.disconnect' => 'Disconnect WhatsApp', 'settings.view' => 'View settings',
            'settings.update' => 'Update settings', 'audit.view' => 'View audit records',
            'audit.export' => 'Export audit records', 'system.health.view' => 'View system health',
        ];
    }

    public function canManageCustomers(): bool
    {
        return in_array($this, [self::Owner, self::Manager, self::Receptionist], true);
    }

    public function canManageSettings(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }
}
