<?php

namespace App\Services;

use App\Exceptions\PayPalException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * PayPal Orders API v2 client (sandbox or live, per PAYPAL_MODE).
 *
 * Amounts always go out in USD — PayPal does not settle in LKR — so callers
 * convert rupees to dollars before calling createOrder(). The client secret
 * never reaches the browser; only the order id does.
 */
class PayPal
{
    private function baseUrl(): string
    {
        return config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function credentials(): array
    {
        $id = config('services.paypal.client_id');
        $secret = config('services.paypal.client_secret');

        if (!$id || !$secret) {
            throw new PayPalException('PayPal is not configured. Add PAYPAL_CLIENT_ID and PAYPAL_CLIENT_SECRET to your .env file.');
        }

        return [$id, $secret];
    }

    private function accessToken(): string
    {
        return Cache::remember('paypal_access_token_' . config('services.paypal.mode'), 480, function () {
            [$id, $secret] = $this->credentials();

            try {
                $response = Http::asForm()
                    ->withBasicAuth($id, $secret)
                    ->baseUrl($this->baseUrl())
                    ->timeout(15)
                    ->post('/v1/oauth2/token', ['grant_type' => 'client_credentials']);
            } catch (ConnectionException $e) {
                throw new PayPalException("Could not reach PayPal: {$e->getMessage()}");
            }

            if ($response->failed()) {
                throw new PayPalException('PayPal rejected the API credentials.', $response->status(), $response->json());
            }

            return $response->json('access_token');
        });
    }

    private function request(string $method, string $endpoint, array $body = []): array
    {
        try {
            $pending = Http::baseUrl($this->baseUrl())
                ->withToken($this->accessToken())
                ->acceptJson()
                ->asJson()
                ->timeout(20);
            $response = $method === 'GET' ? $pending->get($endpoint) : $pending->post($endpoint, $body);
        } catch (ConnectionException $e) {
            throw new PayPalException("Could not reach PayPal: {$e->getMessage()}");
        }

        $payload = $response->json() ?? ['raw' => $response->body()];

        if ($response->failed()) {
            $message = $payload['message']
                ?? ($payload['details'][0]['description'] ?? null)
                ?? "PayPal responded with {$response->status()}";
            throw new PayPalException($message, $response->status(), $payload);
        }

        return $payload;
    }

    /**
     * Creates a CAPTURE-intent order for a single purchase unit. $customId
     * round-trips through PayPal untouched, so the capture step can recover
     * exactly what was ordered without trusting anything the client sends.
     */
    public function createOrder(string $usdAmount, string $customId): array
    {
        return $this->request('POST', '/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'custom_id' => $customId,
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => $usdAmount,
                ],
            ]],
        ]);
    }

    public function captureOrder(string $orderId): array
    {
        return $this->request('POST', '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture');
    }

    /** Order status + purchase_units, same shape a capture response carries. */
    public function getOrder(string $orderId): array
    {
        return $this->request('GET', '/v2/checkout/orders/' . rawurlencode($orderId));
    }
}
