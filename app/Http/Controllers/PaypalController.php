<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientCreditsException;
use App\Exceptions\PayPalException;
use App\Models\AdminNotification;
use App\Models\Transaction;
use App\Services\CreditLedger;
use App\Services\PayPal;
use App\Services\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Automatic credit top-ups via PayPal Checkout. Unlike the manual
 * topup-request flow (bank transfer, admin-approved), credits land the
 * moment PayPal confirms the capture — no admin step.
 */
class PaypalController extends Controller
{
    public function __construct(private PayPal $paypal)
    {
    }

    private function fail(string $message, int $status = 422): JsonResponse
    {
        return response()->json(['status' => 'error', 'message' => $message], $status);
    }

    /** Rupees a customer's `units` credits cost, converted to USD for PayPal. */
    private function usdAmount(int $units, float $rateLkr): string
    {
        $perUsd = (float) Settings::get('paypal_usd_rate', (string) config('portal.paypal_usd_rate'));
        $usd = ($units * $rateLkr) / max($perUsd, 1);

        return number_format(max($usd, 0.01), 2, '.', '');
    }

    public function store(Request $request): JsonResponse
    {
        $units = (int) floor((float) $request->input('units'));
        if ($units < 100) {
            return $this->fail('Buy at least 100 credits.');
        }

        $user = $request->user();
        $usd = $this->usdAmount($units, (float) $user->rate);
        $customId = $user->id . ':' . $units;

        try {
            $order = $this->paypal->createOrder($usd, $customId);
        } catch (PayPalException $e) {
            return $this->fail($e->getMessage(), 502);
        }

        if (empty($order['id'])) {
            return $this->fail('PayPal did not return an order id. Try again.', 502);
        }

        return response()->json(['status' => 'success', 'data' => [
            'order_id' => $order['id'],
            'units' => $units,
            'lkr_amount' => round($units * $user->rate, 2),
            'usd_amount' => $usd,
        ]]);
    }

    public function capture(Request $request, string $orderId): JsonResponse
    {
        $user = $request->user();

        // Already-processed replay (double-click, retried request): tell the
        // caller it succeeded without charging or crediting twice.
        $existing = Transaction::where('ref', $orderId)->first();
        if ($existing) {
            return response()->json(['status' => 'success', 'message' => 'Payment already captured.', 'data' => [
                'credits' => $user->fresh()->credits,
            ]]);
        }

        try {
            $capture = $this->paypal->captureOrder($orderId);
        } catch (PayPalException $e) {
            // A retry after our own server dropped the connection mid-flight
            // can land here even though PayPal already took the payment —
            // it refuses to capture the same order twice. Look the order up
            // instead of failing a payment that actually went through.
            if (($e->body['details'][0]['issue'] ?? null) !== 'ORDER_ALREADY_CAPTURED') {
                return $this->fail($e->getMessage(), 502);
            }

            try {
                $capture = $this->paypal->getOrder($orderId);
            } catch (PayPalException $lookupError) {
                return $this->fail($lookupError->getMessage(), 502);
            }
        }

        if (($capture['status'] ?? null) !== 'COMPLETED') {
            return $this->fail('PayPal has not completed this payment yet.', 402);
        }

        $unit = $capture['purchase_units'][0] ?? null;
        [$ownerId, $units] = explode(':', $unit['custom_id'] ?? '0:0') + [0, 0];

        if ((int) $ownerId !== $user->id || (int) $units < 100) {
            return $this->fail('This payment does not match your account.', 403);
        }

        try {
            $balance = CreditLedger::adjust((int) $user->id, (int) $units, [
                'type' => 'topup',
                'note' => number_format((int) $units) . ' credits via PayPal',
                'ref' => $orderId,
                'actor' => 'paypal',
            ]);
        } catch (InsufficientCreditsException $e) {
            // Unreachable in practice (a top-up only adds credits), kept so
            // a future refactor can't silently swallow the ledger's guard.
            return $this->fail($e->getMessage(), 402);
        }

        AdminNotification::create([
            'user_id' => $user->id,
            'type' => 'paypal_topup',
            'message' => $user->name . ' paid for ' . number_format((int) $units) . ' credits via PayPal',
            'data' => ['units' => (int) $units, 'order_id' => $orderId],
        ]);

        return response()->json(['status' => 'success', 'message' => 'Payment captured. Credits added.', 'data' => [
            'credits' => $balance,
        ]]);
    }
}
