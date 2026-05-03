<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    sendJson(['message' => 'Method not allowed.'], 405);
}

try {
    $result = vectavoipService()->registerInstallation(requestJson());
    sendJson($result['body'], $result['status']);
} catch (InvalidArgumentException $exception) {
    sendJson(['message' => $exception->getMessage()], 422);
} catch (Throwable $exception) {
    sendJson(['message' => 'Registration failed: ' . $exception->getMessage()], 500);
}
