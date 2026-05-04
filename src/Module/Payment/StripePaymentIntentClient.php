<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Payment;

final class StripePaymentIntentClient
{
    /**
     * @param null|callable(string,array<string, string>,array<string, string>): array{status:int,body:string} $transport
     */
    public function __construct(
        private readonly string $secretKey,
        private readonly mixed $transport = null
    ) {
    }

    public function create(PaymentIntentRequest $request): PaymentIntentResult
    {
        if ($this->secretKey === '' || (!str_starts_with($this->secretKey, 'sk_') && !str_starts_with($this->secretKey, 'rk_'))) {
            return new PaymentIntentResult(false, 503, 'stripe', message: 'Stripe secret key is not configured.');
        }

        $headers = [
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];
        if ($request->idempotencyKey !== '') {
            $headers['Idempotency-Key'] = $request->idempotencyKey;
        }

        $fields = [
            'amount' => (string)$request->amountMinorUnits,
            'currency' => strtolower($request->currency),
            'automatic_payment_methods[enabled]' => 'true',
            'metadata[a2bp_customer_id]' => (string)$request->customerId,
        ];
        if ($request->description !== '') {
            $fields['description'] = $request->description;
        }

        $response = $this->send('https://api.stripe.com/v1/payment_intents', $headers, $fields);
        $decoded = json_decode($response['body'], true);
        $payload = is_array($decoded) ? $decoded : [];

        if ($response['status'] < 200 || $response['status'] >= 300) {
            return new PaymentIntentResult(
                false,
                $response['status'],
                'stripe',
                message: $this->errorMessage($payload),
                raw: $payload
            );
        }

        return new PaymentIntentResult(
            true,
            $response['status'],
            'stripe',
            paymentIntentId: $this->stringValue($payload, 'id'),
            clientSecret: $this->stringValue($payload, 'client_secret'),
            status: $this->stringValue($payload, 'status'),
            message: 'Stripe payment intent created.',
            raw: $payload
        );
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, string> $fields
     * @return array{status:int,body:string}
     */
    private function send(string $url, array $headers, array $fields): array
    {
        if ($this->transport !== null) {
            return ($this->transport)($url, $headers, $fields);
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headerLines),
                'content' => http_build_query($fields),
                'ignore_errors' => true,
                'timeout' => 15,
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        $status = $this->statusFromHeaders($http_response_header ?? []);

        return [
            'status' => $status,
            'body' => is_string($body) ? $body : '',
        ];
    }

    /**
     * @param list<string> $headers
     */
    private function statusFromHeaders(array $headers): int
    {
        $first = $headers[0] ?? '';
        if (preg_match('/\s(\d{3})\s/', $first, $matches) === 1) {
            return (int)$matches[1];
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function stringValue(array $payload, string $key): string
    {
        $value = $payload[$key] ?? '';
        return is_scalar($value) ? (string)$value : '';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function errorMessage(array $payload): string
    {
        $error = $payload['error'] ?? [];
        if (is_array($error) && isset($error['message']) && is_scalar($error['message'])) {
            return (string)$error['message'];
        }

        return 'Stripe payment intent request failed.';
    }
}
