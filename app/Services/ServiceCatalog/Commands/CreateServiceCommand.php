<?php

namespace App\Services\ServiceCatalog\Commands;

use App\Interfaces\Repositories\Services\ServiceRepositoryInterface;
use App\Models\Service;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Support\Facades\DB;

class CreateServiceCommand
{
    public function __construct(public ServiceRepositoryInterface $services, public RecordAuditLogCommand $audit) {}

    /** @param array<string, mixed> $attributes */
    public function execute(int $businessId, array $attributes, ?int $userId = null): Service
    {
        return DB::transaction(function () use ($businessId, $attributes, $userId): Service {
            if (isset($attributes['service_category_id'])) {
                $this->services->assertCategoryForBusiness($businessId, (int) $attributes['service_category_id']);
            }
            $service = $this->services->createForBusiness($businessId, $attributes);
            $this->audit->execute($businessId, $userId, 'service.created', Service::class, $service->getKey(), null, ['name' => $service->name, 'duration_minutes' => $service->duration_minutes]);

            return $service;
        });
    }
}
