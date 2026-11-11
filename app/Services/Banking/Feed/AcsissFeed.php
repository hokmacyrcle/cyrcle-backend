<?php

namespace App\Services\Banking\Feed;

use RuntimeException;

/**
 * ACSISS bank feed from SISS Data Services.
 *
 * NOT CONNECTED YET. Each method below is where the call to their API goes once we have their
 * documentation and sandbox keys (see config/banking.php). Until then configured() is false and the
 * app shows "bank connection is being set up". Nothing else needs to change when this is filled in.
 */
class AcsissFeed implements BankFeed
{
    public function name(): string { return 'acsiss'; }

    public function configured(): bool { return false; }

    private function notReady(): RuntimeException
    {
        return new RuntimeException('The ACSISS connection has not been set up yet.');
    }

    public function institutions(string $search): array { throw $this->notReady(); }
    public function start(string $institutionId, string $returnUrl): array { throw $this->notReady(); }
    public function accounts(string $connectionRef): array { throw $this->notReady(); }
    public function transactions(string $connectionRef, string $accountRef, string $since): array { throw $this->notReady(); }
    public function revoke(string $connectionRef): void { throw $this->notReady(); }
}
