<?php

namespace App\Services\ServiceCatalog\Commands;

use App\Interfaces\Repositories\Services\ServiceRepositoryInterface;
use App\Models\Service;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class UpdateServiceCommand
{
    public function __construct(
        public ServiceRepositoryInterface $services,
        public RecordAuditLogCommand $audit,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function execute(int $businessId, int $serviceId, array $attributes, int $userId): Service
    {
        return DB::transaction(function () use ($businessId, $serviceId, $attributes, $userId): Service {
            if (! User::query()->findOrFail($userId)->hasBusinessPermission($businessId, 'services.update')) {
                throw new AuthorizationException('You are not allowed to update services.');
            }

            if (isset($attributes['service_category_id'])) {
                $this->services->assertCategoryForBusiness($businessId, (int) $attributes['service_category_id']);
            }

            $service = $this->services->findForBusiness($businessId, $serviceId);
            $before = $this->auditAttributes($service);
            $updated = $this->services->updateForBusiness($businessId, $serviceId, $attributes);
            $this->audit->execute(
                $businessId,
                $userId,
                'service.updated',
                Service::class,
                $updated->getKey(),
                $before,
                $this->auditAttributes($updated),
            );

            return $updated;
        });
    }

    /** @return array{name: string, duration_minutes: int, price: string, is_active: bool} */
    private function auditAttributes(Service $service): array
    {
        return [
            'name' => $service->name,
            'duration_minutes' => $service->duration_minutes,
            'price' => (string) $service->price,
            'is_active' => $service->is_active,
        ];
    }
}
