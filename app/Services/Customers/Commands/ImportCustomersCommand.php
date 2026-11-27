<?php

namespace App\Services\Customers\Commands;

use App\Interfaces\Repositories\Customers\CustomerRepositoryInterface;
use App\Models\Customer;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use App\Utils\PhoneNumberNormalizer;
use App\Validators\Customers\ValidPhoneNumber;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ImportCustomersCommand
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customers,
        private readonly RecordAuditLogCommand $audit,
        private readonly PhoneNumberNormalizer $phoneNumbers,
    ) {}

    /** @param list<array<string, string|null>> $rows @return array{created:int, skipped:int, issues:list<array{row:int, reason:string}>} */
    public function execute(int $businessId, int $userId, array $rows): array
    {
        $actor = User::query()->findOrFail($userId);
        abort_unless($actor->hasBusinessPermission($businessId, 'customers.import'), 403);
        $canImportNotes = $actor->hasBusinessPermission($businessId, 'customers.note');

        $created = 0;
        $issues = [];
        $seenPhones = [];

        foreach ($rows as $index => $input) {
            $rowNumber = $index + 2;
            if (! $canImportNotes && filled($input['notes'] ?? null)) {
                $issues[] = ['row' => $rowNumber, 'reason' => 'Importing staff notes requires the customer note permission.'];

                continue;
            }
            $phone = $this->phoneNumbers->normalize((string) ($input['phone'] ?? ''));
            if ($phone === null) {
                $issues[] = ['row' => $rowNumber, 'reason' => 'Invalid WhatsApp phone number.'];

                continue;
            }

            $attributes = [
                'first_name' => $input['first_name'] ?? null,
                'last_name' => $input['last_name'] ?? null,
                'phone' => $phone,
                'email' => $input['email'] ?? null,
                'date_of_birth' => $input['date_of_birth'] ?? null,
                'notes' => $canImportNotes ? ($input['notes'] ?? null) : null,
            ];
            $validator = Validator::make($attributes, [
                'first_name' => ['required', 'string', 'max:120'],
                'last_name' => ['nullable', 'string', 'max:120'],
                'phone' => ['required', 'string', 'max:32', app(ValidPhoneNumber::class)],
                'email' => ['nullable', 'email', 'max:255'],
                'date_of_birth' => ['nullable', 'date', 'before:today'],
                'notes' => $canImportNotes ? ['nullable', 'string', 'max:5000'] : ['prohibited'],
            ]);

            if ($validator->fails()) {
                $issues[] = ['row' => $rowNumber, 'reason' => implode(' ', $validator->errors()->all())];

                continue;
            }

            if (isset($seenPhones[$phone])) {
                $issues[] = ['row' => $rowNumber, 'reason' => 'Duplicate normalized phone number within this file; no record was changed.'];

                continue;
            }
            $seenPhones[$phone] = true;

            try {
                $wasCreated = DB::transaction(function () use ($businessId, $userId, $attributes): bool {
                    $exists = Customer::query()->where('business_id', $businessId)->where('phone', $attributes['phone'])->exists();
                    if ($exists) {
                        return false;
                    }
                    $customer = $this->customers->createForBusiness($businessId, $userId, $attributes);
                    $this->audit->execute($businessId, $userId, 'customer.created', Customer::class, $customer->getKey(), null, $customer->only(['first_name', 'last_name', 'phone', 'email']));

                    return true;
                });
                if ($wasCreated) {
                    $created++;
                } else {
                    $issues[] = ['row' => $rowNumber, 'reason' => 'A customer with this normalized phone number already exists; no record was changed.'];
                }
            } catch (QueryException $exception) {
                if (! in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                    throw $exception;
                }
                $issues[] = ['row' => $rowNumber, 'reason' => 'A customer with this normalized phone number already exists; no record was changed.'];
            }
        }

        $this->audit->execute($businessId, $userId, 'customers.imported', null, null, null, ['created' => $created, 'skipped' => count($issues)]);

        return ['created' => $created, 'skipped' => count($issues), 'issues' => array_slice($issues, 0, 100)];
    }
}
