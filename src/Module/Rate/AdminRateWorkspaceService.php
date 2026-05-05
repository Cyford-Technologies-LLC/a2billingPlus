<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Rate;

final class AdminRateWorkspaceService
{
    public function __construct(
        private readonly RatecardSearchService $ratecards,
        private readonly TariffService $tariffs
    ) {
    }

    /**
     * @return array{
     *     filters:array{prefix:string,tariff_plan_id:string,tag:string,destination:string,limit:int},
     *     summary:array{rate_rows:int,tariff_plans:int,tariff_groups:int,destinations:int},
     *     ratecards:array{items:list<array<string,mixed>>,columns:list<string>},
     *     destinations:array{items:list<array<string,mixed>>,columns:list<string>},
     *     tariff_plans:array{items:list<array<string,mixed>>,columns:list<string>},
     *     tariff_groups:array{items:list<array<string,mixed>>,columns:list<string>}
     * }
     */
    public function workspace(string $prefix, string $tariffPlanId, string $tag, string $destination, int $limit = 25): array
    {
        $limit = $limit > 0 && $limit <= 100 ? $limit : 25;
        $planId = $this->positiveIntOrNull($tariffPlanId);

        $ratecards = $this->ratecards->search(new RatecardSearchCriteria(
            $limit,
            0,
            $this->clean($prefix, 32),
            $planId,
            $this->clean($tag, 80)
        ));
        $destinations = $this->ratecards->destinations($this->clean($destination, 80), $limit, 0);
        $tariffPlans = $this->tariffs->list('tariff-plans', $limit, 0);
        $tariffGroups = $this->tariffs->list('tariff-groups', $limit, 0);

        return [
            'filters' => [
                'prefix' => $this->clean($prefix, 32),
                'tariff_plan_id' => $planId !== null ? (string)$planId : '',
                'tag' => $this->clean($tag, 80),
                'destination' => $this->clean($destination, 80),
                'limit' => $limit,
            ],
            'summary' => [
                'rate_rows' => count($ratecards['items']),
                'tariff_plans' => count($tariffPlans['items']),
                'tariff_groups' => count($tariffGroups['items']),
                'destinations' => count($destinations['items']),
            ],
            'ratecards' => $ratecards,
            'destinations' => $destinations,
            'tariff_plans' => $tariffPlans,
            'tariff_groups' => $tariffGroups,
        ];
    }

    private function positiveIntOrNull(string $value): ?int
    {
        $value = trim($value);
        if ($value === '' || preg_match('/^[0-9]+$/', $value) !== 1) {
            return null;
        }

        $int = (int)$value;
        return $int > 0 ? $int : null;
    }

    private function clean(string $value, int $maxLength): string
    {
        return substr(trim($value), 0, $maxLength);
    }
}
