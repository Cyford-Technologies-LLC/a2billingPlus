<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider;

final class ProviderWebhookService
{
    public function __construct(
        private readonly ProviderWebhookVerifier $verifier,
        private readonly ProviderWebhookRepository $repository
    ) {
    }

    /**
     * @return array{status:int, body:array<string, mixed>}
     */
    public function handle(
        string $provider,
        string $rawPayload,
        string $signature,
        int $timestamp,
        ?int $now = null
    ): array {
        if (!$this->verifier->verify($rawPayload, $signature, $timestamp, $now)) {
            return ['status' => 401, 'body' => ['success' => false, 'message' => 'Invalid provider webhook signature.']];
        }

        $payload = json_decode($rawPayload, true);
        if (!is_array($payload)) {
            return ['status' => 400, 'body' => ['success' => false, 'message' => 'Invalid JSON payload.']];
        }

        $eventType = $this->stringValue($payload, 'event_type');
        $eventId = $this->stringValue($payload, 'event_id');
        if ($eventType === '' || $eventId === '') {
            return ['status' => 422, 'body' => ['success' => false, 'message' => 'event_type and event_id are required.']];
        }

        if ($this->repository->hasEvent($provider, $eventId)) {
            return ['status' => 200, 'body' => ['success' => true, 'duplicate' => true, 'message' => 'Provider webhook already processed.']];
        }

        $message = match ($eventType) {
            'rate_deck.updated' => 'Provider rate deck update accepted.',
            'account.updated' => 'Provider account update accepted.',
            default => 'Provider webhook event recorded.',
        };

        $this->repository->record($provider, $eventType, $eventId, $payload, true, $message);

        return [
            'status' => 202,
            'body' => [
                'success' => true,
                'duplicate' => false,
                'provider' => $provider,
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
