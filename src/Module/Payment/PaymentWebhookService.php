<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Payment;

final class PaymentWebhookService
{
    public function __construct(
        private readonly StripeWebhookVerifier $stripeVerifier,
        private readonly PaymentWebhookRepository $repository
    ) {
    }

    /**
     * @return array{status:int, body:array<string, mixed>}
     */
    public function handleStripe(string $rawPayload, string $signatureHeader, ?int $now = null): array
    {
        if (!$this->stripeVerifier->verify($rawPayload, $signatureHeader, $now)) {
            return ['status' => 401, 'body' => ['success' => false, 'message' => 'Invalid Stripe webhook signature.']];
        }

        $payload = json_decode($rawPayload, true);
        if (!is_array($payload)) {
            return ['status' => 400, 'body' => ['success' => false, 'message' => 'Invalid JSON payload.']];
        }

        $eventId = $this->stringValue($payload, 'id');
        $eventType = $this->stringValue($payload, 'type');
        if ($eventId === '' || $eventType === '') {
            return ['status' => 422, 'body' => ['success' => false, 'message' => 'Stripe event id and type are required.']];
        }

        if ($this->repository->hasEvent('stripe', $eventId)) {
            return ['status' => 200, 'body' => ['success' => true, 'duplicate' => true, 'message' => 'Stripe webhook already processed.']];
        }

        $message = match ($eventType) {
            'payment_intent.succeeded' => 'Stripe payment intent success accepted.',
            'payment_intent.payment_failed' => 'Stripe payment intent failure accepted.',
            default => 'Stripe webhook event recorded.',
        };

        $this->repository->record('stripe', $eventType, $eventId, $payload, true, $message);

        return [
            'status' => 202,
            'body' => [
                'success' => true,
                'duplicate' => false,
                'provider' => 'stripe',
                'event_type' => $eventType,
                'event_id' => $eventId,
                'message' => $message,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function stringValue(array $payload, string $key): string
    {
        $value = $payload[$key] ?? '';
        return is_scalar($value) ? trim((string)$value) : '';
    }
}
