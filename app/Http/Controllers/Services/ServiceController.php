<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Services\ServiceCatalog\Commands\CreateServiceCategoryCommand;
use App\Services\ServiceCatalog\Commands\CreateServiceCommand;
use App\Services\ServiceCatalog\Commands\DeleteServiceCategoryCommand;
use App\Services\ServiceCatalog\Commands\UpdateServiceCategoryCommand;
use App\Services\ServiceCatalog\Commands\UpdateServiceCommand;
use App\Services\ServiceCatalog\Queries\ListServiceCategoriesQuery;
use App\Services\ServiceCatalog\Queries\ListServicesQuery;
use App\Validators\Services\StoreServiceCategoryRequest;
use App\Validators\Services\StoreServiceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function index(Request $request, ListServicesQuery $query, ListServiceCategoriesQuery $categories): Response
    {
        $this->authorize('viewAny', Service::class);
        $businessId = (int) $request->attributes->get('currentBusiness')->getKey();

        return Inertia::render('services/index', [
            'services' => $query->catalog($businessId)->map(static fn (Service $service): array => [
                'id' => (int) $service->getKey(),
                'name' => $service->name,
                'description' => $service->description,
                'duration_minutes' => $service->duration_minutes,
                'price' => $service->price,
                'is_active' => $service->is_active,
                'category_id' => $service->service_category_id,
                'category_name' => $service->category?->name,
            ])->values(),
            'categories' => $categories->execute($businessId)->map(static fn (ServiceCategory $category): array => [
                'id' => (int) $category->getKey(),
                'name' => $category->name,
                'description' => $category->description,
                'sort_order' => $category->sort_order,
                'services_count' => $category->services_count,
            ])->values(),
            'currency' => $request->attributes->get('currentBusiness')->currency,
            'permissions' => [
                'create' => $request->user()->hasBusinessPermission($businessId, 'services.create'),
                'update' => $request->user()->hasBusinessPermission($businessId, 'services.update'),
                'createCategory' => $request->user()->hasBusinessPermission($businessId, 'services.categories.create'),
                'updateCategory' => $request->user()->hasBusinessPermission($businessId, 'services.categories.update'),
                'deleteCategory' => $request->user()->hasBusinessPermission($businessId, 'services.categories.delete'),
            ],
        ]);
    }

    public function store(StoreServiceRequest $request, CreateServiceCommand $command): RedirectResponse
    {
        $this->authorize('create', Service::class);
        $service = $command->execute($request->attributes->get('currentBusiness')->getKey(), $request->validated(), $request->user()->getKey());

        return to_route('services.index')->with('success', $service->name.' added.');
    }

    public function update(StoreServiceRequest $request, int $service, UpdateServiceCommand $command): RedirectResponse
    {
        $businessId = (int) $request->attributes->get('currentBusiness')->getKey();
        $record = Service::query()->where('business_id', $businessId)->findOrFail($service);
        $this->authorize('update', $record);
        $updated = $command->execute($businessId, $service, $request->validated(), (int) $request->user()->getKey());

        return to_route('services.index')->with('success', $updated->name.' updated.');
    }

    public function storeCategory(StoreServiceCategoryRequest $request, CreateServiceCategoryCommand $command): RedirectResponse
    {
        $this->authorize('create', ServiceCategory::class);
        $category = $command->execute(
            (int) $request->attributes->get('currentBusiness')->getKey(),
            $request->validated(),
            (int) $request->user()->getKey(),
        );

        return to_route('services.index')->with('success', $category->name.' category created.');
    }

    public function updateCategory(StoreServiceCategoryRequest $request, int $category, UpdateServiceCategoryCommand $command): RedirectResponse
    {
        $businessId = (int) $request->attributes->get('currentBusiness')->getKey();
        $record = ServiceCategory::query()->where('business_id', $businessId)->findOrFail($category);
        $this->authorize('update', $record);
        $updated = $command->execute($businessId, $category, $request->validated(), (int) $request->user()->getKey());

        return to_route('services.index')->with('success', $updated->name.' category updated.');
    }

    public function destroyCategory(Request $request, int $category, DeleteServiceCategoryCommand $command): RedirectResponse
    {
        $businessId = (int) $request->attributes->get('currentBusiness')->getKey();
        $record = ServiceCategory::query()->where('business_id', $businessId)->findOrFail($category);
        $this->authorize('delete', $record);
        $command->execute($businessId, $category, (int) $request->user()->getKey());

        return to_route('services.index')->with('success', 'Service category deleted.');
    }
}
