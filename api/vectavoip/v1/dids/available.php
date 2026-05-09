<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    sendJson(['message' => 'Method not allowed.'], 405);
}

try {
    $query = [];
    foreach ($_GET as $key => $value) {
        if (is_scalar($value)) {
            $query[(string)$key] = (string)$value;
        }
    }

    $result = vectavoipService()->availableDids($query, bearerToken(), apiSecret());
    sendJson($result['body'], $result['status']);
} catch (Throwable $exception) {
    sendJson(['message' => 'DID search failed: ' . $exception->getMessage()], 500);
}
