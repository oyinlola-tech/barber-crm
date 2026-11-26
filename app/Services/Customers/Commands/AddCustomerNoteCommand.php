<?php

namespace App\Services\Customers\Commands;

use App\Interfaces\Repositories\Customers\CustomerRepositoryInterface;
use App\Models\CustomerNote;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Support\Facades\DB;

class AddCustomerNoteCommand
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customers,
        private readonly RecordAuditLogCommand $audit,
    ) {}

    public function execute(int $businessId, int $customerId, int $userId, string $body): CustomerNote
    {
        return DB::transaction(function () use ($businessId, $customerId, $userId, $body): CustomerNote {
            $note = $this->customers->addNoteForBusiness($businessId, $customerId, $userId, $body);
            $this->audit->execute($businessId, $userId, 'customer.note_added', CustomerNote::class, $note->getKey());

            return $note;
        });
    }
}
