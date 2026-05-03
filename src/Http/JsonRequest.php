<?php

declare(strict_types=1);

namespace A2BillingPlus\Http;

final class JsonRequest
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly string $method,
        private readonly array $query = [],
        private readonly array $body = [],
        private readonly array $headers = []
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

        return new self($_SERVER['REQUEST_METHOD'] ?? 'GET', $_GET, $decoded, self::headersFromServer($_SERVER));
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

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->body[$key] ?? $this->query[$key] ?? $default;
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int)$value;
        }

        return $default;
    }

    public function getHeader(string $name): string
    {
        $normalized = strtolower($name);
        foreach ($this->headers as $header => $value) {
            if (strtolower($header) === $normalized) {
                return $value;
            }
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    public function getArray(string $key): array
    {
        $value = $this->body[$key] ?? $this->query[$key] ?? [];
        return is_array($value) ? $value : [];
    }

    /**
     * @param array<string, mixed> $server
     * @return array<string, string>
     */
    private static function headersFromServer(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (!is_scalar($value)) {
                continue;
            }

            if ($key === 'HTTP_AUTHORIZATION') {
                $headers['Authorization'] = (string)$value;
                continue;
            }

            if (str_starts_with((string)$key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr((string)$key, 5)))));
                $headers[$name] = (string)$value;
            }
        }

        return $headers;
    }
}
