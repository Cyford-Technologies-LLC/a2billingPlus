<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    sendJson(['message' => 'Method not allowed.'], 405);
}

try {
    $result = vectavoipService()->installationStatus(bearerToken(), apiSecret());
    sendJson($result['body'], $result['status']);
} catch (Throwable $exception) {
    sendJson(['message' => 'Status check failed: ' . $exception->getMessage()], 500);
}
