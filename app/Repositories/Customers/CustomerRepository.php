<?php

namespace App\Repositories\Customers;

use App\Interfaces\Repositories\Customers\CustomerRepositoryInterface;
use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\CustomerTag;
use App\Models\Visit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class CustomerRepository implements CustomerRepositoryInterface
{
    public function paginateForBusiness(int $businessId, ?string $search = null, int $perPage = 20, string $status = 'active'): LengthAwarePaginator
    {
        return Customer::query()
            ->where('business_id', $businessId)
            ->where('status', $status)
            ->when($search !== null && $search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->select(['id', 'business_id', 'first_name', 'last_name', 'phone', 'email', 'status', 'created_at'])
            ->withCount(['visits'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findForBusiness(int $businessId, int $customerId): Customer
    {
        $customer = Customer::query()
            ->where('business_id', $businessId)
            ->with([
                'noteEntries' => fn ($query) => $query->with('author:id,name')->latest(),
                'consents',
                'visits.services',
                'visits.staff',
                'tags',
            ])
            ->findOrFail($customerId);

        $voidedVisits = Visit::withTrashed()
            ->where('business_id', $businessId)
            ->where('customer_id', $customerId)
            ->whereNotNull('deleted_at')
            ->with(['services', 'staff'])
            ->get();

        $customer->setRelation(
            'visits',
            $customer->visits->concat($voidedVisits)->sortByDesc('visited_at')->values(),
        );

        return $customer;
    }

    public function createForBusiness(int $businessId, int $userId, array $attributes): Customer
    {
        return Customer::query()->create([
            ...$attributes,
            'business_id' => $businessId,
            'created_by' => $userId,
        ]);
    }

    public function updateForBusiness(int $businessId, int $customerId, array $attributes): Customer
    {
        $customer = $this->findForBusiness($businessId, $customerId);
        $customer->fill($attributes)->save();

        return $customer->refresh();
    }

    public function addNoteForBusiness(int $businessId, int $customerId, int $userId, string $body): CustomerNote
    {
        $customer = Customer::query()->where('business_id', $businessId)->findOrFail($customerId);
        if ($customer->status !== 'active') {
            throw ValidationException::withMessages(['customer' => 'Notes cannot be added to an archived or anonymized customer.']);
        }

        return $customer->noteEntries()->create([
            'business_id' => $businessId,
            'created_by' => $userId,
            'body' => $body,
        ]);
    }

    /** @param array<int, string> $names */
    public function syncTagsForBusiness(int $businessId, int $customerId, array $names): Customer
    {
        $customer = Customer::query()->where('business_id', $businessId)->findOrFail($customerId);
        if ($customer->status !== 'active') {
            throw ValidationException::withMessages(['customer' => 'Tags cannot be changed for an archived or anonymized customer.']);
        }
        $tagIds = [];

        foreach ($names as $name) {
            $tag = CustomerTag::query()->firstOrCreate([
                'business_id' => $businessId,
                'name' => mb_strtolower(trim($name)),
            ]);
            $tagIds[$tag->getKey()] = ['business_id' => $businessId];
        }

        $customer->tags()->sync($tagIds);

        return $customer->load('tags');
    }
}
