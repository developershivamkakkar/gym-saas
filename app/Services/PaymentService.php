<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Shard\Invoice;
use App\Models\Shard\Payment;
use Illuminate\Support\Facades\DB;
use Exception;

class PaymentService
{
    /**
     * Record a payment against an invoice with idempotency protection.
     */
    public function recordPayment(int $tenantId, int $invoiceId, float $amount, string $paymentMethod, ?string $transactionReference = null, ?string $idempotencyKey = null): Payment
    {
        if ($idempotencyKey) {
            $existingPayment = Payment::where('tenant_id', $tenantId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingPayment) {
                return $existingPayment;
            }
        }

        return DB::transaction(function () use ($tenantId, $invoiceId, $amount, $paymentMethod, $transactionReference, $idempotencyKey) {
            $invoice = Invoice::where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->findOrFail($invoiceId);

            $payment = Payment::create([
                'tenant_id' => $tenantId,
                'invoice_id' => $invoiceId,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'transaction_reference' => $transactionReference,
                'status' => PaymentStatus::COMPLETED->value,
                'idempotency_key' => $idempotencyKey,
            ]);

            $totalPaid = Payment::where('invoice_id', $invoiceId)
                ->where('status', PaymentStatus::COMPLETED->value)
                ->sum('amount');

            if ($totalPaid >= $invoice->total) {
                $invoice->update(['status' => InvoiceStatus::PAID->value]);
            } elseif ($totalPaid > 0) {
                $invoice->update(['status' => InvoiceStatus::PARTIALLY_PAID->value]);
            }

            return $payment;
        });
    }
}
