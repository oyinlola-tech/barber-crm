<?php

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Service;
use App\Services\Customers\Commands\AddCustomerNoteCommand;
use App\Services\Customers\Commands\AnonymizeCustomerCommand;
use App\Services\Customers\Commands\ArchiveCustomerCommand;
use App\Services\Customers\Commands\CreateCustomerCommand;
use App\Services\Customers\Commands\DeleteCustomerCommand;
use App\Services\Customers\Commands\ImportCustomersCommand;
use App\Services\Customers\Commands\RecordCustomerExportCommand;
use App\Services\Customers\Commands\RestoreCustomerCommand;
use App\Services\Customers\Commands\SyncCustomerTagsCommand;
use App\Services\Customers\Commands\UpdateCustomerCommand;
use App\Services\Customers\Queries\ExportCustomersQuery;
use App\Services\Customers\Queries\GetCustomerQuery;
use App\Services\Customers\Queries\ListCustomersQuery;
use App\Services\ServiceCatalog\Queries\ListServicesQuery;
use App\Services\Staff\Queries\ListStaffOptionsForVisitsQuery;
use App\Utils\CustomerCsvExporter;
use App\Utils\CustomerCsvImporter;
use App\Validators\Customers\ImportCustomersRequest;
use App\Validators\Customers\StoreCustomerNoteRequest;
use App\Validators\Customers\StoreCustomerRequest;
use App\Validators\Customers\SyncCustomerTagsRequest;
use App\Validators\Customers\UpdateCustomerRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function index(Request $request, ListCustomersQuery $query): Response
    {
        $this->authorize('viewAny', Customer::class);
        $business = $request->attributes->get('currentBusiness');
        $status = $request->query('status') === 'archived' ? 'archived' : 'active';

        return Inertia::render('customers/index', [
            'customers' => $query->execute($business->getKey(), $request->string('search')->toString(), status: $status),
            'filters' => ['search' => $request->string('search')->toString(), 'status' => $status],
            'canCreate' => $request->user()->can('create', Customer::class),
            'canAddNotes' => $request->user()->hasBusinessPermission((int) $business->getKey(), 'customers.note'),
            'canExport' => $request->user()->hasBusinessPermission((int) $business->getKey(), 'customers.export'),
            'canImport' => $request->user()->hasBusinessPermission((int) $business->getKey(), 'customers.import'),
            'importResult' => $request->session()->get('importResult'),
        ]);
    }

    public function export(Request $request, ExportCustomersQuery $query, CustomerCsvExporter $exporter, RecordCustomerExportCommand $audit): StreamedResponse
    {
        $businessId = (int) $request->attributes->get('currentBusiness')->getKey();
        abort_unless($request->user()->hasBusinessPermission($businessId, 'customers.export'), 403);
        $audit->execute($businessId, $request->user()->getKey());

        return response()->streamDownload(function () use ($businessId, $query, $exporter): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                return;
            }

            try {
                $exporter->write($stream, $query->execute($businessId));
            } finally {
                fclose($stream);
            }
        }, 'customers-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function import(ImportCustomersRequest $request, CustomerCsvImporter $csv, ImportCustomersCommand $command): RedirectResponse
    {
        $businessId = (int) $request->attributes->get('currentBusiness')->getKey();
        $this->authorize('viewAny', Customer::class);
        $rows = $csv->parse($request->file('file'));
        $result = $command->execute(
            $businessId,
            (int) $request->user()->getKey(),
            $rows,
        );

        return to_route('customers.index')->with('importResult', $result);
    }

    public function show(Request $request, int $customer, GetCustomerQuery $query, ListServicesQuery $services, ListStaffOptionsForVisitsQuery $staff): Response
    {
        $business = $request->attributes->get('currentBusiness');
        $record = $query->execute($business->getKey(), $customer);
        $this->authorize('view', $record);
        $user = $request->user();
        $businessId = (int) $business->getKey();
        $canViewConsent = $user->hasBusinessPermission($businessId, 'consent.view');
        $canViewVisits = $user->hasBusinessPermission($businessId, 'visits.view');
        $canRecordVisit = $record->status === 'active'
            && $user->hasBusinessPermission($businessId, 'visits.create');
        $canAddNote = $user->can('addNote', $record);
        $canManageTags = $user->can('manageTags', $record);

        if (! $canViewConsent) {
            $record->setRelation('consents', collect());
        }
        if (! $canViewVisits) {
            $record->setRelation('visits', collect());
        }
        if (! $canAddNote) {
            $record->setRelation('noteEntries', collect());
            $record->setAttribute('notes', null);
        }
        if (! $canManageTags) {
            $record->setRelation('tags', collect());
        }

        $serviceOptions = [];
        $staffOptions = [];
        if ($canRecordVisit) {
            $serviceOptions = $services->execute($businessId)
                ->map(fn (Service $service): array => $service->only(['id', 'name']))
                ->values()
                ->all();
            $staffOptions = $staff->execute($businessId);
        }

        return Inertia::render('customers/show', [
            'customer' => $record,
            'businessTimezone' => $business->timezone,
            'services' => $serviceOptions,
            'staff' => $staffOptions,
            'permissions' => [
                'update' => $user->can('update', $record),
                'archive' => $user->can('delete', $record),
                'restore_customer' => $user->can('restore', $record),
                'edit_notes' => $canAddNote,
                'record_consent' => $user->hasBusinessPermission($businessId, 'consent.record'),
                'revoke_consent' => $user->hasBusinessPermission($businessId, 'consent.revoke'),
                'view_consent' => $canViewConsent,
                'record_visit' => $canRecordVisit,
                'update_return_status' => $user->hasBusinessPermission($businessId, 'visits.update'),
                'view_visits' => $canViewVisits,
                'update_visit' => $user->hasBusinessPermission($businessId, 'visits.update'),
                'void_visit' => $user->hasBusinessPermission($businessId, 'visits.delete'),
                'restore_visit' => $user->hasBusinessPermission($businessId, 'visits.restore'),
                'add_note' => $canAddNote,
                'manage_tags' => $canManageTags,
                'anonymize' => $user->can('anonymize', $record),
                'permanently_delete' => $user->can('forceDelete', $record),
            ],
        ]);
    }

    public function store(StoreCustomerRequest $request, CreateCustomerCommand $command): RedirectResponse
    {
        $this->authorize('create', Customer::class);
        $business = $request->attributes->get('currentBusiness');
        $customer = $command->execute($business->getKey(), $request->user()->getKey(), $request->toDto());

        return to_route('customers.show', $customer->getKey())->with('success', 'Customer added.');
    }

    public function update(UpdateCustomerRequest $request, Customer $customer, UpdateCustomerCommand $command): RedirectResponse
    {
        $business = $request->attributes->get('currentBusiness');
        abort_unless((int) $customer->business_id === (int) $business->getKey(), 404);
        $this->authorize('update', $customer);
        $record = $command->execute($business->getKey(), $customer->getKey(), $request->user()->getKey(), $request->validated());

        return to_route('customers.show', $record->getKey())->with('success', 'Customer updated.');
    }

    public function destroy(Request $request, Customer $customer, ArchiveCustomerCommand $command): RedirectResponse
    {
        $businessId = (int) $request->attributes->get('currentBusiness')->getKey();
        abort_unless((int) $customer->business_id === $businessId, 404);
        $this->authorize('delete', $customer);
        $command->execute($businessId, $customer->getKey(), $request->user()->getKey());

        return to_route('customers.index')->with('success', 'Customer archived.');
    }

    public function restore(Request $request, Customer $customer, RestoreCustomerCommand $command): RedirectResponse
    {
        $businessId = (int) $request->attributes->get('currentBusiness')->getKey();
        abort_unless((int) $customer->business_id === $businessId, 404);
        $this->authorize('restore', $customer);
        $command->execute($businessId, $customer->getKey(), $request->user()->getKey());

        return to_route('customers.show', $customer->getKey())->with('success', 'Customer restored.');
    }

    public function anonymize(Request $request, Customer $customer, AnonymizeCustomerCommand $command): RedirectResponse
    {
        $businessId = (int) $request->attributes->get('currentBusiness')->getKey();
        abort_unless((int) $customer->business_id === $businessId, 404);
        $this->authorize('anonymize', $customer);
        $command->execute($businessId, (int) $customer->getKey(), (int) $request->user()->getKey());

        return to_route('customers.index')->with('success', 'Customer personal data anonymized. Retained operational history remains linked to the anonymized record.');
    }

    public function permanentlyDelete(Request $request, Customer $customer, DeleteCustomerCommand $command): RedirectResponse
    {
        $businessId = (int) $request->attributes->get('currentBusiness')->getKey();
        abort_unless((int) $customer->business_id === $businessId, 404);
        $this->authorize('forceDelete', $customer);
        $command->execute($businessId, (int) $customer->getKey(), (int) $request->user()->getKey());

        return to_route('customers.index')->with('success', 'Customer record permanently deleted.');
    }

    public function addNote(StoreCustomerNoteRequest $request, Customer $customer, AddCustomerNoteCommand $command): RedirectResponse
    {
        $businessId = (int) $request->attributes->get('currentBusiness')->getKey();
        abort_unless((int) $customer->business_id === $businessId, 404);

        $command->execute($businessId, $customer->getKey(), $request->user()->getKey(), $request->validated('body'));

        return to_route('customers.show', $customer->getKey())->with('success', 'Customer note added.');
    }

    public function syncTags(SyncCustomerTagsRequest $request, Customer $customer, SyncCustomerTagsCommand $command): RedirectResponse
    {
        $businessId = (int) $request->attributes->get('currentBusiness')->getKey();
        abort_unless((int) $customer->business_id === $businessId, 404);

        $command->execute($businessId, $customer->getKey(), $request->user()->getKey(), $request->validated('tags'));

        return to_route('customers.show', $customer->getKey())->with('success', 'Customer tags updated.');
    }
}
