<?php

namespace App\Interfaces\Repositories\Visits;

use App\Models\Service;
use App\Models\Visit;

interface VisitRepositoryInterface
{
    public function findForBusiness(int $businessId, int $visitId): Visit;

    public function lockForBusiness(int $businessId, int $visitId): Visit;

    /** @param array<string, mixed> $attributes @param iterable<Service> $services */
    public function createForBusiness(int $businessId, int $userId, array $attributes, iterable $services): Visit;

    /** @param array<string, mixed> $attributes @param iterable<Service> $services */
    public function updateForBusiness(int $businessId, int $visitId, array $attributes, iterable $services): Visit;

    public function voidForBusiness(int $businessId, int $visitId): Visit;

    public function restoreForBusiness(int $businessId, int $visitId): Visit;
}
