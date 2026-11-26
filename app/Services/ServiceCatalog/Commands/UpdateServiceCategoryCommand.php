<?php

namespace App\Services\ServiceCatalog\Commands;

use App\Interfaces\Repositories\Services\ServiceCategoryRepositoryInterface;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class UpdateServiceCategoryCommand
{
    public function __construct(
        public ServiceCategoryRepositoryInterface $categories,
        public RecordAuditLogCommand $audit,
    ) {}

    /** @param array{name: string, description?: string|null, sort_order?: int} $attributes */
    public function execute(int $businessId, int $categoryId, array $attributes, int $userId): ServiceCategory
    {
        if (! User::query()->findOrFail($userId)->hasBusinessPermission($businessId, 'services.categories.update')) {
            throw new AuthorizationException('You are not allowed to update service categories.');
        }

        return DB::transaction(function () use ($businessId, $categoryId, $attributes, $userId): ServiceCategory {
            $category = $this->categories->findForBusiness($businessId, $categoryId, true);
            $before = $this->auditAttributes($category);
            $updated = $this->categories->updateForBusiness($businessId, $categoryId, $attributes);
            $this->audit->execute($businessId, $userId, 'service_category.updated', ServiceCategory::class, $categoryId, $before, $this->auditAttributes($updated));

            return $updated;
        });
    }

    /** @return array{name: string, description: ?string, sort_order: int} */
    private function auditAttributes(ServiceCategory $category): array
    {
        return ['name' => $category->name, 'description' => $category->description, 'sort_order' => $category->sort_order];
    }
}
