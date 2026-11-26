<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\ServiceCatalog\Commands\CreateServiceCommand;
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

        return Inertia::render('services/index', [
            'services' => $query->execute($request->attributes->get('currentBusiness')->getKey()),
        ]);
    }

    public function store(StoreServiceRequest $request, CreateServiceCommand $command): RedirectResponse
    {
        $this->authorize('create', Service::class);
        $service = $command->execute($request->attributes->get('currentBusiness')->getKey(), $request->validated(), $request->user()->getKey());

        return to_route('services.index')->with('success', $service->name.' added.');
    }
}
