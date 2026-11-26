<?php

namespace App\Services\ServiceCatalog\Queries;

use App\Interfaces\Repositories\Services\ServiceCategoryRepositoryInterface;
use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Collection;

class ListServiceCategoriesQuery
{
    public function __construct(public ServiceCategoryRepositoryInterface $categories) {}

    /** @return Collection<int, ServiceCategory> */
    public function execute(int $businessId): Collection
    {
        return $this->categories->allForBusiness($businessId);
    }
}
