<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Customers\CreateCustomer;
use App\Actions\Customers\DeleteCustomer;
use App\Actions\Customers\UpdateCustomer;
use App\Contracts\NetworkController;
use App\Enums\CustomerStatus;
use App\Exceptions\RouterCommandException;
use App\Exceptions\RouterUnreachableException;
use App\Http\Requests\Customers\CustomerIndexRequest;
use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Http\Requests\Customers\UpdateCustomerRequest;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\MessageLogResource;
use App\Http\Resources\PaymentResource;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Router;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    /** Riwayat di halaman detail; daftar lengkap lewat halaman tagihan/pembayaran yang difilter. */
    private const int HISTORY_LIMIT = 24;

    public function index(CustomerIndexRequest $request): Response
    {
        $customers = Customer::query()
            ->with(['router', 'activeSubscription.package'])
            ->applyFilters($request->filters())
            ->latest('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return Inertia::render('customers/index', [
            'customers' => CustomerResource::collection($customers),
            'filters' => $request->validated(),
            'statuses' => $this->statusOptions(),
            'packages' => $this->packageOptions(activeOnly: false),
            'routers' => $this->routerOptions(activeOnly: false),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Customer::class);

        return Inertia::render('customers/create', [
            'packages' => $this->packageOptions(),
            'routers' => $this->routerOptions(),
        ]);
    }

    public function store(StoreCustomerRequest $request, CreateCustomer $createCustomer): RedirectResponse
    {
        $customer = $createCustomer->handle($request->customerData(), $request->user());
        $this->toast("Pelanggan {$customer->name} ({$customer->code}) didaftarkan.");

        return to_route('customers.show', $customer);
    }

    public function show(Request $request, Customer $customer): Response
    {
        Gate::authorize('view', $customer);
        $customer->load(['router', 'activeSubscription.package', 'activeSubscription.nextPackage']);
        $user = $request->user();

        return Inertia::render('customers/show', [
            'customer' => CustomerResource::make($customer),
            'invoices' => $user?->can('viewAny', Invoice::class)
                ? InvoiceResource::collection($customer->invoices()->latest('period_start')->latest('id')->limit(self::HISTORY_LIMIT)->get())
                : null,
            'payments' => $user?->can('viewAny', Payment::class)
                ? PaymentResource::collection(Payment::query()
                    ->whereHas('invoice', fn (Builder $query) => $query->where('customer_id', $customer->id))
                    ->with(['invoice', 'receivedBy'])->latest('paid_at')->limit(self::HISTORY_LIMIT)->get())
                : null,
            'messages' => $user?->can('viewAny', Invoice::class)
                ? MessageLogResource::collection($customer->messageLogs()->latest('id')->limit(self::HISTORY_LIMIT)->get())
                : null,
            'activities' => ActivityLogResource::collection($customer->activityLogs()->with('user')->latest('id')->limit(self::HISTORY_LIMIT)->get()),
            'packages' => $user?->can('update', $customer) ? $this->packageOptions() : null,
            'connection' => Inertia::defer(fn (): array => $this->connectionStatus($customer)),
        ]);
    }

    public function edit(Customer $customer): Response
    {
        Gate::authorize('update', $customer);
        $customer->load(['router', 'activeSubscription.package', 'activeSubscription.nextPackage']);

        return Inertia::render('customers/edit', [
            'customer' => CustomerResource::make($customer),
            'routers' => $this->routerOptions(includeId: $customer->router_id),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer, UpdateCustomer $updateCustomer): RedirectResponse
    {
        $updateCustomer->handle($customer, $request->customerData(), $request->user());
        $this->toast("Data pelanggan {$customer->name} diperbarui.");

        return to_route('customers.show', $customer);
    }

    public function destroy(Request $request, Customer $customer, DeleteCustomer $deleteCustomer): RedirectResponse
    {
        Gate::authorize('delete', $customer);
        $deleteCustomer->handle($customer, $request->user());
        $this->toast("Pelanggan {$customer->name} dihapus.");

        return to_route('customers.index');
    }

    /**
     * Status sesi PPPoE langsung dari router (deferred prop agar halaman tidak menunggu router).
     * Pelanggan berhenti tidak dicek karena secret-nya dinonaktifkan.
     *
     * @return array{online: bool|null, error: string|null}
     */
    private function connectionStatus(Customer $customer): array
    {
        if ($customer->status === CustomerStatus::Terminated) {
            return ['online' => false, 'error' => null];
        }

        try {
            return ['online' => app(NetworkController::class)->isOnline($customer), 'error' => null];
        } catch (RouterUnreachableException|RouterCommandException) {
            return ['online' => null, 'error' => 'Router tidak dapat dihubungi.'];
        }
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(fn (CustomerStatus $status): array => ['value' => $status->value, 'label' => $status->label()], CustomerStatus::cases());
    }

    /**
     * Paket nonaktif tidak bisa dipilih untuk pelanggan baru atau ganti paket (M2).
     *
     * @return list<array{id: int, name: string, speed_label: string, price: int}>
     */
    private function packageOptions(bool $activeOnly = true): array
    {
        return array_values(Package::query()
            ->when($activeOnly, fn (Builder $query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get(['id', 'name', 'speed_label', 'price'])
            ->map(fn (Package $package): array => ['id' => $package->id, 'name' => $package->name, 'speed_label' => $package->speed_label, 'price' => $package->price])
            ->all());
    }

    /**
     * Router nonaktif tidak bisa dipilih, kecuali router yang sedang dipakai pelanggan ($includeId).
     *
     * @return list<array{id: int, name: string}>
     */
    private function routerOptions(bool $activeOnly = true, ?int $includeId = null): array
    {
        return array_values(Router::query()
            ->when($activeOnly, fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('is_active', true)->orWhere('id', $includeId)))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Router $router): array => ['id' => $router->id, 'name' => $router->name])
            ->all());
    }
}
