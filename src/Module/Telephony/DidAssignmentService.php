<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Telephony;

use A2BillingPlus\Module\Security\AuditLogRepository;

final class DidAssignmentService
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly ?AuditLogRepository $auditLog = null
    )
    {
    }

    /**
     * @return array{success:bool, message:string, assignment?:array<string,mixed>}
     */
    public function assign(
        int $customerId,
        string $did,
        bool $smsEnabled = true,
        bool $voiceEnabled = true,
        string $actor = 'service-key',
        string $webhookUrl = ''
    ): array
    {
        $didRepo = new DidRepository($this->pdo);

        $inventory = $didRepo->findByNumber($did);
        if ($inventory === null) {
            return ['success' => false, 'message' => 'DID not found in inventory.'];
        }

        if ($inventory['status'] !== 'available') {
            return ['success' => false, 'message' => 'DID is not available for assignment.'];
        }

        $this->pdo->beginTransaction();
        try {
            $didRepo->markAssigned($did);

            $now = gmdate('Y-m-d H:i:s');
            $columns = ['customer_id', 'did', 'status', 'sms_enabled', 'voice_enabled', 'provider_reference', 'assigned_at'];
            $values = [
                $customerId,
                $did,
                'active',
                $smsEnabled ? 1 : 0,
                $voiceEnabled ? 1 : 0,
                $inventory['provider_reference'],
                $now,
            ];
            if ($this->columnExists('cc_did_assignment', 'webhook_url')) {
                array_splice($columns, 6, 0, 'webhook_url');
                array_splice($values, 6, 0, $webhookUrl);
            }

            $statement = $this->pdo->prepare(sprintf(
                'INSERT INTO cc_did_assignment (%s) VALUES (%s)',
                implode(', ', array_map([$this, 'quoteIdentifier'], $columns)),
                implode(', ', array_fill(0, count($columns), '?'))
            ));
            $statement->execute($values);
            $assignmentId = (int)$this->pdo->lastInsertId();
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'DID assignment failed: ' . $exception->getMessage()];
        }

        $assignment = $this->findAssignment($assignmentId);
        $this->recordAudit($actor, 'did_assignment.assign', $did, [
            'customer_id' => $customerId,
            'sms_enabled' => $smsEnabled,
            'voice_enabled' => $voiceEnabled,
        ]);

        return ['success' => true, 'message' => 'DID assigned successfully.', 'assignment' => $assignment];
    }

    /**
     * @return array{success:bool, message:string}
     */
    public function release(int $customerId, string $did, string $actor = 'service-key'): array
    {
        $statement = $this->pdo->prepare(
            "SELECT id FROM cc_did_assignment WHERE customer_id = ? AND did = ? AND status = 'active' LIMIT 1"
        );
        $statement->execute([$customerId, $did]);
        $assignment = $statement->fetch(\PDO::FETCH_ASSOC);

        if (!is_array($assignment)) {
            return ['success' => false, 'message' => 'Active DID assignment not found for this customer.'];
        }

        $this->pdo->beginTransaction();
        try {
            $now = gmdate('Y-m-d H:i:s');
            $update = $this->pdo->prepare(
                "UPDATE cc_did_assignment SET status = 'released', released_at = ? WHERE id = ?"
            );
            $update->execute([$now, $assignment['id']]);

            (new DidRepository($this->pdo))->markAvailable($did);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'DID release failed: ' . $exception->getMessage()];
        }

        $this->recordAudit($actor, 'did_assignment.release', $did, [
            'customer_id' => $customerId,
        ]);

        return ['success' => true, 'message' => 'DID released successfully.'];
    }

    /**
     * @return array<string, mixed>
     */
    private function findAssignment(int $id): array
    {
        $statement = $this->pdo->prepare(
            'SELECT a.*, i.country, i.region, i.monthly_rate, i.currency
             FROM cc_did_assignment a
             LEFT JOIN cc_vectavoip_did_inventory i ON i.did = a.did
             WHERE a.id = ?'
        );
        $statement->execute([$id]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : [];
    }

    /**
     * @param array<string,mixed> $metadata
     */
    private function recordAudit(string $actor, string $action, string $did, array $metadata): void
    {
        $this->auditLog?->record($actor, $action, 'cc_did_assignment', $did, $metadata);
    }

    private function columnExists(string $table, string $column): bool
    {
        $driver = (string)$this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $statement = $this->pdo->query('PRAGMA table_info(' . $this->quoteIdentifier($table) . ')');
            $rows = $statement ? $statement->fetchAll(\PDO::FETCH_ASSOC) : [];
            foreach ($rows as $row) {
                if ((string)$row['name'] === $column) {
                    return true;
                }
            }

            return false;
        }

        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $statement->execute([$table, $column]);

        return (int)$statement->fetchColumn() > 0;
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
            throw new \InvalidArgumentException('Unsafe SQL identifier.');
        }

        return '`' . $identifier . '`';
    }
}
