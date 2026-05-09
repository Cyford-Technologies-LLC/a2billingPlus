<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $result = vectavoipService()->upstreamDefault(bearerToken(), apiSecret());
        sendJson($result['body'], $result['status']);
    }

    if ($method === 'POST') {
        $result = vectavoipService()->setUpstreamDefault(requestJson(), bearerToken(), apiSecret());
        sendJson($result['body'], $result['status']);
    }

    sendJson(['message' => 'Method not allowed.'], 405);
} catch (InvalidArgumentException $exception) {
    sendJson(['message' => $exception->getMessage()], 422);
} catch (Throwable $exception) {
    sendJson(['message' => 'Upstream provider update failed: ' . $exception->getMessage()], 500);
}
