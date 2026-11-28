<?php

namespace App\Enums;

enum WhatsAppMessageFeature: string
{
    case Outbound = 'outbound';
    case ManualReplies = 'manual_replies';
    case ThankYou = 'thank_you';
    case ExperienceFollowUp = 'experience_follow_up';
    case ReviewRequests = 'review_requests';
    case ReturnReminders = 'return_reminders';
    case InactiveReengagement = 'inactive_reengagement';
    case MissedVisitFollowUp = 'missed_visit_follow_up';

    /** @return array<string, bool> */
    public static function defaults(): array
    {
        return array_fill_keys(array_column(self::cases(), 'value'), true);
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::Outbound->value => 'All outgoing WhatsApp messages',
            self::ManualReplies->value => 'Staff replies and manual templates',
            self::ThankYou->value => 'Visit thank-you messages',
            self::ExperienceFollowUp->value => 'Experience follow-ups',
            self::ReviewRequests->value => 'Review requests',
            self::ReturnReminders->value => 'Return reminders',
            self::InactiveReengagement->value => 'Inactive customer follow-ups',
            self::MissedVisitFollowUp->value => 'Missed return follow-ups',
        ];
    }

    public static function forTemplatePurpose(string $purpose): ?self
    {
        return match ($purpose) {
            'thank_you' => self::ThankYou,
            'experience' => self::ExperienceFollowUp,
            'review_request' => self::ReviewRequests,
            'return_intention', 'retention' => self::ReturnReminders,
            'reengagement' => self::InactiveReengagement,
            default => null,
        };
    }
}
