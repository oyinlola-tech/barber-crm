<?php

namespace App\Services\ServiceCatalog\Queries;

use App\Interfaces\Repositories\Services\ServiceRepositoryInterface;
use App\Models\Service;
use Illuminate\Database\Eloquent\Collection;

class ListServicesQuery
{
    public function __construct(public ServiceRepositoryInterface $services) {}

    /** @return Collection<int, Service> */
    public function execute(int $businessId): Collection
    {
        return $this->services->activeForBusiness($businessId);
    }

    /** @return Collection<int, Service> */
    public function catalog(int $businessId): Collection
    {
        return $this->services->allForBusiness($businessId);
    }
}
