<?php

namespace App\Repositories\Services;

use App\Interfaces\Repositories\Services\ServiceCategoryRepositoryInterface;
use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Collection;

class ServiceCategoryRepository implements ServiceCategoryRepositoryInterface
{
    public function allForBusiness(int $businessId): Collection
    {
        return ServiceCategory::query()->where('business_id', $businessId)
            ->withCount('services')->orderBy('sort_order')->orderBy('name')->get();
    }

    public function findForBusiness(int $businessId, int $categoryId, bool $lock = false): ServiceCategory
    {
        $query = ServiceCategory::query()->where('business_id', $businessId);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->findOrFail($categoryId);
    }

    public function createForBusiness(int $businessId, array $attributes): ServiceCategory
    {
        return ServiceCategory::query()->create([...$attributes, 'business_id' => $businessId]);
    }

    public function updateForBusiness(int $businessId, int $categoryId, array $attributes): ServiceCategory
    {
        $category = $this->findForBusiness($businessId, $categoryId, true);
        $category->update($attributes);

        return $category->refresh();
    }

    public function deleteForBusiness(int $businessId, int $categoryId): void
    {
        $this->findForBusiness($businessId, $categoryId, true)->delete();
    }
}
