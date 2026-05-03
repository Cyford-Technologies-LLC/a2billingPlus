<?php

declare(strict_types=1);

/*
 * Sandbox-only VectaVoIP rate preview endpoint.
 */

$rateDeck = trim((string)($_GET['rate_deck'] ?? 'default'));
$currency = strtoupper(trim((string)($_GET['currency'] ?? 'USD')));

if ($rateDeck === '') {
    sendJson(['message' => 'rate_deck is required.'], 422);
}

if ($currency === '') {
    sendJson(['message' => 'currency is required.'], 422);
}

$rows = [
    [
        'destination' => 'United States',
        'prefix' => '1',
        'rate' => '0.0100',
        'currency' => $currency,
        'increment' => 60,
        'rate_deck' => $rateDeck,
    ],
    [
        'destination' => 'Canada',
        'prefix' => '1',
        'rate' => '0.0125',
        'currency' => $currency,
        'increment' => 60,
        'rate_deck' => $rateDeck,
    ],
    [
        'destination' => 'United Kingdom',
        'prefix' => '44',
        'rate' => '0.0180',
        'currency' => $currency,
        'increment' => 60,
        'rate_deck' => $rateDeck,
    ],
];

sendJson([
    'message' => 'Sandbox VectaVoIP rate preview completed.',
    'total_rows' => count($rows),
    'sample_rows' => $rows,
], 200);

/**
 * @param array<string, mixed> $payload
 */
function sendJson(array $payload, int $statusCode): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}
