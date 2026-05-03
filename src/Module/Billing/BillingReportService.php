<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Billing;

final class BillingReportService
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * @return array{attempts:int,answered_calls:int,asr_percent:string,aloc_seconds:string}
     */
    public function callQualitySummary(string $from, string $to): array
    {
        $statement = $this->pdo->prepare(
            'SELECT
                COUNT(*) AS attempts,
                SUM(CASE WHEN terminatecauseid = 1 AND sessiontime > 0 THEN 1 ELSE 0 END) AS answered_calls,
                AVG(CASE WHEN terminatecauseid = 1 AND sessiontime > 0 THEN sessiontime ELSE NULL END) AS aloc_seconds
             FROM cc_call
             WHERE starttime >= :from_date AND starttime < :to_date'
        );
        $statement->execute([
            'from_date' => $from,
            'to_date' => $to,
        ]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC) ?: [];

        $attempts = (int)($row['attempts'] ?? 0);
        $answered = (int)($row['answered_calls'] ?? 0);
        $asr = $attempts > 0 ? ($answered / $attempts) * 100 : 0;
        $aloc = is_numeric($row['aloc_seconds'] ?? null) ? (float)$row['aloc_seconds'] : 0;

        return [
            'attempts' => $attempts,
            'answered_calls' => $answered,
            'asr_percent' => number_format($asr, 2, '.', ''),
            'aloc_seconds' => number_format($aloc, 2, '.', ''),
        ];
    }

    /**
     * @return list<array{report_date:string,attempts:int,answered_calls:int,asr_percent:string,aloc_seconds:string}>
     */
    public function dailyCallQuality(string $from, string $to, string $timezone = 'UTC'): array
    {
        $statement = $this->pdo->prepare(
            'SELECT
                starttime,
                sessiontime,
                terminatecauseid
             FROM cc_call
             WHERE starttime >= :from_date AND starttime < :to_date
             ORDER BY starttime'
        );
        $statement->execute([
            'from_date' => $from,
            'to_date' => $to,
        ]);

        $groups = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $reportDate = $this->startTimeDateInTimezone((string)$row['starttime'], $timezone);
            $groups[$reportDate] ??= ['attempts' => 0, 'answered_calls' => 0, 'answered_seconds' => 0];
            $groups[$reportDate]['attempts']++;
            if ((int)$row['terminatecauseid'] === 1 && (int)$row['sessiontime'] > 0) {
                $groups[$reportDate]['answered_calls']++;
                $groups[$reportDate]['answered_seconds'] += (int)$row['sessiontime'];
            }
        }

        $rows = [];
        foreach ($groups as $reportDate => $group) {
            $attempts = (int)$group['attempts'];
            $answered = (int)$group['answered_calls'];
            $aloc = $answered > 0 ? ((int)$group['answered_seconds'] / $answered) : 0;
            $rows[] = [
                'report_date' => $reportDate,
                'attempts' => $attempts,
                'answered_calls' => $answered,
                'asr_percent' => number_format($attempts > 0 ? ($answered / $attempts) * 100 : 0, 2, '.', ''),
                'aloc_seconds' => number_format($aloc, 2, '.', ''),
            ];
        }

        return $rows;
    }

    /**
     * @return array{calls:int,customer_revenue:string,provider_cost:string,gross_margin:string}
     */
    public function reconciliationSummary(string $from, string $to): array
    {
        $statement = $this->pdo->prepare(
            'SELECT
                COUNT(*) AS calls,
                COALESCE(SUM(sessionbill), 0) AS customer_revenue,
                COALESCE(SUM(buycost), 0) AS provider_cost
             FROM cc_call
             WHERE starttime >= :from_date AND starttime < :to_date'
        );
        $statement->execute([
            'from_date' => $from,
            'to_date' => $to,
        ]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC) ?: [];

        $revenue = is_numeric($row['customer_revenue'] ?? null) ? (float)$row['customer_revenue'] : 0;
        $cost = is_numeric($row['provider_cost'] ?? null) ? (float)$row['provider_cost'] : 0;

        return [
            'calls' => (int)($row['calls'] ?? 0),
            'customer_revenue' => number_format($revenue, 5, '.', ''),
            'provider_cost' => number_format($cost, 5, '.', ''),
            'gross_margin' => number_format($revenue - $cost, 5, '.', ''),
        ];
    }

    private function startTimeDateInTimezone(string $startTime, string $timezone): string
    {
        try {
            $zone = new \DateTimeZone($timezone);
            $value = new \DateTimeImmutable($startTime, new \DateTimeZone('UTC'));
            return $value->setTimezone($zone)->format('Y-m-d');
        } catch (\Throwable) {
            return substr($startTime, 0, 10);
        }
    }
}
