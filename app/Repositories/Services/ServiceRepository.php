<?php

namespace App\Repositories\Services;

use App\Interfaces\Repositories\Services\ServiceRepositoryInterface;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Collection;

class ServiceRepository implements ServiceRepositoryInterface
{
    public function activeForBusiness(int $businessId): Collection
    {
        return Service::query()->where('business_id', $businessId)->where('is_active', true)->orderBy('name')->get();
    }

    public function findForBusiness(int $businessId, int $serviceId): Service
    {
        return Service::query()->where('business_id', $businessId)->findOrFail($serviceId);
    }

    public function assertCategoryForBusiness(int $businessId, int $categoryId): void
    {
        ServiceCategory::query()->where('business_id', $businessId)->findOrFail($categoryId);
    }

    public function findActiveManyForBusiness(int $businessId, array $serviceIds): Collection
    {
        return Service::query()->where('business_id', $businessId)
            ->where('is_active', true)
            ->whereIn('id', $serviceIds)
            ->get();
    }

    public function findManyForBusiness(int $businessId, array $serviceIds): Collection
    {
        return Service::query()->where('business_id', $businessId)->whereIn('id', $serviceIds)->get();
    }

    public function createForBusiness(int $businessId, array $attributes): Service
    {
        return Service::query()->create([...$attributes, 'business_id' => $businessId]);
    }
}
