<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Telephony;

final class AdminTelephonyWorkspaceService
{
    public function __construct(
        private readonly DidService $dids,
        private readonly TrunkService $trunks,
        private readonly TelephonyAccountService $accounts,
        private readonly AsteriskConfigCheckService $asteriskChecks,
        private readonly ?\PDO $pdo = null
    ) {
    }

    /**
     * @param array{did:bool,trunk:bool,accounts:bool} $capabilities
     * @param array<string,string> $asteriskSettings
     * @return array{
     *   filters:array{customer_id:string,trunk_status:string,did_reserved:string,did_activated:string,limit:int},
     *   summary:array{dids:int,trunks:int,sip_accounts:int,iax_accounts:int,asterisk_ready:bool,vectavoip_requests:int,pjsip_trunks:int},
     *   vectavoip_inventory:array{items:list<array<string,mixed>>,columns:list<string>,total:int},
     *   customer_assignments:array{items:list<array<string,mixed>>,total:int},
     *   dids:array{items:list<array<string,mixed>>,columns:list<string>},
     *   trunks:array{items:list<array<string,mixed>>,columns:list<string>},
     *   sip_accounts:array{items:list<array<string,mixed>>,columns:list<string>},
     *   iax_accounts:array{items:list<array<string,mixed>>,columns:list<string>},
     *   vectavoip_requests:array{items:list<array<string,mixed>>,columns:list<string>},
     *   pjsip_trunks:array{items:list<array<string,mixed>>,columns:list<string>},
     *   asterisk:array{success:bool,checks:list<array{name:string,success:bool,message:string}>}
     * }
     */
    public function workspace(
        string $customerId,
        string $trunkStatus,
        string $didReserved,
        string $didActivated,
        int $limit,
        array $capabilities,
        array $asteriskSettings
    ): array {
        $limit = $limit > 0 && $limit <= 100 ? $limit : 25;
        $customerIdInt = $this->positiveIntOrNull($customerId);
        $trunkStatusInt = $this->binaryOrNull($trunkStatus);
        $didReservedInt = $this->binaryOrNull($didReserved);
        $didActivatedInt = $this->binaryOrNull($didActivated);

        $dids = ['items' => [], 'columns' => []];
        $trunks = ['items' => [], 'columns' => []];
        $sipAccounts = ['items' => [], 'columns' => []];
        $iaxAccounts = ['items' => [], 'columns' => []];
        $vectavoipRequests = ['items' => [], 'columns' => []];
        $pjsipTrunks = ['items' => [], 'columns' => []];
        $vectavoipInventory = ['items' => [], 'columns' => [], 'total' => 0];
        $customerAssignments = ['items' => [], 'total' => 0];

        if ($capabilities['did']) {
            $dids = $this->dids->list($limit, 0, $customerIdInt, $didReservedInt, $didActivatedInt);
        }
        if ($capabilities['trunk']) {
            $trunks = $this->trunks->list($limit, 0, $trunkStatusInt);
        }
        if ($capabilities['accounts']) {
            $sipAccounts = $this->accounts->list('sip', $limit, 0, $customerIdInt);
            $iaxAccounts = $this->accounts->list('iax', $limit, 0, $customerIdInt);
        }

        $asterisk = $this->asteriskChecks->check($asteriskSettings);
        if ($this->pdo !== null) {
            $vectavoipRequests = $this->vectavoipDidRequests($limit);
            $pjsipTrunks = $this->pjsipTrunkEndpoints($limit);
            $repository = new DidRepository($this->pdo);
            $vectavoipInventory = $repository->listAvailable($limit, 0);
            if ($customerIdInt !== null) {
                $customerAssignments = $repository->listAssignedToCustomer($customerIdInt, $limit, 0);
            }
        }

        return [
            'filters' => [
                'customer_id' => $customerIdInt !== null ? (string)$customerIdInt : '',
                'trunk_status' => $trunkStatusInt !== null ? (string)$trunkStatusInt : '',
                'did_reserved' => $didReservedInt !== null ? (string)$didReservedInt : '',
                'did_activated' => $didActivatedInt !== null ? (string)$didActivatedInt : '',
                'limit' => $limit,
            ],
            'summary' => [
                'dids' => count($dids['items']),
                'trunks' => count($trunks['items']),
                'sip_accounts' => count($sipAccounts['items']),
                'iax_accounts' => count($iaxAccounts['items']),
                'asterisk_ready' => $asterisk['success'],
                'vectavoip_requests' => count($vectavoipRequests['items']),
                'pjsip_trunks' => count($pjsipTrunks['items']),
            ],
            'dids' => $dids,
            'trunks' => $trunks,
            'sip_accounts' => $sipAccounts,
            'iax_accounts' => $iaxAccounts,
            'vectavoip_inventory' => $vectavoipInventory,
            'customer_assignments' => $customerAssignments,
            'vectavoip_requests' => $vectavoipRequests,
            'pjsip_trunks' => $pjsipTrunks,
            'asterisk' => $asterisk,
        ];
    }

    /**
     * @return array{items:list<array<string,mixed>>,columns:list<string>}
     */
    private function vectavoipDidRequests(int $limit): array
    {
        if (!$this->tableExists('cc_vectavoip_did_requests')) {
            return ['items' => [], 'columns' => []];
        }

        $columns = ['id', 'package_code', 'did_count', 'sms_enabled', 'e911_enabled', 'ratecard_id', 'trunk_id', 'account_number', 'registered_ip', 'status', 'created_at'];
        $statement = $this->pdo->prepare(
            'SELECT ' . implode(', ', $columns) . ' FROM cc_vectavoip_did_requests ORDER BY id DESC LIMIT :limit'
        );
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        return ['items' => $statement->fetchAll(\PDO::FETCH_ASSOC), 'columns' => $columns];
    }

    /**
     * @return array{items:list<array<string,mixed>>,columns:list<string>}
     */
    private function pjsipTrunkEndpoints(int $limit): array
    {
        if (!$this->tableExists('cc_a2bp_pjsip_endpoint_map') || !$this->tableExists('ps_endpoints')) {
            return ['items' => [], 'columns' => []];
        }

        $statement = $this->pdo->prepare(
            'SELECT
                m.endpoint_id,
                m.label,
                e.context,
                e.allow,
                m.updated_at
             FROM cc_a2bp_pjsip_endpoint_map m
             INNER JOIN ps_endpoints e ON e.id = m.endpoint_id
             WHERE m.endpoint_type = :endpoint_type
             ORDER BY m.updated_at DESC
             LIMIT :limit'
        );
        $statement->bindValue(':endpoint_type', 'trunk');
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        return [
            'items' => $statement->fetchAll(\PDO::FETCH_ASSOC),
            'columns' => ['endpoint_id', 'label', 'context', 'allow', 'updated_at'],
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

    private function binaryOrNull(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return $value === '0' ? 0 : ($value === '1' ? 1 : null);
    }

    private function tableExists(string $table): bool
    {
        if ($this->pdo === null || preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
            return false;
        }

        if ((string)$this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :table");
        } else {
            $statement = $this->pdo->prepare('SHOW TABLES LIKE :table');
        }
        $statement->execute([':table' => $table]);

        return $statement->fetchColumn() !== false;
    }
}
