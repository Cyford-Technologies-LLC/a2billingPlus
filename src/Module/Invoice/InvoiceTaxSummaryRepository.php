<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Invoice;

final class InvoiceTaxSummaryRepository
{
    private const TABLE = 'cc_invoice_item';

    private const COLUMNS = ['id_invoice', 'price', 'VAT'];

    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * @return array{subtotal:string,tax_total:string,total:string,rates:list<array{vat_rate:string,subtotal:string,tax_total:string,total:string}>}
     */
    public function summarizeInvoice(int $invoiceId): array
    {
        $columns = $this->availableColumns(self::TABLE, self::COLUMNS);
        foreach (self::COLUMNS as $column) {
            if (!in_array($column, $columns, true)) {
                return $this->emptySummary();
            }
        }

        $statement = $this->pdo->prepare(sprintf(
            'SELECT %s AS vat_rate, COALESCE(SUM(%s), 0) AS subtotal
             FROM %s
             WHERE %s = :invoice_id
             GROUP BY %s
             ORDER BY %s ASC',
            $this->quoteIdentifier('VAT'),
            $this->quoteIdentifier('price'),
            $this->quoteIdentifier(self::TABLE),
            $this->quoteIdentifier('id_invoice'),
            $this->quoteIdentifier('VAT'),
            $this->quoteIdentifier('VAT')
        ));
        $statement->bindValue(':invoice_id', $invoiceId, \PDO::PARAM_INT);
        $statement->execute();

        $subtotal = 0.0;
        $taxTotal = 0.0;
        $rates = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $vatRate = $this->decimalValue($row, 'vat_rate');
            $rateSubtotal = $this->decimalValue($row, 'subtotal');
            $rateTax = $rateSubtotal * ($vatRate / 100);
            $rateTotal = $rateSubtotal + $rateTax;

            $subtotal += $rateSubtotal;
            $taxTotal += $rateTax;
            $rates[] = [
                'vat_rate' => $this->format($vatRate),
                'subtotal' => $this->format($rateSubtotal),
                'tax_total' => $this->format($rateTax),
                'total' => $this->format($rateTotal),
            ];
        }

        return [
            'subtotal' => $this->format($subtotal),
            'tax_total' => $this->format($taxTotal),
            'total' => $this->format($subtotal + $taxTotal),
            'rates' => $rates,
        ];
    }

    /**
     * @return array{subtotal:string,tax_total:string,total:string,rates:list<array{vat_rate:string,subtotal:string,tax_total:string,total:string}>}
     */
    private function emptySummary(): array
    {
        return [
            'subtotal' => '0.00000',
            'tax_total' => '0.00000',
            'total' => '0.00000',
            'rates' => [],
        ];
    }

    /**
     * @param list<string> $preferred
     * @return list<string>
     */
    private function availableColumns(string $table, array $preferred): array
    {
        $driver = (string)$this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $statement = $this->pdo->query('PRAGMA table_info(' . $this->quoteIdentifier($table) . ')');
            $rows = $statement ? $statement->fetchAll(\PDO::FETCH_ASSOC) : [];
            $available = array_map(static fn (array $row): string => (string)$row['name'], $rows);
        } else {
            $statement = $this->pdo->query('SHOW COLUMNS FROM ' . $this->quoteIdentifier($table));
            $rows = $statement ? $statement->fetchAll(\PDO::FETCH_ASSOC) : [];
            $available = array_map(static fn (array $row): string => (string)$row['Field'], $rows);
        }

        return array_values(array_intersect($preferred, $available));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function decimalValue(array $row, string $key): float
    {
        $value = $row[$key] ?? 0;
        return is_numeric($value) ? (float)$value : 0.0;
    }

    private function format(float $value): string
    {
        return number_format(round($value, 5, PHP_ROUND_HALF_UP), 5, '.', '');
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
            throw new \InvalidArgumentException('Unsafe SQL identifier.');
        }

        return '`' . $identifier . '`';
    }
}
