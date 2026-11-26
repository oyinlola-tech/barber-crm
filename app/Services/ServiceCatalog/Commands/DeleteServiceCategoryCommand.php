<?php

namespace App\Services\ServiceCatalog\Commands;

use App\Interfaces\Repositories\Services\ServiceCategoryRepositoryInterface;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteServiceCategoryCommand
{
    public function __construct(
        public ServiceCategoryRepositoryInterface $categories,
        public RecordAuditLogCommand $audit,
    ) {}

    public function execute(int $businessId, int $categoryId, int $userId): void
    {
        if (! User::query()->findOrFail($userId)->hasBusinessPermission($businessId, 'services.categories.delete')) {
            throw new AuthorizationException('You are not allowed to delete service categories.');
        }

        DB::transaction(function () use ($businessId, $categoryId, $userId): void {
            $category = $this->categories->findForBusiness($businessId, $categoryId, true);
            if ($category->services()->exists()) {
                throw ValidationException::withMessages(['category' => 'Reassign or remove this category from its services before deleting it.']);
            }
            $before = ['name' => $category->name, 'description' => $category->description, 'sort_order' => $category->sort_order];
            $this->categories->deleteForBusiness($businessId, $categoryId);
            $this->audit->execute($businessId, $userId, 'service_category.deleted', ServiceCategory::class, $categoryId, $before, null);
        });
    }
}
