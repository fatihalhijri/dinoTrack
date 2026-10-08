<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Customers\ActivateNewCustomer;
use App\Actions\Customers\ChangeCustomerPackage;
use App\Actions\Customers\ReactivateCustomer;
use App\Actions\Customers\TerminateCustomer;
use App\Http\Requests\Customers\ActivateCustomerRequest;
use App\Http\Requests\Customers\ChangeCustomerPackageRequest;
use App\Http\Requests\Customers\ReactivateCustomerRequest;
use App\Http\Requests\Customers\TerminateCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;

/**
 * Perubahan status langganan pelanggan: terpasang, berhenti, berlangganan lagi, dan ganti paket.
 */
class CustomerLifecycleController extends Controller
{
    public function activate(ActivateCustomerRequest $request, Customer $customer, ActivateNewCustomer $activateNewCustomer): RedirectResponse
    {
        $activateNewCustomer->handle($customer, $request->installedAt(), $request->user());
        $this->toast("Pelanggan {$customer->name} ditandai terpasang dan tagihan pertama diterbitkan.");

        return to_route('customers.show', $customer);
    }

    public function terminate(TerminateCustomerRequest $request, Customer $customer, TerminateCustomer $terminateCustomer): RedirectResponse
    {
        $reason = $request->filled('reason') ? $request->string('reason')->toString() : null;
        $terminateCustomer->handle($customer, $request->user(), $reason);
        $this->toast("Pelanggan {$customer->name} diberhentikan.");

        return to_route('customers.show', $customer);
    }

    public function reactivate(ReactivateCustomerRequest $request, Customer $customer, ReactivateCustomer $reactivateCustomer): RedirectResponse
    {
        $reactivateCustomer->handle($customer, $request->packageId(), $request->billingDay(), $request->user());
        $this->toast("Pelanggan {$customer->name} didaftarkan kembali dan menunggu pemasangan.");

        return to_route('customers.show', $customer);
    }

    public function changePackage(ChangeCustomerPackageRequest $request, Customer $customer, ChangeCustomerPackage $changePackage): RedirectResponse
    {
        $changePackage->handle($customer, $request->packageId(), $request->user());
        $this->toast($request->packageId() === null ? 'Rencana ganti paket dibatalkan.' : 'Paket pelanggan diperbarui.');

        return to_route('customers.show', $customer);
    }
}
