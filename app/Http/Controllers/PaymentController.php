<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $payments = Payment::with('order')
            ->when(!$request->user()->hasRole('super-admin'), fn ($query) => $query->whereHas('order', fn ($query) => $query->where('user_id', $request->user()->id)))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json(['status' => true, 'data' => $payments]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id' => ['required', 'exists:orders,id'],
            'provider' => ['required', 'in:stripe,aba,bakong,wing'],
            'success_url' => ['nullable', 'url'],
            'cancel_url' => ['nullable', 'url'],
        ]);

        $order = Order::with('items.productVariant.product')->findOrFail($data['order_id']);
        abort_unless($request->user()->hasRole('super-admin') || $order->user_id === $request->user()->id, 403);

        if ($order->payment_status === 'paid') {
            return response()->json(['status' => false, 'message' => 'Order is already paid.'], 422);
        }

        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => $data['provider'],
            'transaction_id' => 'PAY-'.Str::upper(Str::random(16)),
            'amount' => $order->total_amount,
            'currency' => 'USD',
            'status' => 'pending',
            'idempotency_key' => (string) Str::uuid(),
            'raw_response' => [],
        ]);

        if ($data['provider'] === 'stripe') {
            $checkout = $this->createStripeCheckoutSession($order, $payment, $data);
            $payment->update([
                'external_transaction_id' => $checkout['id'] ?? null,
                'raw_response' => $checkout,
            ]);
        }

        return response()->json(['status' => true, 'message' => 'Payment initialized.', 'data' => $payment->fresh()]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Payment $payment)
    {
        abort_unless(request()->user()->hasRole('super-admin') || $payment->order->user_id === request()->user()->id, 403);

        return response()->json(['status' => true, 'data' => $payment->load('order')]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Payment $payment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Payment $payment)
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,paid,failed,refunded'],
            'external_transaction_id' => ['nullable', 'string', 'max:255'],
            'raw_response' => ['nullable', 'array'],
        ]);

        $payment->update($data);

        if ($data['status'] === 'paid') {
            $payment->order->update(['payment_status' => 'paid', 'status' => 'paid']);
        }

        return response()->json(['status' => true, 'message' => 'Payment updated.', 'data' => $payment->fresh()]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Payment $payment)
    {
        return response()->json(['status' => false, 'message' => 'Payments cannot be deleted.'], 405);
    }

    public function stripeWebhook(Request $request)
    {
        $payload = $request->getContent();
        $secret = config('services.stripe.webhook_secret');

        if ($secret && !$this->validStripeSignature($payload, (string) $request->header('Stripe-Signature'), $secret)) {
            return response()->json(['status' => false, 'message' => 'Invalid webhook signature.'], 400);
        }

        $event = json_decode($payload, true);

        if (($event['type'] ?? null) === 'checkout.session.completed') {
            $session = $event['data']['object'] ?? [];
            $paymentId = $session['metadata']['payment_id'] ?? null;

            if ($paymentId) {
                $payment = Payment::find($paymentId);
                $payment?->update([
                    'status' => 'paid',
                    'external_transaction_id' => $session['payment_intent'] ?? $session['id'] ?? $payment->external_transaction_id,
                    'raw_response' => $session,
                ]);
                $payment?->order?->update(['payment_status' => 'paid', 'status' => 'paid']);
            }
        }

        return response()->json(['status' => true]);
    }

    private function createStripeCheckoutSession(Order $order, Payment $payment, array $data): array
    {
        $secret = config('services.stripe.secret');

        if (!$secret) {
            return [
                'mode' => 'payment',
                'mock' => true,
                'message' => 'Set STRIPE_SECRET to create a live Stripe Checkout Session.',
                'payment_id' => $payment->id,
            ];
        }

        $lineItems = $order->items->map(function ($item) {
            $amount = (int) round(((float) $item->price_at_purchase) * 100);

            return [
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => ['name' => $item->productVariant->product->name],
                    'unit_amount' => $amount,
                ],
                'quantity' => $item->quantity,
            ];
        })->values()->all();

        $response = Http::asForm()
            ->withToken($secret)
            ->withHeaders(['Stripe-Version' => '2026-02-25.clover'])
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'success_url' => $data['success_url'] ?? config('app.url').'/checkout/success',
                'cancel_url' => $data['cancel_url'] ?? config('app.url').'/checkout/cancel',
                'client_reference_id' => $order->order_number,
                'metadata[order_id]' => $order->id,
                'metadata[payment_id]' => $payment->id,
                'line_items' => $lineItems,
            ]);

        return $response->json() ?? ['status' => $response->status(), 'body' => $response->body()];
    }

    private function validStripeSignature(string $payload, string $signatureHeader, string $secret): bool
    {
        $parts = collect(explode(',', $signatureHeader))
            ->mapWithKeys(function ($part) {
                [$key, $value] = array_pad(explode('=', $part, 2), 2, null);

                return [$key => $value];
            });

        $timestamp = $parts->get('t');
        $signature = $parts->get('v1');

        if (!$timestamp || !$signature) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return hash_equals($expected, $signature);
    }
}
