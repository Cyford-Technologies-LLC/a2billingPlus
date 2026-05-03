<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Agent;

final class AgentService
{
    public function __construct(private readonly AgentRepository $repository)
    {
    }

    /**
     * @return array{items:list<array<string,mixed>>,columns:list<string>}
     */
    public function list(int $limit, int $offset, ?string $active = null): array
    {
        return $this->repository->list($limit, $offset, $active);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function detail(int $id): ?array
    {
        $agent = $this->repository->find($id);
        if ($agent === null) {
            return null;
        }

        $agent['commissions'] = $this->repository->commissions($id, 25);
        return $agent;
    }

    /**
     * @return array{items:list<array<string,mixed>>,columns:list<string>}
     */
    public function customers(int $agentId, int $limit, int $offset): array
    {
        return $this->repository->customers($agentId, $limit, $offset);
    }
}
