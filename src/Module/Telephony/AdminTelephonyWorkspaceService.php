<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Telephony;

final class AdminTelephonyWorkspaceService
{
    public function __construct(
        private readonly DidService $dids,
        private readonly TrunkService $trunks,
        private readonly TelephonyAccountService $accounts,
        private readonly AsteriskConfigCheckService $asteriskChecks
    ) {
    }

    /**
     * @param array{did:bool,trunk:bool,accounts:bool} $capabilities
     * @param array<string,string> $asteriskSettings
     * @return array{
     *   filters:array{customer_id:string,trunk_status:string,did_reserved:string,did_activated:string,limit:int},
     *   summary:array{dids:int,trunks:int,sip_accounts:int,iax_accounts:int,asterisk_ready:bool},
     *   dids:array{items:list<array<string,mixed>>,columns:list<string>},
     *   trunks:array{items:list<array<string,mixed>>,columns:list<string>},
     *   sip_accounts:array{items:list<array<string,mixed>>,columns:list<string>},
     *   iax_accounts:array{items:list<array<string,mixed>>,columns:list<string>},
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
            ],
            'dids' => $dids,
            'trunks' => $trunks,
            'sip_accounts' => $sipAccounts,
            'iax_accounts' => $iaxAccounts,
            'asterisk' => $asterisk,
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
}
