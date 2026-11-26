<?php

namespace App\Interfaces\Repositories\Services;

use App\Models\Service;
use Illuminate\Database\Eloquent\Collection;

interface ServiceRepositoryInterface
{
    /** @return Collection<int, Service> */
    public function activeForBusiness(int $businessId): Collection;

    /** @return Collection<int, Service> */
    public function allForBusiness(int $businessId): Collection;

    public function findForBusiness(int $businessId, int $serviceId): Service;

    public function assertCategoryForBusiness(int $businessId, int $categoryId): void;

    /** @param list<int> $serviceIds @return Collection<int, Service> */
    public function findActiveManyForBusiness(int $businessId, array $serviceIds): Collection;

    /** @param list<int> $serviceIds @return Collection<int, Service> */
    public function findManyForBusiness(int $businessId, array $serviceIds): Collection;

    /** @param array<string, mixed> $attributes */
    public function createForBusiness(int $businessId, array $attributes): Service;

    /** @param array<string, mixed> $attributes */
    public function updateForBusiness(int $businessId, int $serviceId, array $attributes): Service;
}
