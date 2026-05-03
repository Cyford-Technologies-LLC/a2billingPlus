<?php

declare(strict_types=1);

namespace A2BillingPlus\Http;

final class JsonResponse
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly array $payload,
        private readonly int $statusCode = 200
    ) {
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);
        header('Content-Type: application/json');
        echo json_encode($this->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
