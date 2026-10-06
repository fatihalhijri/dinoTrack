<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Network\ActivateCustomerManually;
use App\Actions\Network\IsolateCustomerManually;
use App\Http\Requests\Customers\ManualIsolationRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;

/**
 * Isolir dan buka isolir manual oleh admin (K8). Perintah router berjalan di queue, sehingga
 * pesan sukses berarti perintah sudah dijadwalkan.
 */
class CustomerIsolationController extends Controller
{
    public function isolate(ManualIsolationRequest $request, Customer $customer, IsolateCustomerManually $isolateCustomer): RedirectResponse
    {
        $isolateCustomer->handle($customer, $request->string('reason')->toString(), $this->actor($request));
        $this->toast("Isolir pelanggan {$customer->name} sedang diproses.");

        return to_route('customers.show', $customer);
    }

    public function release(ManualIsolationRequest $request, Customer $customer, ActivateCustomerManually $activateCustomer): RedirectResponse
    {
        $hasArrearsPastGrace = $activateCustomer->handle($customer, $request->string('reason')->toString(), $this->actor($request));

        // N7: pelanggan yang masih menunggak akan diisolir lagi oleh isolir otomatis berikutnya.
        $hasArrearsPastGrace
            ? $this->toast("Buka isolir {$customer->name} sedang diproses, tetapi pelanggan masih punya tunggakan lewat toleransi dan akan diisolir lagi pada isolir otomatis berikutnya.", 'warning')
            : $this->toast("Buka isolir pelanggan {$customer->name} sedang diproses.");

        return to_route('customers.show', $customer);
    }
}
