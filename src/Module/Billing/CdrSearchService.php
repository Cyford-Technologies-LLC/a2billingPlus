<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Billing;

final class CdrSearchService
{
    public function __construct(private readonly CdrRepository $repository)
    {
    }

    /**
     * @return array{items:list<array<string, mixed>>,columns:list<string>}
     */
    public function search(CdrSearchCriteria $criteria): array
    {
        return $this->repository->search($criteria);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detail(int $id): ?array
    {
        return $this->repository->find($id);
    }

    /**
     * @return array{items:list<array<string,mixed>>,columns:list<string>}
     */
    public function export(CdrSearchCriteria $criteria, bool $redact = true): array
    {
        $result = $this->repository->search($criteria);
        if (!$redact) {
            return $result;
        }

        foreach ($result['items'] as &$row) {
            if (isset($row['calledstation']) && is_scalar($row['calledstation'])) {
                $row['calledstation'] = $this->redactCalledStation((string)$row['calledstation']);
            }
        }
        unset($row);

        return $result;
    }

    private function redactCalledStation(string $value): string
    {
        $length = strlen($value);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', max(0, $length - 4)) . substr($value, -4);
    }
}
