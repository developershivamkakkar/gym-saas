<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Models\Shard\Invoice;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Exception;

class BillingController extends Controller
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function invoices(Request $request)
    {
        $tenant = app('tenant');
        $invoices = Invoice::where('tenant_id', $tenant->id)
            ->with(['member', 'payments'])
            ->latest()
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $invoices->items(),
            'pagination' => [
                'total' => $invoices->total(),
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
            ],
        ]);
    }

    public function showInvoice($id)
    {
        $tenant = app('tenant');
        $invoice = Invoice::where('tenant_id', $tenant->id)
            ->with(['member', 'payments', 'subscription'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $invoice,
        ]);
    }

    public function recordPayment(Request $request, $id)
    {
        $tenant = app('tenant');

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|max:50',
            'transaction_reference' => 'nullable|string|max:100',
            'idempotency_key' => 'nullable|string|max:100',
        ]);

        try {
            $payment = $this->paymentService->recordPayment(
                $tenant->id,
                $id,
                $validated['amount'],
                $validated['payment_method'],
                $validated['transaction_reference'] ?? null,
                $validated['idempotency_key'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Payment recorded successfully',
                'data' => $payment,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
