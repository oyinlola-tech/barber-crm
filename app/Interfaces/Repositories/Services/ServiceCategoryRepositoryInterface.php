<?php

namespace App\Interfaces\Repositories\Services;

use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Collection;

interface ServiceCategoryRepositoryInterface
{
    /** @return Collection<int, ServiceCategory> */
    public function allForBusiness(int $businessId): Collection;

    public function findForBusiness(int $businessId, int $categoryId, bool $lock = false): ServiceCategory;

    /** @param array{name: string, description?: string|null, sort_order?: int} $attributes */
    public function createForBusiness(int $businessId, array $attributes): ServiceCategory;

    /** @param array{name: string, description?: string|null, sort_order?: int} $attributes */
    public function updateForBusiness(int $businessId, int $categoryId, array $attributes): ServiceCategory;

    public function deleteForBusiness(int $businessId, int $categoryId): void;
}
