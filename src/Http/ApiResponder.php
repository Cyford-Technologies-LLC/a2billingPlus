<?php

declare(strict_types=1);

namespace A2BillingPlus\Http;

final class ApiResponder
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $meta
     */
    public static function ok(array $data = [], array $meta = [], int $statusCode = 200): JsonResponse
    {
        $payload = [
            'api_version' => 'v1',
            'success' => true,
            'data' => $data,
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return new JsonResponse($payload, $statusCode);
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function error(string $code, string $message, int $statusCode, array $details = []): JsonResponse
    {
        $payload = [
            'api_version' => 'v1',
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        if ($details !== []) {
            $payload['error']['details'] = $details;
        }

        return new JsonResponse($payload, $statusCode);
    }
}
