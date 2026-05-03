<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Messaging;

final class SmsMessageRepository
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function create(
        int $customerId,
        string $fromNumber,
        string $toNumber,
        string $body,
        string $direction,
        string $status,
        string $gatewayMessageId = ''
    ): array {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO cc_sms_message
                (customer_id, from_number, to_number, body, direction, status, gateway_message_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([$customerId, $fromNumber, $toNumber, $body, $direction, $status, $gatewayMessageId, $now, $now]);

        return $this->findById((int)$this->pdo->lastInsertId());
    }

    /**
     * @return array<string, mixed>
     */
    public function updateStatus(int $id, string $status, string $errorMessage = ''): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'UPDATE cc_sms_message SET status = ?, error_message = ?, updated_at = ? WHERE id = ?'
        );
        $statement->execute([$status, $errorMessage, $now, $id]);

        return $this->findById($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function updateDelivery(int $id, string $status, string $gatewayMessageId = '', string $errorMessage = ''): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'UPDATE cc_sms_message
             SET status = ?, gateway_message_id = ?, error_message = ?, updated_at = ?
             WHERE id = ?'
        );
        $statement->execute([$status, $gatewayMessageId, $errorMessage, $now, $id]);

        return $this->findById($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function findById(int $id): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM cc_sms_message WHERE id = ?');
        $statement->execute([$id]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : [];
    }

    /**
     * @return array{items:list<array<string,mixed>>,total:int}
     */
    public function search(
        int $limit,
        int $offset,
        ?int $customerId = null,
        ?string $direction = null,
        ?string $did = null
    ): array {
        $where = [];
        $params = [];

        if ($customerId !== null) {
            $where[] = 'customer_id = ?';
            $params[] = $customerId;
        }

        if ($direction !== null) {
            $where[] = 'direction = ?';
            $params[] = $direction;
        }

        if ($did !== null) {
            $where[] = '(from_number = ? OR to_number = ?)';
            $params[] = $did;
            $params[] = $did;
        }

        $whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStatement = $this->pdo->prepare("SELECT COUNT(*) FROM cc_sms_message {$whereSql}");
        $countStatement->execute($params);
        $total = (int)$countStatement->fetchColumn();

        $listStatement = $this->pdo->prepare(
            "SELECT * FROM cc_sms_message {$whereSql} ORDER BY id DESC LIMIT ? OFFSET ?"
        );
        foreach ($params as $index => $value) {
            $listStatement->bindValue($index + 1, $value);
        }
        $listStatement->bindValue(count($params) + 1, $limit, \PDO::PARAM_INT);
        $listStatement->bindValue(count($params) + 2, $offset, \PDO::PARAM_INT);
        $listStatement->execute();

        return [
            'items' => $listStatement->fetchAll(\PDO::FETCH_ASSOC),
            'total' => $total,
        ];
    }
}
