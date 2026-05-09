<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesN8nRequests;
use App\Models\Address;
use App\Models\Customer;
use App\Services\DeliveryFeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class N8nCustomerController extends Controller
{
    use AuthorizesN8nRequests;

    public function index(Request $request): JsonResponse
    {
        $this->ensureAuthorized($request);

        $validated = $request->validate([
            'phone' => ['nullable', 'string', 'max:30'],
            'search' => ['nullable', 'string', 'max:255'],
            'with_addresses' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $withAddresses = (bool) ($validated['with_addresses'] ?? true);
        $perPage = (int) ($validated['per_page'] ?? 15);

        $query = Customer::query()
            ->when($withAddresses, fn ($builder) => $builder->with(['addresses' => fn ($addressQuery) => $addressQuery->orderByDesc('is_primary')->latest()]))
            ->when(!empty($validated['phone']), fn ($builder) => $builder->where('phone', $validated['phone']))
            ->when(!empty($validated['search']), function ($builder) use ($validated) {
                $search = $validated['search'];

                $builder->where(function ($customerQuery) use ($search) {
                    $customerQuery
                        ->where('name', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                });
            })
            ->latest();

        if (!empty($validated['phone'])) {
            $customer = $query->first();

            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cliente nao encontrado.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'customer' => $this->serializeCustomer($customer),
            ]);
        }

        $customers = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'customers' => collect($customers->items())->map(fn (Customer $customer) => $this->serializeCustomer($customer))->values(),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureAuthorized($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'array'],
            'address.street' => ['required_with:address', 'string', 'max:255'],
            'address.number' => ['nullable', 'string', 'max:50'],
            'address.complement' => ['nullable', 'string', 'max:255'],
            'address.neighborhood' => ['required_with:address', 'string', 'max:255'],
            'address.city' => ['required_with:address', 'string', 'max:255'],
            'address.state' => ['required_with:address', 'string', 'max:10'],
            'address.zip_code' => ['nullable', 'string', 'max:20'],
            'address.reference' => ['nullable', 'string', 'max:255'],
            'address.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'address.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'address.is_primary' => ['nullable', 'boolean'],
        ]);

        $customer = null;

        $customer = DB::transaction(function () use ($validated, &$customer) {
            $customer = Customer::updateOrCreate(
                ['phone' => $validated['phone']],
                [
                    'name' => $validated['name'],
                    'email' => $validated['email'] ?? null,
                ]
            );

            if (!empty($validated['address'])) {
                $this->createOrUpdateAddress($customer, $validated['address']);
            }

            return $customer->fresh(['addresses' => fn ($query) => $query->orderByDesc('is_primary')->latest()]);
        });

        return response()->json([
            'success' => true,
            'customer' => $this->serializeCustomer($customer),
        ], $customer->wasRecentlyCreated ? 201 : 200);
    }

    public function show(Request $request, Customer $customer): JsonResponse
    {
        $this->ensureAuthorized($request);

        $customer->load(['addresses' => fn ($query) => $query->orderByDesc('is_primary')->latest(), 'orders' => fn ($query) => $query->latest()->limit(10)]);

        return response()->json([
            'success' => true,
            'customer' => $this->serializeCustomer($customer, true),
        ]);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $this->ensureAuthorized($request);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'max:30', Rule::unique('customers', 'phone')->ignore($customer->id)],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $customer->update($validated);
        $customer->load(['addresses' => fn ($query) => $query->orderByDesc('is_primary')->latest()]);

        return response()->json([
            'success' => true,
            'customer' => $this->serializeCustomer($customer),
        ]);
    }

    public function upsertAddress(
        Request $request,
        Customer $customer,
        DeliveryFeeService $deliveryFeeService
    ): JsonResponse {
        $this->ensureAuthorized($request);

        $validated = $request->validate([
            'street' => ['required', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:50'],
            'complement' => ['nullable', 'string', 'max:255'],
            'neighborhood' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:10'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'reference' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $address = DB::transaction(function () use ($customer, $validated) {
            return $this->createOrUpdateAddress($customer, $validated);
        });

        $delivery = $deliveryFeeService->calculateForAddress($address);

        return response()->json([
            'success' => true,
            'address' => $this->serializeAddress($address->fresh()),
            'delivery' => $delivery,
        ], $address->wasRecentlyCreated ? 201 : 200);
    }

    public function listAddresses(Request $request, Customer $customer): JsonResponse
    {
        $this->ensureAuthorized($request);

        $customer->load(['addresses' => fn ($query) => $query->orderByDesc('is_primary')->latest()]);

        return response()->json([
            'success' => true,
            'customer_id' => $customer->id,
            'addresses' => $customer->addresses->map(fn (Address $address) => $this->serializeAddress($address))->values(),
            'primary_address' => ($primaryAddress = $customer->addresses->firstWhere('is_primary', true))
                ? $this->serializeAddress($primaryAddress)
                : null,
        ]);
    }

    protected function createOrUpdateAddress(Customer $customer, array $data): Address
    {
        $isPrimary = (bool) ($data['is_primary'] ?? !$customer->addresses()->exists());

        if ($isPrimary) {
            $customer->addresses()->update(['is_primary' => false]);
        }

        $address = Address::updateOrCreate(
            [
                'customer_id' => $customer->id,
                'street' => $data['street'],
                'number' => (string) ($data['number'] ?? 'S/N'),
            ],
            [
                'complement' => $data['complement'] ?? null,
                'neighborhood' => $data['neighborhood'],
                'city' => $data['city'],
                'state' => strtoupper((string) $data['state']),
                'zip_code' => $data['zip_code'] ?? null,
                'reference' => $data['reference'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'is_primary' => $isPrimary,
            ]
        );

        if (!$isPrimary && !$customer->addresses()->where('is_primary', true)->exists()) {
            $address->update(['is_primary' => true]);
        }

        return $address;
    }

    protected function serializeCustomer(Customer $customer, bool $includeOrders = false): array
    {
        $addresses = $customer->relationLoaded('addresses')
            ? $customer->addresses->map(fn (Address $address) => $this->serializeAddress($address))->values()->all()
            : [];

        $primaryAddress = $customer->relationLoaded('addresses')
            ? $customer->addresses->firstWhere('is_primary', true)
            : null;

        $payload = [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'created_at' => $customer->created_at?->toISOString(),
            'updated_at' => $customer->updated_at?->toISOString(),
            'addresses' => $addresses,
            'primary_address' => $primaryAddress ? $this->serializeAddress($primaryAddress) : null,
        ];

        if ($includeOrders && $customer->relationLoaded('orders')) {
            $payload['recent_orders'] = $customer->orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'status' => $order->status,
                    'type' => $order->type,
                    'total_amount' => (float) $order->total_amount,
                    'created_at' => $order->created_at?->toISOString(),
                ];
            })->values()->all();
        }

        return $payload;
    }

    protected function serializeAddress(Address $address): array
    {
        return [
            'id' => $address->id,
            'customer_id' => $address->customer_id,
            'street' => $address->street,
            'number' => $address->number,
            'complement' => $address->complement,
            'neighborhood' => $address->neighborhood,
            'city' => $address->city,
            'state' => $address->state,
            'zip_code' => $address->zip_code,
            'reference' => $address->reference,
            'latitude' => $address->latitude !== null ? (float) $address->latitude : null,
            'longitude' => $address->longitude !== null ? (float) $address->longitude : null,
            'is_primary' => (bool) $address->is_primary,
            'google_maps_url' => $address->googleMapsUrl(),
            'created_at' => $address->created_at?->toISOString(),
            'updated_at' => $address->updated_at?->toISOString(),
        ];
    }
}
