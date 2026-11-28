<?php

namespace App\Services\Automations\Commands;

use App\Configs\WhatsAppConfig;
use App\Enums\ScheduledMessageStatus;
use App\Interfaces\Apis\MessagingProviderInterface;
use App\Interfaces\Repositories\Conversations\ConversationRepositoryInterface;
use App\Mappers\Templates\WhatsAppTemplateDataMapper;
use App\Models\AutomationRule;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\ScheduledMessage;
use App\Models\WhatsAppConnection;
use App\Services\Automations\SendWindowCalculator;
use App\Services\Consent\Queries\CustomerCanReceiveMessageQuery;
use App\Services\Customers\Queries\CustomerHasUpcomingReturnDateQuery;
use App\Services\Notifications\Commands\NotifyStaffOfScheduledMessageFailureCommand;
use App\Services\Settings\Queries\GetWhatsAppMessageFeaturesQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProcessScheduledMessageCommand
{
    public function __construct(
        public MessagingProviderInterface $messaging,
        public CustomerCanReceiveMessageQuery $consent,
        public SendWindowCalculator $sendWindow,
        private readonly ScheduleNextFollowUpAfterSentMessageCommand $scheduleNextFollowUp,
        public WhatsAppConfig $whatsAppConfiguration,
        public ConversationRepositoryInterface $conversations,
        public WhatsAppTemplateDataMapper $templateData,
        private readonly CustomerHasUpcomingReturnDateQuery $upcomingReturnDate,
        private readonly GetWhatsAppMessageFeaturesQuery $messageFeatures,
        private readonly NotifyStaffOfScheduledMessageFailureCommand $failureNotifier,
    ) {}

    public function execute(int $scheduledMessageId): void
    {
        $context = DB::transaction(function () use ($scheduledMessageId): ?array {
            $scheduledMessage = ScheduledMessage::query()->lockForUpdate()->findOrFail($scheduledMessageId);
            if ($scheduledMessage->status !== ScheduledMessageStatus::Queued->value) {
                return null;
            }

            $customer = Customer::query()
                ->where('business_id', $scheduledMessage->business_id)
                ->lockForUpdate()
                ->find($scheduledMessage->customer_id);
            if ($customer === null || $customer->status !== 'active' || $customer->archived_at !== null) {
                $this->cancel($scheduledMessage, 'Customer is not eligible for automated messages.');

                return null;
            }

            if (! $this->consent->execute($scheduledMessage->business_id, $customer->getKey(), $scheduledMessage->channel)) {
                $this->cancel($scheduledMessage, 'Customer messaging consent is not active.');

                return null;
            }

            $rule = $scheduledMessage->automation_rule_id === null
                ? null
                : AutomationRule::query()->where('business_id', $scheduledMessage->business_id)->find($scheduledMessage->automation_rule_id);
            if ($rule === null || ! $rule->is_enabled) {
                $this->cancel($scheduledMessage, 'The associated automation rule is no longer active.');

                return null;
            }

            if (! $this->isStillEligibleForTrigger($scheduledMessage, $customer, $rule)) {
                $this->cancel($scheduledMessage, 'The customer no longer meets the automation trigger conditions.');

                return null;
            }

            $payload = $scheduledMessage->payload ?? [];
            $template = MessageTemplate::query()
                ->where('business_id', $scheduledMessage->business_id)
                ->where('provider_status', 'APPROVED')
                ->where('is_enabled', true)
                ->find($payload['template_id'] ?? null);
            if ($template === null || $template->purpose === null || $template->provider_template_id === null
                || $template->name !== ($payload['template_name'] ?? null)
                || $template->language !== ($payload['language'] ?? null)
                || (($payload['trigger'] ?? $rule->trigger) === 'customer_inactive' && ! in_array($template->purpose, ['reengagement', 'retention'], true))
                || (($payload['trigger'] ?? $rule->trigger) === 'return_date_missed' && ! in_array($template->purpose, ['reengagement', 'retention', 'return_intention'], true))) {
                $this->cancel($scheduledMessage, 'The selected WhatsApp template is no longer approved and enabled.');

                return null;
            }

            if (! $this->messageFeatures->allowsAutomation(
                $scheduledMessage->business_id,
                $template->purpose,
                (string) ($payload['trigger'] ?? $rule->trigger),
            )) {
                $this->cancel($scheduledMessage, 'WhatsApp messaging for this feature is turned off.');

                return null;
            }

            $connection = WhatsAppConnection::query()
                ->where('business_id', $scheduledMessage->business_id)
                ->where('status', 'connected')
                ->first();
            if ($connection === null || $connection->access_token === null || $connection->phone_number_id === null
                || ! $this->whatsAppConfiguration->graphApiConfigured()) {
                $scheduledMessage->update([
                    'status' => ScheduledMessageStatus::Failed->value,
                    'failed_at' => now(),
                    'last_error' => 'whatsapp_connection_incomplete',
                ]);

                return [
                    'terminal_failure_id' => (int) $scheduledMessage->getKey(),
                    'terminal_failure_business_id' => (int) $scheduledMessage->business_id,
                ];
            }

            $business = Business::query()->findOrFail($scheduledMessage->business_id);
            $sendAt = $this->sendWindow->nextAllowedAt(now('UTC'), $business->timezone, $rule->settings['sending_hours'] ?? null);
            if ($sendAt->isFuture()) {
                $scheduledMessage->update([
                    'status' => ScheduledMessageStatus::Pending->value,
                    'scheduled_at' => $sendAt,
                    'claimed_at' => null,
                    'last_error' => null,
                ]);

                return null;
            }

            try {
                $components = $this->templateData->map($template, $customer, $business)['components'];
            } catch (ValidationException) {
                $scheduledMessage->update([
                    'status' => ScheduledMessageStatus::Failed->value,
                    'failed_at' => now(),
                    'last_error' => 'template_parameter_mapping_invalid',
                ]);

                return [
                    'terminal_failure_id' => (int) $scheduledMessage->getKey(),
                    'terminal_failure_business_id' => (int) $scheduledMessage->business_id,
                ];
            }

            $conversation = $scheduledMessage->conversation_id !== null
                ? Conversation::query()
                    ->where('business_id', $scheduledMessage->business_id)
                    ->findOrFail($scheduledMessage->conversation_id)
                : Conversation::query()->firstOrCreate([
                    'business_id' => $scheduledMessage->business_id,
                    'customer_id' => $scheduledMessage->customer_id,
                    'channel' => $scheduledMessage->channel,
                ], ['status' => 'open']);

            $scheduledMessage->update([
                'status' => ScheduledMessageStatus::Sending->value,
                'conversation_id' => $conversation->getKey(),
                'claimed_at' => now(),
                'attempts' => $scheduledMessage->attempts + 1,
            ]);

            return [
                'scheduled_message' => $scheduledMessage->refresh(),
                'customer' => $customer,
                'business' => $business,
                'template' => $template,
                'connection' => $connection,
                'conversation' => $conversation,
                'rule' => $rule,
                'components' => $components,
            ];
        });

        if ($context === null) {
            return;
        }

        if (isset($context['terminal_failure_id'])) {
            $this->failureNotifier->execute(
                (int) $context['terminal_failure_business_id'],
                (int) $context['terminal_failure_id'],
            );

            return;
        }

        /** @var ScheduledMessage $scheduledMessage */
        $scheduledMessage = $context['scheduled_message'];
        /** @var Customer $customer */
        $customer = $context['customer'];
        /** @var MessageTemplate $template */
        $template = $context['template'];
        /** @var WhatsAppConnection $connection */
        $connection = $context['connection'];
        /** @var Conversation $conversation */
        $conversation = $context['conversation'];
        /** @var AutomationRule $rule */
        $rule = $context['rule'];
        /** @var list<array<string, mixed>> $components */
        $components = $context['components'];

        $hasConsent = $this->consent->execute($scheduledMessage->business_id, $customer->getKey(), $scheduledMessage->channel);
        $currentCustomer = Customer::query()
            ->where('business_id', $scheduledMessage->business_id)
            ->find($customer->getKey());
        $currentRule = AutomationRule::query()
            ->where('business_id', $scheduledMessage->business_id)
            ->find($rule->getKey());
        $currentTemplate = MessageTemplate::query()
            ->where('business_id', $scheduledMessage->business_id)
            ->find($template->getKey());

        if ($currentCustomer === null
            || $currentCustomer->status !== 'active'
            || $currentCustomer->archived_at !== null
            || $currentRule === null
            || ! $currentRule->is_enabled
            || $currentTemplate === null
            || $currentTemplate->purpose === null
            || ! $this->messageFeatures->allowsAutomation(
                $scheduledMessage->business_id,
                $currentTemplate->purpose,
                (string) (($scheduledMessage->payload ?? [])['trigger'] ?? $currentRule->trigger),
            )
            || ! $hasConsent
            || ! $this->isStillEligibleForTrigger($scheduledMessage, $currentCustomer, $currentRule)) {
            DB::transaction(function () use ($scheduledMessage): void {
                $locked = ScheduledMessage::query()->lockForUpdate()->findOrFail($scheduledMessage->getKey());
                if ($locked->status === ScheduledMessageStatus::Sending->value) {
                    $this->cancel($locked, 'Customer eligibility changed before provider dispatch.');
                }
            });

            return;
        }

        $customer = $currentCustomer;
        $rule = $currentRule;

        try {
            $response = $this->messaging->sendTemplate(
                $connection,
                $customer->phone,
                $template->name,
                $template->language,
                ['components' => $components],
            );
        } catch (RequestException $exception) {
            if ($this->handleProviderRejection($scheduledMessage, $exception)) {
                $this->failureNotifier->execute((int) $scheduledMessage->business_id, (int) $scheduledMessage->getKey());
            }

            return;
        } catch (ConnectionException) {
            $this->markUncertain($scheduledMessage);

            return;
        } catch (Throwable) {
            $this->markUncertain($scheduledMessage);

            return;
        }

        $providerMessageId = data_get($response, 'messages.0.id');
        if (! is_string($providerMessageId) || $providerMessageId === '') {
            $this->markUncertain($scheduledMessage);

            return;
        }

        DB::transaction(function () use ($scheduledMessage, $conversation, $providerMessageId, $template): void {
            $lockedMessage = ScheduledMessage::query()->lockForUpdate()->findOrFail($scheduledMessage->getKey());
            if ($lockedMessage->status !== ScheduledMessageStatus::Sending->value) {
                return;
            }

            Message::query()->firstOrCreate([
                'business_id' => $lockedMessage->business_id,
                'provider_message_id' => $providerMessageId,
            ], [
                'conversation_id' => $conversation->getKey(),
                'scheduled_message_id' => $lockedMessage->getKey(),
                'direction' => 'outbound',
                'type' => 'template',
                'status' => 'accepted',
                'payload' => ['template_name' => $template->name, 'language' => $template->language],
                'sent_at' => now(),
            ]);
            $lockedMessage->update(['status' => ScheduledMessageStatus::Sent->value, 'sent_at' => now(), 'last_error' => null]);
            $conversation->update(['last_message_at' => now()]);
        });

        $this->scheduleNextFollowUp->execute((int) $scheduledMessage->getKey());
    }

    private function handleProviderRejection(ScheduledMessage $scheduledMessage, RequestException $exception): bool
    {
        $status = $exception->response->status();

        return DB::transaction(function () use ($scheduledMessage, $status): bool {
            $locked = ScheduledMessage::query()->lockForUpdate()->findOrFail($scheduledMessage->getKey());
            if ($locked->status !== ScheduledMessageStatus::Sending->value) {
                return false;
            }

            if ($status === 429 && $locked->attempts < (int) ($locked->payload['max_attempts'] ?? config('automation.max_attempts'))) {
                $backoff = config('automation.retry_backoff_seconds', [60, 300, 900, 3600]);
                $index = min(max($locked->attempts - 1, 0), count($backoff) - 1);
                $locked->update([
                    'status' => ScheduledMessageStatus::Pending->value,
                    'scheduled_at' => now()->addSeconds((int) $backoff[$index]),
                    'claimed_at' => null,
                    'last_error' => 'provider_rate_limited',
                ]);

                return false;
            }

            $knownRejection = $status >= 400 && $status < 500;
            $locked->update([
                'status' => $knownRejection ? ScheduledMessageStatus::Failed->value : ScheduledMessageStatus::ReconciliationRequired->value,
                'failed_at' => now(),
                'last_error' => $knownRejection ? 'provider_rejected_http_'.$status : 'provider_response_uncertain_http_'.$status,
            ]);

            return $knownRejection;
        });
    }

    private function markUncertain(ScheduledMessage $scheduledMessage): void
    {
        DB::transaction(function () use ($scheduledMessage): void {
            $locked = ScheduledMessage::query()->lockForUpdate()->findOrFail($scheduledMessage->getKey());
            if ($locked->status === ScheduledMessageStatus::Sending->value) {
                $locked->update([
                    'status' => ScheduledMessageStatus::ReconciliationRequired->value,
                    'failed_at' => now(),
                    'last_error' => 'provider_delivery_outcome_uncertain',
                ]);
            }
        });
    }

    private function cancel(ScheduledMessage $scheduledMessage, string $reason): void
    {
        $scheduledMessage->update([
            'status' => ScheduledMessageStatus::Cancelled->value,
            'last_error' => $reason,
            'claimed_at' => null,
        ]);
    }

    private function isStillEligibleForTrigger(ScheduledMessage $scheduledMessage, Customer $customer, AutomationRule $rule): bool
    {
        $payload = $scheduledMessage->payload ?? [];
        $templatePurpose = MessageTemplate::query()
            ->where('business_id', $scheduledMessage->business_id)
            ->whereKey($payload['template_id'] ?? 0)
            ->value('purpose');
        if (is_string($templatePurpose)
            && in_array($templatePurpose, config('automation.return_reminder_template_purposes', []), true)
            && $this->upcomingReturnDate->execute((int) $scheduledMessage->business_id, $customer)) {
            return false;
        }

        if (array_key_exists('last_customer_message_id', $payload)) {
            $expectedMessageId = $payload['last_customer_message_id'];
            $latestMessageId = $this->conversations->latestInboundMessageIdForCustomer(
                (int) $scheduledMessage->business_id,
                (int) $customer->getKey(),
                (string) $scheduledMessage->channel,
            );

            if ($expectedMessageId === null ? $latestMessageId !== null : (int) $expectedMessageId !== $latestMessageId) {
                return false;
            }
        }

        if (($payload['trigger'] ?? $rule->trigger) === 'return_date_missed') {
            $returnDate = $payload['return_intention_date'] ?? null;
            $business = Business::query()->find($scheduledMessage->business_id);

            return $customer->intended_return_status === 'missed'
                && is_string($returnDate)
                && $customer->intended_return_date !== null
                && CarbonImmutable::parse($customer->intended_return_date)->toDateString() === $returnDate
                && $business !== null
                && ! CarbonImmutable::parse($returnDate)->isAfter(now($business->timezone)->startOfDay());
        }

        if (($payload['trigger'] ?? $rule->trigger) !== 'customer_inactive') {
            return true;
        }

        $anchorValue = $payload['inactivity_anchor'] ?? null;
        $inactiveDays = filter_var(($rule->settings ?? [])['inactive_after_days'] ?? null, FILTER_VALIDATE_INT);
        if (! is_string($anchorValue) || ! is_int($inactiveDays) || $inactiveDays < 3 || $inactiveDays > 365) {
            return false;
        }

        $lastVisitAt = $customer->visits()->max('visited_at');
        if ($lastVisitAt === null) {
            return false;
        }
        $anchor = CarbonImmutable::parse($anchorValue, 'UTC')->utc();
        if (! CarbonImmutable::parse($lastVisitAt, 'UTC')->utc()->equalTo($anchor)) {
            return false;
        }

        $business = Business::query()->find($scheduledMessage->business_id);
        if ($business === null || $anchor->addDays($inactiveDays)->isFuture()) {
            return false;
        }
        if ($customer->intended_return_date !== null
            && ! CarbonImmutable::parse($customer->intended_return_date)->lt(now($business->timezone)->startOfDay())) {
            return false;
        }

        $conversation = $scheduledMessage->conversation_id === null
            ? null
            : Conversation::query()->where('business_id', $scheduledMessage->business_id)->find($scheduledMessage->conversation_id);

        return $conversation?->last_customer_message_at === null
            || ! $conversation->last_customer_message_at->greaterThanOrEqualTo($scheduledMessage->created_at);
    }
}
