<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Payments\ResolvePayment;
use App\Enums\PaymentMethod;
use App\Enums\PaymentReviewStatus;
use App\Http\Requests\Payments\PaymentIndexRequest;
use App\Http\Requests\Payments\ReviewPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(PaymentIndexRequest $request): Response
    {
        $filters = $request->filters();
        $payments = Payment::query()
            ->with(['invoice.customer', 'paymentCharge', 'receivedBy'])
            ->applyFilters($filters)
            ->latest('paid_at')
            ->latest('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return Inertia::render('payments/index', [
            'payments' => PaymentResource::collection($payments),
            'filters' => $request->validated(),
            'methods' => array_map(fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => $method->label()], PaymentMethod::cases()),
            'review_statuses' => array_map(fn (PaymentReviewStatus $status): array => ['value' => $status->value, 'label' => $status->label()], PaymentReviewStatus::cases()),
            // Pelanggan yang dipilih lewat `customer_id` (dari detail pelanggan) untuk chip filter.
            'customer' => $filters['customer_id'] === null
                ? null
                : Customer::query()->find($filters['customer_id'], ['id', 'code', 'name'])?->only(['id', 'code', 'name']),
        ]);
    }

    public function review(ReviewPaymentRequest $request, Payment $payment, ResolvePayment $resolvePayment): RedirectResponse
    {
        $resolvePayment->handle($payment, $request->string('review_note')->toString(), $this->actor($request));
        $this->toast('Pembayaran ditandai sudah ditinjau.');

        return back();
    }
}
