<?php

namespace App\Repositories\Visits;

use App\Interfaces\Repositories\Visits\VisitRepositoryInterface;
use App\Models\Service;
use App\Models\Visit;
use App\Models\VisitService;

class VisitRepository implements VisitRepositoryInterface
{
    public function findForBusiness(int $businessId, int $visitId): Visit
    {
        return Visit::query()->where('business_id', $businessId)->findOrFail($visitId);
    }

    public function lockForBusiness(int $businessId, int $visitId): Visit
    {
        return Visit::query()->where('business_id', $businessId)->lockForUpdate()->findOrFail($visitId);
    }

    public function createForBusiness(int $businessId, int $userId, array $attributes, iterable $services): Visit
    {
        $visit = Visit::query()->create([
            ...$attributes,
            'business_id' => $businessId,
            'recorded_by' => $userId,
        ]);
        foreach ($services as $service) {
            if (! $service instanceof Service) {
                continue;
            }
            VisitService::query()->create([
                'business_id' => $businessId,
                'visit_id' => $visit->getKey(),
                'service_id' => $service->getKey(),
                'service_name' => $service->name,
                'duration_minutes' => $service->duration_minutes,
                'price' => $service->price,
            ]);
        }

        return $visit->load('customer', 'staff', 'services');
    }

    public function updateForBusiness(int $businessId, int $visitId, array $attributes, iterable $services): Visit
    {
        $visit = Visit::query()->where('business_id', $businessId)->lockForUpdate()->findOrFail($visitId);
        $visit->fill($attributes)->save();
        $visit->increment('automation_revision');
        $visit->refresh();
        $visit->services()->delete();

        foreach ($services as $service) {
            if (! $service instanceof Service) {
                continue;
            }

            VisitService::query()->create([
                'business_id' => $businessId,
                'visit_id' => $visit->getKey(),
                'service_id' => $service->getKey(),
                'service_name' => $service->name,
                'duration_minutes' => $service->duration_minutes,
                'price' => $service->price,
            ]);
        }

        return $visit->load('customer', 'staff', 'services');
    }

    public function voidForBusiness(int $businessId, int $visitId): Visit
    {
        $visit = Visit::query()->where('business_id', $businessId)->lockForUpdate()->findOrFail($visitId);
        $visit->delete();

        return $visit;
    }

    public function restoreForBusiness(int $businessId, int $visitId): Visit
    {
        $visit = Visit::withTrashed()->where('business_id', $businessId)->lockForUpdate()->findOrFail($visitId);
        $visit->restore();
        $visit->increment('automation_revision');

        return $visit->refresh();
    }
}
