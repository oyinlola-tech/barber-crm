<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\ServiceCatalog\Commands\CreateServiceCommand;
use App\Services\ServiceCatalog\Commands\UpdateServiceCommand;
use App\Services\ServiceCatalog\Queries\ListServicesQuery;
use App\Validators\Services\StoreServiceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function index(Request $request, ListServicesQuery $query): Response
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
            ])->values(),
            'currency' => $request->attributes->get('currentBusiness')->currency,
            'permissions' => [
                'create' => $request->user()->hasBusinessPermission($businessId, 'services.create'),
                'update' => $request->user()->hasBusinessPermission($businessId, 'services.update'),
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
}
