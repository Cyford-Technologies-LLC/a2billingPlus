<?php

declare(strict_types=1);

namespace A2BillingPlus\Http;

final class JsonRequest
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     */
    public function __construct(
        private readonly string $method,
        private readonly array $query = [],
        private readonly array $body = []
    ) {
    }

    public static function fromGlobals(): self
    {
        $rawBody = file_get_contents('php://input');
        $decoded = [];

        if (is_string($rawBody) && trim($rawBody) !== '') {
            $json = json_decode($rawBody, true);
            $decoded = is_array($json) ? $json : [];
        }

        return new self($_SERVER['REQUEST_METHOD'] ?? 'GET', $_GET, $decoded);
    }

    public function getMethod(): string
    {
        return strtoupper($this->method);
    }

    public function getString(string $key, string $default = ''): string
    {
        $value = $this->body[$key] ?? $this->query[$key] ?? $default;
        return is_scalar($value) ? (string)$value : $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function getArray(string $key): array
    {
        $value = $this->body[$key] ?? $this->query[$key] ?? [];
        return is_array($value) ? $value : [];
    }
}
