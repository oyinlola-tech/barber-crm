<?php

namespace App\Services\ServiceCatalog\Commands;

use App\Interfaces\Repositories\Services\ServiceRepositoryInterface;
use App\Models\Service;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class CreateServiceCommand
{
    public function __construct(public ServiceRepositoryInterface $services, public RecordAuditLogCommand $audit) {}

    /** @param array<string, mixed> $attributes */
    public function execute(int $businessId, array $attributes, int $userId): Service
    {
        if (! User::query()->findOrFail($userId)->hasBusinessPermission($businessId, 'services.create')) {
            throw new AuthorizationException('You are not allowed to create services.');
        }

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
