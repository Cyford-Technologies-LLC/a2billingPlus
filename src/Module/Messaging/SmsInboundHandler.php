<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Messaging;

/**
 * Handles an inbound SMS event from VectaVoIP.
 * Stores the message in cc_sms_message and fires the CRM callback if configured.
 */
final class SmsInboundHandler
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly SmsMessageRepository $messageRepo
    ) {
    }

    /**
     * @param array<string,mixed> $payload  Decoded webhook payload
     * @return array{success:bool, message:string, message_id?:int}
     */
    public function handle(array $payload): array
    {
        $did   = trim((string)($payload['to']              ?? $payload['did']         ?? ''));
        $from  = trim((string)($payload['from']            ?? $payload['from_number'] ?? ''));
        $body  = trim((string)($payload['body']            ?? $payload['text']        ?? ''));
        $msgId = trim((string)($payload['message_id']      ?? $payload['id']          ?? ''));

        if ($did === '' || $from === '' || $body === '') {
            return ['success' => false, 'message' => 'Missing required fields: to, from, body.'];
        }

        // Look up which customer owns this DID
        $customerId = $this->resolveCustomerByDid($did);

        // Persist to cc_sms_message  (from=caller, to=DID for inbound)
        $stored = $this->messageRepo->create(
            $customerId,
            $from,
            $did,
            $body,
            'inbound',
            'received',
            $msgId
        );
        $storedId = (int)($stored['id'] ?? 0);

        // Fire CRM callback if a webhook_url is registered for this DID / customer
        $this->fireCrmCallback($did, $customerId, $storedId, $from, $body, $msgId);

        return ['success' => true, 'message' => 'Inbound SMS processed.', 'message_id' => $storedId];
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function resolveCustomerByDid(string $did): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT customer_id FROM cc_did_assignment WHERE did = ? AND status = 'active' ORDER BY assigned_at DESC LIMIT 1"
        );
        $stmt->execute([$did]);
        return (int)($stmt->fetchColumn() ?: 0);
    }

    private function fireCrmCallback(string $did, int $customerId, int $messageId, string $from, string $body, string $gatewayMsgId): void
    {
        $url = $this->getCrmCallbackUrl($did, $customerId);
        if (!$url) {
            return;
        }

        $payload = json_encode([
            'event'             => 'sms.inbound',
            'did'               => $did,
            'customer_id'       => $customerId,
            'message_id'        => $messageId,
            'from_number'       => $from,
            'body'              => $body,
            'gateway_message_id'=> $gatewayMsgId,
            'received_at'       => gmdate('Y-m-d\TH:i:s\Z'),
        ]);

        $ctx = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\nX-A2BP-Event: sms.inbound\r\n",
            'content' => $payload,
            'timeout' => 5,
            'ignore_errors' => true,
        ]]);

        @file_get_contents($url, false, $ctx);
    }

    private function getCrmCallbackUrl(string $did, int $customerId): string
    {
        // Per-DID webhook URL takes precedence
        try {
            $stmt = $this->pdo->prepare(
                "SELECT webhook_url FROM cc_did_assignment WHERE did=? AND status='active' LIMIT 1"
            );
            $stmt->execute([$did]);
            $url = (string)($stmt->fetchColumn() ?: '');
            if ($url !== '') return $url;
        } catch (\Throwable $e) {}

        // Fall back to customer-level webhook URL
        if ($customerId > 0) {
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT webhook_url FROM cc_card WHERE id=? LIMIT 1"
                );
                $stmt->execute([$customerId]);
                $url = (string)($stmt->fetchColumn() ?: '');
                if ($url !== '') return $url;
            } catch (\Throwable $e) {}
        }

        // Fall back to global env config
        $global = getenv('CRM_SMS_WEBHOOK_URL');
        return is_string($global) ? $global : '';
    }
}
